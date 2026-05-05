import React from 'react';
import { StyleSheet, View, Text, ScrollView, TouchableOpacity } from 'react-native';
import { COLORS, SPACING, SIZES } from '../theme/tokens';
import { ArrowRight, Scale, Zap, Utensils } from 'lucide-react-native';

export default function DashboardScreen() {
  // Mock data - à remplacer par les appels API
  const currentWeight = 113.5;
  const targetWeight = 95.0;
  const progress = 45;

  return (
    <ScrollView style={styles.container}>
      {/* Hero Section */}
      <View style={styles.hero}>
        <Text style={styles.tag}>PROGRAMME ACTIF · 1M83</Text>
        <Text style={styles.title}>121 KG <Text style={styles.highlight}>→ 95 KG</Text></Text>
        
        <View style={styles.statsRow}>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{currentWeight} kg</Text>
            <Text style={styles.statLabel}>POIDS ACTUEL</Text>
          </View>
          <View style={styles.statItem}>
            <Text style={styles.statValueSecondary}>-7.7 kg</Text>
            <Text style={styles.statLabel}>PERDU</Text>
          </View>
        </View>

        {/* Progress Bar */}
        <View style={styles.progressContainer}>
          <View style={styles.progressHeader}>
            <Text style={styles.progressText}>121.2 kg</Text>
            <Text style={styles.progressPercent}>{progress}% atteint</Text>
            <Text style={styles.progressText}>95 kg 🎯</Text>
          </View>
          <View style={styles.progressBarBg}>
            <View style={[styles.progressBarFill, { width: `${progress}%` }]} />
          </View>
        </View>
      </View>

      {/* Action Cards */}
      <View style={styles.content}>
        <TouchableOpacity style={styles.card}>
          <View style={styles.cardHeader}>
            <View style={styles.cardTagContainer}>
              <Zap size={14} color={COLORS.primary} />
              <Text style={styles.cardTag}>AUJOURD'HUI</Text>
            </View>
            <ArrowRight size={18} color={COLORS.muted} />
          </View>
          <Text style={styles.cardTitle}>PUSH — LUNDI</Text>
          <Text style={styles.cardSubtitle}>Épaules · Pectoraux · Triceps</Text>
        </TouchableOpacity>

        <View style={styles.row}>
          <TouchableOpacity style={[styles.miniCard, { marginRight: SPACING.md }]}>
            <Scale size={20} color={COLORS.primary} />
            <Text style={styles.miniCardValue}>113.5 kg</Text>
            <Text style={styles.miniCardLabel}>POIDS</Text>
          </TouchableOpacity>
          
          <TouchableOpacity style={styles.miniCard}>
            <Utensils size={20} color={COLORS.secondary} />
            <Text style={styles.miniCardValue}>2150 kcal</Text>
            <Text style={styles.miniCardLabel}>DIÈTE</Text>
          </TouchableOpacity>
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.black,
  },
  hero: {
    padding: SPACING.lg,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
    paddingTop: SPACING.xl,
  },
  tag: {
    color: COLORS.primary,
    fontSize: 10,
    letterSpacing: 2,
    marginBottom: SPACING.xs,
  },
  title: {
    color: COLORS.white,
    fontSize: SIZES.font_xl,
    fontWeight: '900',
  },
  highlight: {
    color: COLORS.primary,
  },
  statsRow: {
    flexDirection: 'row',
    marginTop: SPACING.lg,
  },
  statItem: {
    marginRight: SPACING.xl,
  },
  statValue: {
    color: COLORS.primary,
    fontSize: SIZES.font_lg,
    fontWeight: 'bold',
  },
  statValueSecondary: {
    color: COLORS.secondary,
    fontSize: SIZES.font_lg,
    fontWeight: 'bold',
  },
  statLabel: {
    color: COLORS.muted,
    fontSize: 10,
    letterSpacing: 1,
    marginTop: 4,
  },
  progressContainer: {
    marginTop: SPACING.lg,
  },
  progressHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  progressText: {
    color: COLORS.muted,
    fontSize: 10,
  },
  progressPercent: {
    color: COLORS.primary,
    fontSize: 10,
    fontWeight: 'bold',
  },
  progressBarBg: {
    height: 4,
    backgroundColor: COLORS.grey,
    width: '100%',
  },
  progressBarFill: {
    height: 4,
    backgroundColor: COLORS.primary,
  },
  content: {
    padding: SPACING.lg,
  },
  card: {
    backgroundColor: COLORS.card,
    padding: SPACING.lg,
    borderWidth: 1,
    borderColor: COLORS.border,
    marginBottom: SPACING.md,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: SPACING.sm,
  },
  cardTagContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  cardTag: {
    color: COLORS.primary,
    fontSize: 10,
    fontWeight: 'bold',
    marginLeft: 6,
    letterSpacing: 1,
  },
  cardTitle: {
    color: COLORS.white,
    fontSize: SIZES.font_md,
    fontWeight: 'bold',
  },
  cardSubtitle: {
    color: COLORS.muted,
    fontSize: SIZES.font_sm,
    marginTop: 4,
  },
  row: {
    flexDirection: 'row',
  },
  miniCard: {
    flex: 1,
    backgroundColor: COLORS.card,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    alignItems: 'center',
  },
  miniCardValue: {
    color: COLORS.white,
    fontSize: SIZES.font_md,
    fontWeight: 'bold',
    marginTop: SPACING.sm,
  },
  miniCardLabel: {
    color: COLORS.muted,
    fontSize: 10,
    marginTop: 2,
  }
});
