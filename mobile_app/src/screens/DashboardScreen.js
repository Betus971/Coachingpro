import React, { useCallback, useState } from 'react';
import {
  StyleSheet,
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { COLORS, SPACING, SIZES } from '../theme/tokens';
import { ArrowRight, Scale, Zap, Utensils, Calendar } from 'lucide-react-native';

import { weightsApi } from '../api/weights';
import { nutritionApi } from '../api/nutrition';
import { sessionsApi } from '../api/sessions';
import { programsApi, findTodayWorkout } from '../api/programs';
import { ScreenLoader, ErrorBlock } from '../components/ScreenLoader';
import { StatCard } from '../components/StatCard';
import { useAuth } from '../context/AuthContext';

// Targets — à terme, lire depuis user.profile.dailyTargets
const TARGETS = { proteins: 190, carbs: 270, fats: 75, kcal: 2700 };
const GOAL_KG = 95;

export default function DashboardScreen() {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      setError(null);
      // Charge tout en parallèle pour réduire la latence
      const [recentWeights, earliestWeight, todayNutrition, recentSessions, activeAssignment] =
        await Promise.all([
          weightsApi.recent(10),
          weightsApi.earliest(),
          nutritionApi.today(),
          sessionsApi.recent(3),
          programsApi.activeAssignment().catch(() => null),
        ]);

      setData({
        recentWeights,
        earliestWeight,
        todayNutrition,
        recentSessions,
        activeAssignment,
      });
    } catch (e) {
      setError(e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  // Recharge à chaque retour sur l'écran (utile après ajout pesée/nutrition)
  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    load();
  }, [load]);

  if (loading && !data) return <ScreenLoader message="Chargement..." />;
  if (error && !data) return <ErrorBlock error={error} onRetry={load} />;

  const { recentWeights, earliestWeight, todayNutrition, recentSessions, activeAssignment } = data;

  // Calculs
  const lastKg = recentWeights[0] ? parseFloat(recentWeights[0].weightKg) : null;
  const startKg = earliestWeight ? parseFloat(earliestWeight.weightKg) : lastKg;
  const lostKg = startKg != null && lastKg != null ? startKg - lastKg : 0;
  const totalToLose = startKg != null ? startKg - GOAL_KG : 0;
  const progress = totalToLose > 0 ? Math.round((lostKg / totalToLose) * 100) : 0;
  const remainingKg = lastKg != null ? lastKg - GOAL_KG : 0;

  const todayWorkout = findTodayWorkout(activeAssignment);
  const firstName = user?.profile?.firstName ?? 'Champion';

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={COLORS.primary} />}
    >
      {/* Hero */}
      <View style={styles.hero}>
        <Text style={styles.tag}>SALUT {firstName.toUpperCase()} · OBJECTIF {GOAL_KG} KG</Text>
        <Text style={styles.title}>
          {startKg != null ? `${startKg.toFixed(0)} KG ` : ''}
          <Text style={styles.highlight}>→ {GOAL_KG} KG</Text>
        </Text>

        <View style={styles.statsRow}>
          <StatCard
            label="POIDS ACTUEL"
            value={lastKg != null ? lastKg.toFixed(1) : '—'}
            suffix=" kg"
            color={COLORS.primary}
          />
          <StatCard
            label="PERDU"
            value={`${lostKg > 0 ? '−' : ''}${Math.abs(lostKg).toFixed(1)}`}
            suffix=" kg"
            color={lostKg > 0 ? COLORS.secondary : COLORS.white}
          />
          <StatCard
            label="RESTANT"
            value={remainingKg.toFixed(1)}
            suffix=" kg"
            color={COLORS.white}
          />
        </View>

        {/* Progress bar */}
        {startKg != null && (
          <View style={styles.progressContainer}>
            <View style={styles.progressHeader}>
              <Text style={styles.progressText}>{startKg.toFixed(1)} kg</Text>
              <Text style={styles.progressPercent}>{progress}% atteint</Text>
              <Text style={styles.progressText}>{GOAL_KG} kg 🎯</Text>
            </View>
            <View style={styles.progressBarBg}>
              <View style={[styles.progressBarFill, { width: `${Math.min(Math.max(progress, 0), 100)}%` }]} />
            </View>
          </View>
        )}
      </View>

      {/* Today's workout */}
      <View style={styles.section}>
        <Text style={styles.sectionTag}>AUJOURD'HUI</Text>
        {todayWorkout ? (
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <View style={styles.cardTagContainer}>
                <Zap size={14} color={COLORS.primary} />
                <Text style={styles.cardTag}>SÉANCE PRÉVUE</Text>
              </View>
              <ArrowRight size={18} color={COLORS.muted} />
            </View>
            <Text style={styles.cardTitle}>{todayWorkout.name?.toUpperCase()}</Text>
            <Text style={styles.cardSubtitle}>
              {todayWorkout.exerciseTemplates?.length ?? 0} exercices
            </Text>
          </View>
        ) : (
          <View style={[styles.card, styles.cardRest]}>
            <View style={styles.cardTagContainer}>
              <Calendar size={14} color={COLORS.secondary} />
              <Text style={[styles.cardTag, { color: COLORS.secondary }]}>REPOS ACTIF</Text>
            </View>
            <Text style={styles.cardTitle}>JOUR OFF</Text>
            <Text style={styles.cardSubtitle}>Marche · étirements · récup'</Text>
          </View>
        )}
      </View>

      {/* Stats jour */}
      <View style={styles.section}>
        <View style={styles.row}>
          <View style={[styles.miniCard, { marginRight: SPACING.md }]}>
            <Scale size={20} color={COLORS.primary} />
            <Text style={styles.miniCardValue}>
              {lastKg != null ? `${lastKg.toFixed(1)} kg` : '—'}
            </Text>
            <Text style={styles.miniCardLabel}>POIDS</Text>
          </View>

          <View style={styles.miniCard}>
            <Utensils size={20} color={COLORS.secondary} />
            <Text style={styles.miniCardValue}>
              {todayNutrition ? `${todayNutrition.kcal} kcal` : '— kcal'}
            </Text>
            <Text style={styles.miniCardLabel}>
              DIÈTE / {TARGETS.kcal}
            </Text>
          </View>
        </View>
      </View>

      {/* Dernières séances */}
      <View style={styles.section}>
        <Text style={styles.sectionTag}>DERNIÈRES SÉANCES</Text>
        {recentSessions.length === 0 ? (
          <Text style={styles.empty}>Aucune séance loggée. C'est l'heure de suer 💪</Text>
        ) : (
          recentSessions.map((s) => (
            <View key={s['@id'] ?? s.id} style={styles.sessionRow}>
              <View>
                <Text style={styles.sessionName}>{s.name}</Text>
                <Text style={styles.sessionMeta}>
                  {formatDate(s.performedAt)}
                  {s.durationMinutes ? ` · ${s.durationMinutes} min` : ''}
                  {s.rpe ? ` · RPE ${s.rpe}/10` : ''}
                </Text>
              </View>
              <View style={styles.sessionStats}>
                <Text style={styles.sessionSetCount}>{s.sets?.length ?? 0}</Text>
                <Text style={styles.miniCardLabel}>séries</Text>
              </View>
            </View>
          ))
        )}
      </View>
    </ScrollView>
  );
}

const formatDate = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short' });
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.black },
  hero: {
    padding: SPACING.lg,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
    paddingTop: SPACING.xl,
  },
  tag: { color: COLORS.primary, fontSize: 10, letterSpacing: 2, marginBottom: SPACING.xs },
  title: { color: COLORS.white, fontSize: SIZES.font_xl, fontWeight: '900' },
  highlight: { color: COLORS.primary },
  statsRow: { flexDirection: 'row', marginTop: SPACING.lg, flexWrap: 'wrap' },
  progressContainer: { marginTop: SPACING.lg },
  progressHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 },
  progressText: { color: COLORS.muted, fontSize: 10 },
  progressPercent: { color: COLORS.primary, fontSize: 10, fontWeight: 'bold' },
  progressBarBg: { height: 4, backgroundColor: COLORS.grey, width: '100%' },
  progressBarFill: { height: 4, backgroundColor: COLORS.primary },
  section: {
    paddingHorizontal: SPACING.lg,
    paddingVertical: SPACING.md,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  sectionTag: {
    color: COLORS.muted,
    fontSize: 10,
    letterSpacing: 2,
    marginBottom: SPACING.md,
    fontWeight: 'bold',
  },
  card: {
    backgroundColor: COLORS.card,
    padding: SPACING.lg,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  cardRest: { borderColor: COLORS.grey2 },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: SPACING.sm },
  cardTagContainer: { flexDirection: 'row', alignItems: 'center' },
  cardTag: { color: COLORS.primary, fontSize: 10, fontWeight: 'bold', marginLeft: 6, letterSpacing: 1 },
  cardTitle: { color: COLORS.white, fontSize: SIZES.font_md, fontWeight: 'bold' },
  cardSubtitle: { color: COLORS.muted, fontSize: SIZES.font_sm, marginTop: 4 },
  row: { flexDirection: 'row' },
  miniCard: {
    flex: 1,
    backgroundColor: COLORS.card,
    padding: SPACING.md,
    borderWidth: 1,
    borderColor: COLORS.border,
    alignItems: 'center',
  },
  miniCardValue: { color: COLORS.white, fontSize: SIZES.font_md, fontWeight: 'bold', marginTop: SPACING.sm },
  miniCardLabel: { color: COLORS.muted, fontSize: 10, marginTop: 2, letterSpacing: 1 },
  empty: { color: COLORS.muted, fontSize: SIZES.font_sm, fontStyle: 'italic' },
  sessionRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: SPACING.sm,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.grey,
  },
  sessionName: { color: COLORS.white, fontSize: SIZES.font_sm, fontWeight: 'bold' },
  sessionMeta: { color: COLORS.muted, fontSize: 11, marginTop: 2 },
  sessionStats: { alignItems: 'center' },
  sessionSetCount: { color: COLORS.primary, fontSize: SIZES.font_lg, fontWeight: 'bold' },
});
