/**
 * Config Expo dynamique.
 *
 * URL d'API injectée au build via EXPO_PUBLIC_API_URL :
 *   - dev local : EXPO_PUBLIC_API_URL=http://192.168.1.71:90 npx expo start
 *   - APK perso : EXPO_PUBLIC_API_URL=http://192.168.1.71:90 eas build --profile preview --platform android
 *   - prod      : EXPO_PUBLIC_API_URL=https://api.coachpro.example eas build --profile production --platform android
 *
 * `usesCleartextTraffic: true` est nécessaire pour appeler l'API en HTTP simple
 * sur le LAN (Android bloque le cleartext par défaut depuis Android 9).
 * Pour la vraie prod, il faudra HTTPS et virer ce flag.
 */
module.exports = ({ config }) => ({
  ...config,
  name: 'CoachPro',
  slug: 'coachingpro',
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
    bundleIdentifier: 'com.donleo.coachingpro',
  },
  android: {
    package: 'com.donleo.coachingpro',
    versionCode: 1,
    adaptiveIcon: {
      foregroundImage: './assets/adaptive-icon.png',
      backgroundColor: '#0a0a0a',
    },
    edgeToEdgeEnabled: true,
    // Autorise les appels HTTP non chiffrés (LAN). À retirer en prod HTTPS.
    usesCleartextTraffic: true,
  },
  web: {
    favicon: './assets/favicon.png',
  },
  extra: {
    apiUrl: process.env.EXPO_PUBLIC_API_URL ?? 'http://192.168.1.71:90',
    // EAS project ID — sera rempli automatiquement par `eas init`.
    eas: {
      projectId: process.env.EAS_PROJECT_ID,
    },
  },
});
