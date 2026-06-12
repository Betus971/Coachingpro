# CoachPro — Application de coaching sportif & nutritionnel

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white) ![Symfony](https://img.shields.io/badge/Symfony-8.0-000000?logo=symfony&logoColor=white) ![API Platform](https://img.shields.io/badge/API_Platform-4.3-38A3A5) ![Doctrine](https://img.shields.io/badge/Doctrine-3.x-FC6A31?logo=doctrine&logoColor=white) ![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql&logoColor=white) ![JWT](https://img.shields.io/badge/Auth-JWT-FB015B?logo=jsonwebtokens&logoColor=white) ![React Native](https://img.shields.io/badge/React_Native-0.81-61DAFB?logo=react&logoColor=black) ![Expo](https://img.shields.io/badge/Expo-SDK_54-000020?logo=expo&logoColor=white) ![Mistral AI](https://img.shields.io/badge/Mistral_AI-coach-FF7000?logo=mistralai&logoColor=white) ![License](https://img.shields.io/badge/license-proprietary-lightgrey)

Application de suivi sportif et nutritionnel, conçue **API-First** et pensée **SaaS dès le premier jour**.

- **Phase 1 (MVP)** : usage personnel — tracker poids, perfs en salle, diète, avec un objectif de recomposition (121 → 95 kg, 4 séances PPL + cardio/sem).
- **Phase 2 (cible)** : plateforme SaaS pour coachs sportifs — chaque coach gère ses clients, assigne des programmes et suit leurs courbes de progression.

L'application est volontairement découpée en **un backend API unique** (Symfony / API Platform) consommé par **un client mobile** (React Native / Expo). Le même backend servira demain le web et d'autres coachs : aucune logique métier ne vit côté client.

---

## Stack technique & pourquoi ces choix

### Backend (le cœur)

| Techno | Version | Pourquoi ce choix |
|---|---|---|
| **PHP** | 8.4 | Typage strict, enums, attributs, propriétés `readonly` — du code robuste et auto-documenté. |
| **Symfony** | 8.0 | Framework mature et structurant, expertise existante. DI, Messenger, Security, Serializer prêts à l'emploi. |
| **API Platform** | 4.3 | Approche **API-First** : expose les entités Doctrine en REST/JSON-LD avec sécurité, pagination et filtres déclaratifs. Un seul backend pour mobile + web + futurs coachs, sans réécrire la couche API. |
| **Doctrine ORM** | 3.6 | Mapping objet propre, migrations versionnées, et surtout les **extensions de requête** qui portent le scoping multi-tenant (voir sécurité). |
| **PostgreSQL** | — | Intégrité relationnelle pour le modèle coach ↔ client ↔ logs, type `UUID` natif, et fonctions avancées (ex. `pg_trgm` prévu pour le matching d'exercices). |
| **LexikJWTAuthenticationBundle** | 3.2 | Authentification **stateless par JWT** : indispensable pour un client mobile/SPA, scalable, pas de session serveur à partager. |
| **Symfony Messenger** | — | Traite les tâches lourdes/IA **en asynchrone** hors du cycle requête (ex. recalibrage d'objectif après une pesée). Transport Doctrine, donc zéro infra supplémentaire. |
| **Symfony UID (UUID v7)** | — | IDs **triables temporellement**, indexables, et **non énumérables** dans l'API (pas de `/users/1`, `/users/2`) — important en multi-tenant. |
| **Mistral AI** | mistral-small | Coach IA : conseils personnalisés et explication des recalibrages. Modèle FR, bon rapport coût/qualité. **L'IA explique, elle ne décide pas** : les calculs (régression, projection) sont déterministes, le LLM ne fait que reformuler. |

### Client mobile

| Techno | Version | Pourquoi ce choix |
|---|---|---|
| **React Native** | 0.81 | Un seul code base iOS + Android. Remplace l'ancienne approche WebView pour une vraie app native. |
| **Expo** | SDK 54 | Build cloud via **EAS** (pas de toolchain native locale à maintenir), itération rapide, OTA updates. |
| **React Navigation** | 7 | Navigation par onglets + stack, standard de l'écosystème. |
| **axios + AsyncStorage** | — | Client HTTP vers l'API + stockage sécurisé du token JWT côté device. |
| **react-native-chart-kit / svg** | — | Courbes de progression (poids, perfs) directement dans l'app. |
| **Moteur JSC** (`jsEngine: jsc`) | — | Stabilité : Hermes désactivé volontairement (`newArchEnabled: false`) pour éviter les régressions sur ce projet. |

### Front web (dashboard Symfony)

Tailwind CSS 4 + DaisyUI, servis via l'AssetMapper Symfony (Stimulus / Turbo) — pour le tableau de bord côté serveur, sans build JS lourd.

---

## Fonctionnalités clés

- **Tracking complet** : poids & composition corporelle (`WeightLog`), nutrition quotidienne avec macros (`NutritionLog`), séances et séries (`WorkoutSession` → `WorkoutSet` → `Exercise`).
- **Programmes prescrits** : `Program` → `WorkoutTemplate` → `ExerciseTemplate`, assignés à un client via `ProgramAssignment`. Les **templates sont séparés des logs** : un coach modifie un programme sans casser l'historique passé.
- **Objectifs modulables + IA** : entité `Goal` (cible / délai / rythme) avec un `mode` qui décide quelle dimension l'IA a le droit d'ajuster. `GoalProjectionService` fait une régression déterministe sur les pesées, recalibre, et trace chaque modulation dans `GoalAdjustment` (audit append-only). Mistral reformule la décision en langage clair.
- **Coach IA** (`MistralCoachService`) : conseils contextualisés, avec **garde-fou de périmètre** (sport & nutrition uniquement, refus des sujets hors cadre).
- **Sécurité multi-tenant** : un client ne voit que ses données, un coach uniquement celles de ses clients (voir ci-dessous).
- **Intégrations** : SSO Google, import Google Fit, import de composition corporelle Boditrax (CSV), gamification (séries/streaks).

---

## Sécurité multi-tenant (défense en profondeur)

Deux couches complémentaires, détaillées dans [`ARCHITECTURE.md`](./ARCHITECTURE.md) :

1. **Lectures** — `App\Doctrine\Extension\CurrentUserExtension` : injecte automatiquement le `WHERE` de scoping sur toute collection/item API Platform. Ajouter une entité « owned » = l'inscrire dans la map, et elle est protégée (aucun `WHERE` oublié).
2. **Écritures & accès direct** — `App\Security\Voter\OwnedResourceVoter` : Voter unique (`VIEW`/`EDIT`/`CREATE`) pour toutes les entités owned. Self → ok ; client de mon périmètre coach → ok ; sinon refus.

JWT pour l'authentification stateless, UUID v7 non énumérables, et `securityPostDenormalize` sur les POST pour empêcher la création de ressources au nom d'un autre utilisateur.

---

## Structure du dépôt

```
src/
  Entity/        # Modèle Doctrine (User, WeightLog, NutritionLog, WorkoutSession,
                 #   WorkoutSet, Exercise, Program, Goal, GoalAdjustment, ...)
  Controller/    # Endpoints custom (Dashboard, Chat IA, GoogleFit, Analytics, ...)
  Service/       # MistralCoachService, GoalProjectionService, GoogleFitClient, ...
  Security/Voter # OwnedResourceVoter (autorisation multi-tenant)
  Doctrine/      # CurrentUserExtension (scoping des lectures)
  Message/ + MessageHandler/  # Pipeline async (recalibrage d'objectif)
  State/         # Processors API Platform (ownership, hash mot de passe)
migrations/      # Migrations Doctrine versionnées
config/          # Config Symfony (API Platform, security, messenger, ...)
mobile_app/      # Client React Native / Expo (CoachPro v2)
```

> **Note legacy** : les dossiers `androidApp/`, `iosApp/`, `desktopApp/`, `webApp/`, `shared/` et la config Gradle proviennent d'un ancien essai Kotlin Multiplatform **abandonné** au profit de React Native. À nettoyer.

---

## Démarrage rapide

### Backend (API Symfony)

```bash
composer install
cp .env.local.example .env.local      # configure DATABASE_URL, APP_SECRET, JWT_PASSPHRASE, MISTRAL_API_KEY
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair
symfony serve                          # ou php -S avec le doc root sur public/
```

L'API est exposée sur `/api` (catalogue JSON-LD API Platform).

### Worker asynchrone (recalibrage d'objectifs)

```bash
php bin/console messenger:consume async   # en service systemd en prod
```

### Mobile (Expo)

```bash
cd mobile_app
npm install
npx expo start          # scan du QR code avec Expo Go
# Build : eas build --profile preview --platform android
```

`EXPO_PUBLIC_API_URL` doit pointer sur l'URL publique de l'API (HTTPS en prod).

---

## Déploiement

Workflow : `git push` en local → SSH sur le serveur → `./deploy.sh` (idempotent : pull, composer prod, migrations, cache, clés JWT, permissions). Détails complets dans [`DEPLOY.md`](./DEPLOY.md).

---

## Roadmap

- **SaaS coachs** : tableaux de bord coach, gestion multi-clients, assignation de programmes à l'échelle.
- **Journalisation des séances en langage naturel** (l'utilisateur décrit ce qu'il a fait, l'IA le structure) — spec dans [`FEATURE_LOG_SEANCES_IA.md`](./FEATURE_LOG_SEANCES_IA.md).
- Nettoyage du scaffolding Kotlin Multiplatform.
