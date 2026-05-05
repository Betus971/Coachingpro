#!/usr/bin/env bash
###############################################################################
# Coachingpro — Script de déploiement
#
# À exécuter SUR LE SERVEUR (pas en local), depuis la racine du projet :
#   ssh user@server
#   cd /var/www/coachingpro
#   ./deploy.sh
#
# Pré-requis sur le serveur (à faire UNE FOIS, voir DEPLOY.md) :
#   - php >= 8.4 + extensions (pdo_pgsql, intl, ctype, iconv, opcache, mbstring)
#   - composer 2
#   - postgresql + base + user créés
#   - .env.local configuré
#   - VirtualHost Apache pointant sur public/
#   - clé SSH GitHub déposée pour cloner en SSH
#
# Le script est IDEMPOTENT : tu peux le relancer autant de fois que tu veux.
###############################################################################

set -euo pipefail

# ── Couleurs ──────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log()  { echo -e "${GREEN}[deploy]${NC} $*"; }
warn() { echo -e "${YELLOW}[deploy]${NC} $*"; }
err()  { echo -e "${RED}[deploy]${NC} $*" >&2; }

# ── Vérifs ────────────────────────────────────────────────────────────────
if [[ ! -f "composer.json" ]]; then
  err "À exécuter depuis la racine du projet (composer.json introuvable)."
  exit 1
fi

if [[ ! -f ".env.local" ]]; then
  err "Fichier .env.local manquant. Copie .env.local.example et configure-le."
  exit 1
fi

# ── 1. Pull du code ───────────────────────────────────────────────────────
log "Récupération du dernier code depuis GitHub..."
git fetch origin
git reset --hard origin/main

# ── 2. Dépendances Composer ───────────────────────────────────────────────
log "Installation des dépendances PHP (mode prod)..."
composer install --no-dev --optimize-autoloader --no-interaction

# ── 3. Migrations Doctrine ────────────────────────────────────────────────
log "Application des migrations Doctrine..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# ── 4. Cache prod ─────────────────────────────────────────────────────────
log "Warmup du cache prod..."
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear --no-warmup
APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup

# ── 5. Assets ─────────────────────────────────────────────────────────────
if [[ -d "assets" ]]; then
  log "Compilation des assets (importmap)..."
  php bin/console importmap:install || warn "importmap:install a échoué (non bloquant)"
  php bin/console asset-map:compile || warn "asset-map:compile a échoué (non bloquant)"
fi

# ── 6. Permissions sur var/ ───────────────────────────────────────────────
log "Réglage des permissions sur var/..."
mkdir -p var/cache var/log
# Apache (www-data sur Debian/Ubuntu) doit pouvoir écrire ici
if id www-data &>/dev/null; then
  chown -R "$(whoami):www-data" var/
  chmod -R g+rwX var/
fi

# ── 7. Génération clés JWT (si manquantes) ────────────────────────────────
if [[ ! -f config/jwt/private.pem ]]; then
  log "Génération des clés JWT..."
  php bin/console lexik:jwt:generate-keypair --no-interaction
fi

log "✓ Déploiement terminé."
log "  → Vérifie les logs : tail -f var/log/prod-$(date +%Y-%m-%d).log"
