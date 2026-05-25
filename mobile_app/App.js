import React, { useRef, useState, useEffect } from 'react';
import {
  BackHandler,
  StatusBar,
  StyleSheet,
  View,
  ActivityIndicator,
} from 'react-native';
import { WebView } from 'react-native-webview';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';

const APP_URL = process.env.EXPO_PUBLIC_API_URL ?? 'https://coachingpro.ubikd.com';

function AppWebView() {
  const webViewRef = useRef(null);
  const [canGoBack, setCanGoBack] = useState(false);

  // Gestion bouton retour Android
  useEffect(() => {
    const onBackPress = () => {
      if (canGoBack) {
        webViewRef.current?.goBack();
        return true; // consomme l'event
      }
      return false; // laisse Android quitter l'app
    };
    BackHandler.addEventListener('hardwareBackPress', onBackPress);
    return () => BackHandler.removeEventListener('hardwareBackPress', onBackPress);
  }, [canGoBack]);

  return (
    <View style={styles.container}>
      <StatusBar barStyle="light-content" backgroundColor="#1d232a" />
      <WebView
        ref={webViewRef}
        source={{ uri: APP_URL }}
        style={styles.webview}
        onNavigationStateChange={(state) => setCanGoBack(state.canGoBack)}
        startInLoadingState
        renderLoading={() => (
          <View style={styles.loading}>
            <ActivityIndicator size="large" color="#9b6dff" />
          </View>
        )}
        // Cookies Symfony session
        sharedCookiesEnabled
        thirdPartyCookiesEnabled
        // Optimisations
        javaScriptEnabled
        domStorageEnabled
        allowsInlineMediaPlayback
        mediaPlaybackRequiresUserAction={false}
        // User-agent Chrome standard — Google OAuth bloque les WebViews ("wv")
        // on se fait passer pour Chrome Mobile pour débloquer le login Google
        userAgent="Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36"
      />
    </View>
  );
}

export default function App() {
  return (
    <SafeAreaProvider>
      <SafeAreaView style={styles.safeArea} edges={['top']}>
        <AppWebView />
      </SafeAreaView>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#1d232a',
  },
  container: {
    flex: 1,
    backgroundColor: '#1d232a',
  },
  webview: {
    flex: 1,
  },
  loading: {
    position: 'absolute',
    top: 0, left: 0, right: 0, bottom: 0,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#1d232a',
  },
});
