/**
 * Config Expo dynamique. Permet d'injecter des variables d'env au build.
 *
 * Utilisation :
 *   - dev local LAN : EXPO_PUBLIC_API_URL=http://192.168.1.71:90 npx expo start
 *   - dev simulateur iOS : EXPO_PUBLIC_API_URL=http://localhost:90 npx expo start
 *   - prod : EXPO_PUBLIC_API_URL=https://api.coachpro.example eas build ...
 *
 * Tu peux aussi créer un fichier .env (avec dotenv si tu veux) pour ne pas
 * retaper la variable à chaque commande.
 */
module.exports = ({ config }) => ({
  ...config,
  name: 'mobile_app',
  slug: 'mobile_app',
  version: '1.0.0',
  orientation: 'portrait',
  icon: './assets/icon.png',
  userInterfaceStyle: 'light',
  newArchEnabled: true,
  splash: {
    image: './assets/splash-icon.png',
    resizeMode: 'contain',
    backgroundColor: '#0a0a0a',
  },
  ios: {
    supportsTablet: true,
  },
  android: {
    adaptiveIcon: {
      foregroundImage: './assets/adaptive-icon.png',
      backgroundColor: '#0a0a0a',
    },
    edgeToEdgeEnabled: true,
  },
  web: {
    favicon: './assets/favicon.png',
  },
  extra: {
    apiUrl: process.env.EXPO_PUBLIC_API_URL ?? 'http://192.168.1.71:90',
  },
});
