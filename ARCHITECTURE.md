# Coach App — Architecture (Phase 1: Modélisation)

## Vue d'ensemble

```
                    User (ROLE_COACH | ROLE_CLIENT)
                     │
                     │ self-ref (coach_id)
                     ▼
                    User (client)
                     │
        ┌────────────┼─────────────────────────────┐
        │            │                             │
        ▼            ▼                             ▼
   WeightLog    NutritionLog                 WorkoutSession
                                                   │
                                                   ▼
                                              WorkoutSet ──► Exercise
                                                                ▲
                                                                │
              Program ──► WorkoutTemplate ──► ExerciseTemplate ─┘
                 │
                 ▼
           ProgramAssignment ──► User (qui exécute)
```

Deux axes parallèles :

- **Logs** (`WeightLog`, `NutritionLog`, `WorkoutSession` + `WorkoutSet`) = ce que le client a réellement fait. Owned by `user`.
- **Prescription** (`Program` + `WorkoutTemplate` + `ExerciseTemplate`) = ce que le coach prescrit. Lié au client via `ProgramAssignment` (table de jointure avec dates).

`Exercise` est un **catalogue** partagé : exercices "système" visibles par tous + exercices privés créés par les coachs.

## Choix techniques principaux

| Choix | Pourquoi |
|---|---|
| Single User table + ROLE | STI Doctrine = piège, et un coach veut souvent tracker ses propres perfs. Plus simple, plus flexible. |
| UUID v7 partout | Sortable temporellement, indexable, pas d'énumération possible dans l'API. PostgreSQL natif via `Symfony\Uid`. |
| `decimal` pour kg, `smallint` pour macros | Précision pour les charges (1.25 kg increments), compact pour les macros. |
| Templates ≠ Logs | Un coach modifie son template sans casser l'historique des séances passées. Audit-friendly. |
| Filtre Doctrine + Voter | Defense in depth : le filtre rend les lectures sûres par défaut (`SELECT` automatiquement scopé), le voter gère la logique métier des écritures. |

## Sécurité multi-tenant

### Niveau 1 — Lectures : `App\Doctrine\Extension\CurrentUserExtension`

Implémente `QueryCollectionExtensionInterface` + `QueryItemExtensionInterface` (API Platform).
À chaque `GET /weights`, `GET /workout_sessions/{id}`, etc., l'extension injecte automatiquement :

```sql
-- Pour un client connecté :
WHERE entity.user_id = :current_user

-- Pour un coach connecté :
LEFT JOIN user u ON entity.user_id = u.id
WHERE u.id = :current_user OR u.coach_id = :current_user
```

**Bénéfice clé** : un dev qui ajoute une nouvelle entité "owned" l'inscrit dans la map `SCOPED_ENTITIES` et c'est protégé. Pas de WHERE oublié dans un repository custom.

### Niveau 2 — Écritures et accès direct : `App\Security\Voter\OwnedResourceVoter`

Voter unique gérant `VIEW`, `EDIT`, `CREATE` pour toutes les entités owned.
Logique :
- Owner = self → autorisé
- Owner = client de moi (je suis coach) → autorisé
- Sinon → refusé

Branché sur les opérations API Platform via :

```php
new Get(security: "is_granted('VIEW', object)"),
new Patch(security: "is_granted('EDIT', object)"),
new Post(securityPostDenormalize: "is_granted('CREATE', object)"),
```

`securityPostDenormalize` (vs `security`) est crucial sur le POST : il évalue après hydratation, donc on a accès à `object.user` pour vérifier qu'on ne crée pas une ressource pour un autre user.

### Niveau 3 — Validation métier (à venir)

Cas non couverts par les niveaux 1-2 (à raffiner) :
- Un client ne peut pas modifier `WeightLog.user` après création (Validator sur la requête PATCH)
- Un client ne peut pas créer un `ProgramAssignment` pour son coach (logique inverse)
- Un coach peut créer des `Program`, mais pas modifier les logs d'un client (un coach observe, ne falsifie pas) — à ajuster dans le voter en distinguant `EDIT` selon le type de ressource.

## TODO d'installation (à exécuter quand tu attaques le code)

```bash
composer require api lexik/jwt-authentication-bundle symfony/uid
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

Puis :
1. Créer un `App\State\UserPasswordHasher` (state processor) pour hasher `plainPassword` → `password` au POST.
2. Configurer JWT dans `config/packages/lexik_jwt_authentication.yaml` + `security.yaml`.
3. Enregistrer `CurrentUserExtension` comme service taggé `api_platform.doctrine.orm.query_extension.collection` et `…item` (autoconfigure devrait le faire seul, sinon explicite dans `services.yaml`).
4. Générer les Repository correspondants : `php bin/console make:entity --regenerate App` ou à la main.

## Roadmap modèle

- **Phase 2 (SaaS)** : ajouter `Subscription` (Stripe), quotas (nb max de clients), invitations (`CoachInvitation`).
- **Phase 3** : photos de progression (`ProgressPhoto`), messagerie coach↔client (`Message`), notifications.
- **Phase 4** : analyse IA des séances (intégration LLM pour suggestions auto basées sur les WorkoutSet historiques).
