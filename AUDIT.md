# Audit Technique — CoachPro
> **Date :** 2026-05-22 · **Périmètre :** MVP personnel → SaaS · **Stack :** Symfony 8 / PHP 8.4 / PostgreSQL / Gemini 2.0

---

## Sommaire

| Priorité | Nb | Catégorie |
|---|---|---|
| 🔴 CRITIQUE | 1 | Sécurité |
| 🟠 HAUTE | 2 | Sécurité / Config prod |
| 🟡 MOYENNE | 5 | Sécurité · Code · SaaS-readiness |
| 🔵 BASSE | 5 | Performance · Robustesse · UX |
| ⚪ INFO | 4 | Dette technique · Refactoring futur |

---

## 🔴 CRITIQUE

### [SEC-01] Secrets en clair dans `.env` commité

**Fichier :** `.env`

Le fichier `.env` commité dans git contient des credentials de production réels :

```
DATABASE_URL="postgresql://postgres:dodo971@127.0.0.1:5432/coach_pro..."
JWT_PASSPHRASE=6076a8e20088f7e4abdafad205d9619e76ee26b7c84d17f6b3a55fda2dc45548
GEMINI_API_KEY=AIzaSyBpaaJUXWeSXi3zBMv33bq3hGvT6qtVgOE
GOOGLE_CLIENT_SECRET=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
APP_SECRET=sgdrlkzngzaoeglzngzeoiazgnfpzhrignzpoghn75748848
```

Le `.gitignore` protège correctement `.env.local` et `.env.*.local` — mais pas `.env` lui-même.
En SaaS, si ce dépôt devient public ou accessible à un collaborateur, tous ces secrets sont compromis.

**Correction immédiate :**

1. Faire tourner les secrets exposés (regen JWT keypair, nouveau GEMINI_API_KEY, nouveau GOOGLE_CLIENT_SECRET, nouveau `APP_SECRET`)
2. Déplacer les valeurs réelles dans `.env.local` (non commité) ou via les **Symfony Secrets** (`bin/console secrets:set`)
3. Conserver dans `.env` uniquement des valeurs placeholder / documentation :

```dotenv
DATABASE_URL="postgresql://user:CHANGE_ME@127.0.0.1:5432/coach_pro?serverVersion=16"
GEMINI_API_KEY=your_key_here
```

---

## 🟠 HAUTE

### [SEC-02] `APP_ENV=dev` hardcodé dans `.env`

**Fichier :** `.env` ligne 16

Le fichier commité positionne `APP_ENV=dev`. En production, le profiler Symfony, la toolbar et les messages d'erreur détaillés sont donc actifs si `.env.local` ne surcharge pas cette valeur.

**Correction :** Définir `APP_ENV=prod` dans l'environnement système du serveur (variable Apache/systemd) ou dans `.env.local` sur le serveur. Ne jamais laisser `dev` comme défaut commité.

---

### [SEC-03] Tokens Google OAuth en clair en base

**Fichier :** `src/Entity/User.php` lignes ~80-90

Le commentaire dans le code le signale lui-même :

```php
// Chiffrer en prod (kernel.secret + Sodium) si on veut être propre.
#[ORM\Column(type: 'text', nullable: true)]
private ?string $googleAccessToken = null;

#[ORM\Column(type: 'text', nullable: true)]
private ?string $googleRefreshToken = null;
```

Si la BDD est compromise (dump SQL, accès direct PostgreSQL), tous les refresh tokens Google des utilisateurs sont lisibles en clair.

**Correction (phase SaaS) :** Chiffrer avec `sodium_crypto_secretbox` / `libsodium` en utilisant `kernel.secret` comme clé. Ou déléguer à un Doctrine type custom `EncryptedString`.

---

## 🟡 MOYENNE

### [SEC-04] Absence de rate limiting sur `/chat/send`

**Fichier :** `src/Controller/ChatController.php`

Un utilisateur authentifié peut envoyer des requêtes en boucle à `/chat/send`, générant autant d'appels Gemini API. Sans throttle, cela peut :
- épuiser le quota/budget Gemini rapidement
- saturer la table `chat_message`

**Correction :** Ajouter le composant `symfony/rate-limiter` :

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        chat_send:
            policy: sliding_window
            limit: 20
            interval: '1 minute'
```

```php
// ChatController::send()
$limiter = $this->limiterFactory->create($user->getId()->toRfc4122());
if (!$limiter->consume()->isAccepted()) {
    return $this->json(['error' => 'Trop de messages, attends une minute.'], 429);
}
```

---

### [SEC-05] `json_decode` sans guard dans `ChatController::send()`

**Fichier :** `src/Controller/ChatController.php` ligne ~44

```php
$data    = json_decode($request->getContent(), true);
$message = trim($data['message'] ?? '');
```

Si le body n'est pas du JSON valide, `json_decode` retourne `null`, et `$data['message']` lève une `TypeError` PHP (tentative d'accès sur null). Symfony retournera une 500.

**Correction :**

```php
$data = json_decode($request->getContent(), true);
if (!is_array($data)) {
    return $this->json(['error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
}
```

---

### [ARCH-01] `ChatMessage` hors scope du `OwnedResourceVoter`

**Fichier :** `src/Security/Voter/OwnedResourceVoter.php`

Le voter gère `WeightLog`, `NutritionLog`, `WorkoutSession`, etc., mais pas `ChatMessage`. Pour le MVP solo c'est sans risque (le controller force `$this->getUser()`), mais en phase SaaS multi-tenant, un coach qui accède à `/chat/history` pourrait potentiellement voir les messages d'un autre utilisateur si un bug de routing apparaît.

**Correction (avant SaaS) :** Ajouter `ChatMessage` dans le voter + un filtre Doctrine `CurrentUserExtension` analogue à ceux existants.

---

### [QUAL-01] Confusion sémantique `startWeight` dans `buildUserContext()`

**Fichier :** `src/Service/GeminiCoachService.php` ligne ~105

```php
$startWeight = $this->weightRepo->findOneBy(['user' => $user], ['weightKg' => 'DESC']);
```

Le "poids de départ" est cherché comme le **maximum de weightKg** (le plus lourd). C'est un proxy acceptable si l'utilisateur a toujours pesé moins que son poids initial, mais c'est sémantiquement faux — le poids de départ est le **plus ancien enregistrement**, pas le plus lourd.
Si l'utilisateur entre un jour un poids de rebond supérieur au départ, le calcul `kilos perdus` deviendra négatif.

**Correction :**

```php
$startWeight = $this->weightRepo->findOneBy(['user' => $user], ['loggedOn' => 'ASC']);
```

La même logique incorrecte est présente dans `buildDashboardPrompt()` — corriger les deux occurrences.

---

## 🔵 BASSE

### [PERF-01] `loadHistory()` exécuté à chaque page, même panel fermé

**Fichier :** `assets/controllers/chat_controller.js` ligne 17

```js
connect() {
    this.loadHistory();
}
```

À chaque navigation (Turbo ou rechargement), le widget fait un GET `/chat/history` même si l'utilisateur n'ouvre jamais le panel. Inutile si l'historique est long (30 messages).

**Correction :** Charger l'historique de manière lazy, uniquement à la première ouverture :

```js
connect() {
    this._historyLoaded = false;
}

toggle() {
    this.openValue = !this.openValue;
    this.panelTarget.classList.toggle('hidden', !this.openValue);
    if (this.openValue) {
        if (!this._historyLoaded) {
            this.loadHistory();
            this._historyLoaded = true;
        }
        this.inputTarget.focus();
        this.scrollToBottom();
    }
}
```

---

### [PERF-02] `pruneOldMessages` synchrone à chaque envoi

**Fichier :** `src/Controller/ChatController.php` ligne ~65

```php
$repo->pruneOldMessages($user, 100);
```

Ce DELETE est exécuté après chaque message. Pour le MVP c'est négligeable, mais en SaaS avec des milliers d'utilisateurs actifs, c'est du SQL inutile sur 99% des appels (la table n'aura quasi jamais > 100 messages).

**Correction :** Exécuter le prune de façon probabiliste ou via un Messenger worker :

```php
// Nettoyage 1 fois sur 20 en moyenne
if (random_int(1, 20) === 1) {
    $repo->pruneOldMessages($user, 100);
}
```

---

### [UX-01] Absence de timeout côté fetch dans `chat_controller.js`

**Fichier :** `assets/controllers/chat_controller.js` ligne ~48

Si Gemini met > 20s à répondre (ou ne répond pas), le typing indicator reste visible indéfiniment. L'utilisateur n'a aucun feedback qu'il y a un problème.

**Correction :**

```js
const controller = new AbortController();
const timeout = setTimeout(() => controller.abort(), 25000);
try {
    const res = await fetch('/chat/send', {
        signal: controller.signal,
        // ...
    });
} catch (e) {
    const msg = e.name === 'AbortError' ? '⚠️ Timeout — réessaie.' : '⚠️ Erreur réseau.';
    this.appendMessage('model', msg, now);
} finally {
    clearTimeout(timeout);
}
```

---

### [SEC-06] `always_remember_me: true` sans opt-in utilisateur

**Fichier :** `config/packages/security.yaml`

```yaml
remember_me:
    always_remember_me: true
```

Le cookie "remember me" (30 jours) est posé automatiquement sans que l'utilisateur coche une case. Sur un appareil partagé, cela laisse la session ouverte indéfiniment.

**Correction (MVP) :** Acceptable pour usage personnel. En SaaS, passer à `always_remember_me: false` et ajouter la case à cocher dans le formulaire de login.

---

### [QUAL-02] `ChatMessage.role` sans validation enum

**Fichier :** `src/Entity/ChatMessage.php`

Le champ `role` est un `VARCHAR(10)` sans contrainte d'énumération au niveau Doctrine ni au niveau DB. Une valeur arbitraire peut y être persistée si une autre partie du code appelle le constructeur avec une mauvaise chaîne.

**Correction :** Utiliser un `BackedEnum` PHP 8.1+ :

```php
enum ChatRole: string
{
    case User  = 'user';
    case Model = 'model';
}

// Dans l'entité :
#[ORM\Column(length: 10, enumType: ChatRole::class)]
private ChatRole $role;
```

---

## ⚪ INFO / Dette technique

### [INFO-01] Duplication de logique `buildUserContext` / `buildDashboardPrompt`

`GeminiCoachService` contient deux méthodes qui construisent un contexte utilisateur quasi-identique (`buildUserContext` pour le chat, `buildDashboardPrompt` pour les conseils). À refactorer en une méthode privée partagée quand la feature se stabilise.

---

### [INFO-02] Pas de tests automatisés

Aucun test unitaire ni fonctionnel n'est présent (PHPUnit est installé mais le répertoire `tests/` est vide). Avant la phase SaaS, il faudra au minimum :
- Tests fonctionnels sur les routes protégées (401/403 sans auth)
- Tests unitaires sur les méthodes critiques du Voter
- Tests sur `ChatController::send()` (body invalide, message vide, réponse Gemini KO)

---

### [INFO-03] Google Fit Controller non sécurisé par Voter

**Fichier :** `src/Controller/GoogleFitController.php`

À vérifier que les endpoints Google Fit appliquent bien `$this->getUser()` et non un user_id passé en paramètre (risque IDOR classique). Non audité en détail ici.

---

### [INFO-04] `.env.local.example` commité mais incomplet

**Fichier :** `.env.local.example`

Le fichier d'exemple n'est pas à jour : il ne contient pas les nouvelles variables `GEMINI_API_KEY` ni `GOOGLE_CLIENT_ID/SECRET`. Un nouveau développeur ne saura pas quelles variables configurer.

**Action :** Mettre à jour `.env.local.example` avec toutes les variables requises (valeurs vides ou placeholder).

---

## Récapitulatif des actions prioritaires

| # | Action | Effort | Priorité |
|---|---|---|---|
| 1 | Révoquer et rotater tous les secrets du `.env` commité | ~1h | 🔴 Immédiat |
| 2 | Déplacer les vrais secrets dans `.env.local` ou Symfony Secrets | ~30min | 🔴 Immédiat |
| 3 | Corriger `json_decode` guard dans `ChatController::send()` | ~10min | 🟡 Cette semaine |
| 4 | Corriger `startWeight` → `findOneBy(['loggedOn' => 'ASC'])` | ~5min | 🟡 Cette semaine |
| 5 | Ajouter rate limiting sur `/chat/send` | ~1h | 🟡 Avant SaaS |
| 6 | Lazy-load de l'historique chat (ne fetch qu'à l'ouverture) | ~15min | 🔵 Quand possible |
| 7 | Ajouter timeout AbortController sur le fetch chat | ~15min | 🔵 Quand possible |
| 8 | Étendre `OwnedResourceVoter` à `ChatMessage` | ~30min | 🟡 Avant SaaS |
| 9 | Chiffrer les tokens OAuth en BDD | ~2h | 🟠 Avant SaaS |
| 10 | Écrire les premiers tests fonctionnels | ~4h | 🟡 Avant SaaS |
