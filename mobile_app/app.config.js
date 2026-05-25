/**
 * Config Expo — WebView wrapper de l'app Symfony CoachPro
 *
 * URL injectée via EXPO_PUBLIC_API_URL au build :
 *   dev  : EXPO_PUBLIC_API_URL=http://192.168.1.71:90 npx expo start
 *   APK  : défini dans eas.json > build.preview.env
 *   prod : défini dans eas.json > build.production.env
 */
module.exports = ({ config }) => ({
  ...config,
  name: 'CoachPro',
  slug: 'coachingpro',
  version: '1.0.0',
  orientation: 'portrait',
  icon: './assets/icon.png',
  userInterfaceStyle: 'dark',
  newArchEnabled: false,
  splash: {
    image: './assets/splash-icon.png',
    resizeMode: 'contain',
    backgroundColor: '#1d232a',
  },
  ios: {
    supportsTablet: true,
    bundleIdentifier: 'com.donleo.coachingpro',
  },
  android: {
    package: 'com.donleo.coachingpro',
    versionCode: 3,
    adaptiveIcon: {
      foregroundImage: './assets/adaptive-icon.png',
      backgroundColor: '#1d232a',
    },
    edgeToEdgeEnabled: true,
    usesCleartextTraffic: true,
  },
  web: {
    favicon: './assets/favicon.png',
  },
  extra: {
    apiUrl: process.env.EXPO_PUBLIC_API_URL ?? 'http://192.168.1.71:90',
    eas: {
      projectId: 'fc3d8583-50f9-4857-b163-e23f91eeb867',
    },
  },
});
