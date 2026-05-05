# Déploiement Coachingpro

Workflow simple : tu push depuis ton PC, tu te connectes en SSH au serveur, tu lances `./deploy.sh`.

## Setup initial (à faire UNE FOIS sur le serveur)

### 1. Pré-requis système (Debian/Ubuntu)

```bash
sudo apt update
sudo apt install -y apache2 php8.4 php8.4-cli php8.4-fpm \
  php8.4-pgsql php8.4-intl php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-zip php8.4-opcache \
  postgresql git unzip

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Active mod_rewrite Apache
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### 2. Base PostgreSQL

```bash
sudo -u postgres psql <<EOF
CREATE USER coach_user WITH PASSWORD 'CHANGE_ME';
CREATE DATABASE coachingpro OWNER coach_user;
GRANT ALL PRIVILEGES ON DATABASE coachingpro TO coach_user;
EOF
```

### 3. Clone du repo

```bash
sudo mkdir -p /var/www/coachingpro
sudo chown $(whoami):$(whoami) /var/www/coachingpro
cd /var/www/coachingpro
git clone git@github.com:Betus971/Coachingpro.git .
```

> Si le clone SSH échoue : génère une clé sur le serveur (`ssh-keygen -t ed25519`) et ajoute la clé publique sur GitHub → Settings → Deploy Keys (read-only sur ce repo).

### 4. Configuration `.env.local`

```bash
cp .env.local.example .env.local
nano .env.local
```

Renseigne au minimum :
- `APP_SECRET` → `openssl rand -hex 32`
- `DATABASE_URL` avec le mot de passe choisi à l'étape 2
- `JWT_PASSPHRASE` → `openssl rand -hex 32`
- `CORS_ALLOW_ORIGIN` adapté à l'URL de ton app

### 5. VirtualHost Apache

```bash
sudo cp apache-vhost.example.conf /etc/apache2/sites-available/coachingpro.conf
sudo nano /etc/apache2/sites-available/coachingpro.conf  # adapte ServerName
sudo a2ensite coachingpro
sudo a2dissite 000-default  # facultatif si Coachingpro est seul
sudo systemctl reload apache2
```

### 6. Premier déploiement

```bash
chmod +x deploy.sh
./deploy.sh
```

Le script va :
- Pull le code
- Installer les dépendances Composer en mode prod
- Appliquer les migrations
- Générer les clés JWT
- Warmup le cache
- Régler les permissions sur `var/`

### 7. Vérification

```bash
curl http://coachingpro.local/api    # devrait renvoyer du JSON-LD (catalogue API Platform)
```

## Workflow quotidien

### En local (sur ton PC)
```bash
git add -A
git commit -m "feat: ..."
git push origin main
```

### Sur le serveur
```bash
ssh user@server
cd /var/www/coachingpro
./deploy.sh
```

## App mobile

L'app mobile (`mobile_app/`) ne se déploie pas via ce script — c'est une app Expo qui se distribue via :
- **Dev** : `npx expo start` sur ton PC, scan QR code avec Expo Go
- **Beta** : `eas build --profile preview --platform android` puis distribution interne
- **Prod** : `eas build --profile production` puis stores (Play / App Store)

Le `EXPO_PUBLIC_API_URL` doit pointer sur l'URL publique de ton API en prod (HTTPS recommandé).

## Troubleshooting

**Erreur 500 après déploiement** :
```bash
tail -50 var/log/prod-$(date +%Y-%m-%d).log
sudo tail -50 /var/log/apache2/coachingpro_error.log
```

**Permissions cassées sur `var/`** :
```bash
sudo chown -R $(whoami):www-data var/
sudo chmod -R g+rwX var/
```

**Migration foirée** :
```bash
php bin/console doctrine:migrations:list
php bin/console doctrine:migrations:execute --down 'DoctrineMigrations\VersionXXX'
```

**Cache pollué** :
```bash
rm -rf var/cache/*
./deploy.sh
```
