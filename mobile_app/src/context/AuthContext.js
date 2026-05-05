import React, { createContext, useState, useEffect, useContext } from 'react';
import * as SecureStore from 'expo-secure-store';
import { authService } from '../api/auth';
import { setUnauthorizedHandler } from '../api/client';

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  // Branchement de l'auto-logout sur 401 (token expiré ou invalide)
  useEffect(() => {
    setUnauthorizedHandler(() => {
      setUser(null);
    });
    return () => setUnauthorizedHandler(null);
  }, []);

  useEffect(() => {
    loadStoredToken();
  }, []);

  const loadStoredToken = async () => {
    try {
      const token = await SecureStore.getItemAsync('user_token');
      if (!token) return;

      // Hydrate le user depuis /api/me — si le token est invalide,
      // l'interceptor 401 nettoiera tout seul.
      try {
        const profile = await authService.getProfile();
        setUser({ token, profile });
      } catch {
        // 401 déjà géré par l'interceptor (token supprimé)
        setUser(null);
      }
    } catch (e) {
      console.error('Erreur chargement token', e);
    } finally {
      setLoading(false);
    }
  };

  const login = async (email, password) => {
    try {
      const data = await authService.login(email, password);
      await SecureStore.setItemAsync('user_token', data.token);

      // Récupère le profil immédiatement après login
      let profile = null;
      try {
        profile = await authService.getProfile();
      } catch {
        // Pas bloquant — on garde au moins le token
      }

      setUser({ token: data.token, profile });
      return { success: true };
    } catch (e) {
      return { success: false, error: e.response?.data?.message || 'Erreur de connexion' };
    }
  };

  const logout = async () => {
    await SecureStore.deleteItemAsync('user_token');
    setUser(null);
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
