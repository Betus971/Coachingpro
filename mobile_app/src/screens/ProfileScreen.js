import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView } from 'react-native';
import { COLORS, SPACING, SIZES } from '../theme/tokens';
import { LogOut, User as UserIcon, Mail, Ruler, Calendar } from 'lucide-react-native';
import { useAuth } from '../context/AuthContext';

export default function ProfileScreen() {
  const { user, logout } = useAuth();
  const profile = user?.profile;

  const fullName = profile
    ? `${profile.firstName ?? ''} ${profile.lastName ?? ''}`.trim()
    : 'Utilisateur';

  const role = profile?.roles?.includes('ROLE_COACH')
    ? 'COACH'
    : 'CLIENT';

  const age = profile?.birthDate
    ? Math.floor((Date.now() - new Date(profile.birthDate).getTime()) / (1000 * 60 * 60 * 24 * 365.25))
    : null;

  return (
    <ScrollView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{getInitials(fullName)}</Text>
        </View>
        <Text style={styles.name}>{fullName.toUpperCase() || '—'}</Text>
        <Text style={styles.role}>{role}</Text>
      </View>

      {/* Infos */}
      <View style={styles.section}>
        <Text style={styles.sectionTag}>INFORMATIONS</Text>

        <Row icon={<Mail size={16} color={COLORS.muted} />} label="Email" value={profile?.email ?? '—'} />
        <Row icon={<UserIcon size={16} color={COLORS.muted} />} label="Sexe" value={profile?.sex ? translateSex(profile.sex) : '—'} />
        <Row icon={<Ruler size={16} color={COLORS.muted} />} label="Taille" value={profile?.heightCm ? `${profile.heightCm} cm` : '—'} />
        <Row icon={<Calendar size={16} color={COLORS.muted} />} label="Âge" value={age != null ? `${age} ans` : '—'} />
      </View>

      {/* Logout */}
      <View style={styles.section}>
        <TouchableOpacity style={styles.logoutBtn} onPress={logout}>
          <LogOut size={16} color={COLORS.white} />
          <Text style={styles.logoutText}>DÉCONNEXION</Text>
        </TouchableOpacity>
      </View>

      {/* Footer */}
      <Text style={styles.footer}>CoachPro · v1.0.0</Text>
    </ScrollView>
  );
}

const Row = ({ icon, label, value }) => (
  <View style={styles.row}>
    {icon}
    <View style={{ marginLeft: SPACING.md, flex: 1 }}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={styles.rowValue}>{value}</Text>
    </View>
  </View>
);

const getInitials = (name) => {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/);
  return (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '') || '?';
};

const translateSex = (sex) => ({ male: 'Homme', female: 'Femme', other: 'Autre' }[sex] ?? sex);

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.black },
  header: {
    alignItems: 'center',
    padding: SPACING.xl,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  avatar: {
    width: 80,
    height: 80,
    backgroundColor: COLORS.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: SPACING.md,
  },
  avatarText: { color: COLORS.white, fontSize: SIZES.font_xl, fontWeight: '900' },
  name: { color: COLORS.white, fontSize: SIZES.font_lg, fontWeight: '900', letterSpacing: 1 },
  role: { color: COLORS.primary, fontSize: 10, letterSpacing: 3, marginTop: SPACING.xs, fontWeight: 'bold' },
  section: {
    padding: SPACING.lg,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  sectionTag: {
    color: COLORS.muted,
    fontSize: 10,
    letterSpacing: 2,
    fontWeight: 'bold',
    marginBottom: SPACING.md,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: SPACING.sm,
  },
  rowLabel: { color: COLORS.muted, fontSize: 10, letterSpacing: 1 },
  rowValue: { color: COLORS.white, fontSize: SIZES.font_sm, marginTop: 2 },
  logoutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.error,
    padding: SPACING.md,
  },
  logoutText: {
    color: COLORS.white,
    fontWeight: '900',
    letterSpacing: 2,
    marginLeft: SPACING.sm,
  },
  footer: {
    color: COLORS.muted,
    fontSize: 10,
    textAlign: 'center',
    padding: SPACING.xl,
    letterSpacing: 1,
  },
});
