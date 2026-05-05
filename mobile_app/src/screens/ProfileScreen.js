import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { COLORS, SPACING } from '../theme/tokens';
import { useAuth } from '../context/AuthContext';

export default function ProfileScreen() {
  const { logout } = useAuth();
  return (
    <View style={styles.container}>
      <Text style={styles.text}>Mon Profil</Text>
      <TouchableOpacity style={styles.logoutBtn} onPress={logout}>
        <Text style={styles.logoutText}>DÉCONNEXION</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.black, justifyContent: 'center', alignItems: 'center' },
  text: { color: COLORS.white, marginBottom: SPACING.xl },
  logoutBtn: { backgroundColor: COLORS.error, padding: SPACING.md, borderRadius: 0 },
  logoutText: { color: COLORS.white, fontWeight: 'bold' }
});
