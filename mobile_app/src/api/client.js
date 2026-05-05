import axios from 'axios';
import * as SecureStore from 'expo-secure-store';
import Constants from 'expo-constants';

// L'URL est lue depuis app.config.js (extra.apiUrl), avec fallback dev.
const API_URL =
  Constants.expoConfig?.extra?.apiUrl ??
  Constants.manifest?.extra?.apiUrl ??
  'http://192.168.1.71:90';

const apiClient = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

apiClient.interceptors.request.use(async (config) => {
  const token = await SecureStore.getItemAsync('user_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Callback enregistré par AuthProvider pour réagir à un 401
// (token expiré ou invalide → on déconnecte l'utilisateur).
let onUnauthorized = null;
export const setUnauthorizedHandler = (handler) => {
  onUnauthorized = handler;
};

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      await SecureStore.deleteItemAsync('user_token');
      if (typeof onUnauthorized === 'function') {
        onUnauthorized();
      }
    }
    return Promise.reject(error);
  }
);

export default apiClient;
