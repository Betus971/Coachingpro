/**
 * DashboardScreen — refonte UI Samsung Health / Google Fit
 * Dark mode · rings circulaires · cartes arrondies · typographie douce
 */
import React, { useCallback, useState } from 'react';
import {
  StyleSheet,
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
  StatusBar,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  Zap, Dumbbell, Scale, Flame, ChevronRight, Calendar, TrendingDown,
} from 'lucide-react-native';

import { COLORS, SPACING, FONT, RADIUS, opacity } from '../theme/tokens';
import { weightsApi } from '../api/weights';
import { nutritionApi } from '../api/nutrition';
import { sessionsApi } from '../api/sessions';
import { programsApi, findTodayWorkout } from '../api/programs';
import { ScreenLoader, ErrorBlock } from '../components/ScreenLoader';
import { useAuth } from '../context/AuthContext';
import ActivityRing from '../components/ActivityRing';
import MacroRingsRow from '../components/MacroRingsRow';

// ─── Targets (à externaliser vers user.profile à terme) ──────────────────────
const TARGETS = { proteins: 190, carbs: 270, fats: 75, kcal: 2700 };
const GOAL_KG = 95;

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatDate = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: 'short' });
};

const greeting = () => {
  const h = new Date().getHours();
  if (h < 6)  return 'Bonne nuit';
  if (h < 12) return 'Bonjour';
  if (h < 18) return 'Bon après-midi';
  return 'Bonsoir';
};

const rpeColor = (rpe) => {
  if (!rpe) return COLORS.textMuted;
  if (rpe <= 5) return COLORS.success;
  if (rpe <= 7) return COLORS.carbs;  // yellow
  return COLORS.danger;
};

// ─── Composants locaux ────────────────────────────────────────────────────────

/** Barre kcal horizontale (Samsung Health style) */
function KcalBar({ actual = 0, target = 2700 }) {
  const progress = Math.min(actual / target, 1);
  const pct = Math.round(progress * 100);
  const overTarget = actual > target;

  return (
    <View style={kcalStyles.wrap}>
      <View style={kcalStyles.header}>
        <View style={kcalStyles.left}>
          <Flame size={14} color={COLORS.energy} />
          <Text style={kcalStyles.title}>Calories</Text>
        </View>
        <Text style={[kcalStyles.value, overTarget && { color: COLORS.danger }]}>
          {actual} <Text style={kcalStyles.target}>/ {target} kcal</Text>
        </Text>
      </View>
      <View style={kcalStyles.trackBg}>
        <View
          style={[
            kcalStyles.fill,
            {
              width: `${pct}%`,
              backgroundColor: overTarget ? COLORS.danger : COLORS.energy,
            },
          ]}
        />
      </View>
      <Text style={kcalStyles.pct}>{pct}% de l'objectif journalier</Text>
    </View>
  );
}

const kcalStyles = StyleSheet.create({
  wrap:   { marginTop: SPACING.sm },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: SPACING.sm },
  left:   { flexDirection: 'row', alignItems: 'center', gap: 6 },
  title:  { color: COLORS.textSub, fontSize: FONT.sm },
  value:  { color: COLORS.text, fontSize: FONT.sm, fontWeight: '700' },
  target: { color: COLORS.textMuted, fontWeight: '400' },
  trackBg: {
    height: 6,
    backgroundColor: opacity(COLORS.energy, 0.18),
    borderRadius: RADIUS.full,
    overflow: 'hidden',
  },
  fill: { height: 6, borderRadius: RADIUS.full },
  pct:  { color: COLORS.textMuted, fontSize: FONT.xs, marginTop: 6 },
});

/** Card Section générique */
function SectionCard({ children, style }) {
  return <View style={[cardStyles.card, style]}>{children}</View>;
}

const cardStyles = StyleSheet.create({
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: RADIUS.lg,
    padding: SPACING.md,
    marginBottom: SPACING.md,
  },
});

/** Header de section avec titre et optionnel lien "Voir tout" */
function SectionHeader({ icon: Icon, iconColor, title, action, onAction }) {
  return (
    <View style={shStyles.row}>
      <View style={shStyles.left}>
        {Icon && <Icon size={14} color={iconColor ?? COLORS.textSub} style={shStyles.icon} />}
        <Text style={shStyles.title}>{title}</Text>
      </View>
      {action && (
        <TouchableOpacity onPress={onAction} style={shStyles.action}>
          <Text style={shStyles.actionText}>{action}</Text>
          <ChevronRight size={12} color={COLORS.primary} />
        </TouchableOpacity>
      )}
    </View>
  );
}

const shStyles = StyleSheet.create({
  row:        { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: SPACING.md },
  left:       { flexDirection: 'row', alignItems: 'center' },
  icon:       { marginRight: 6 },
  title:      { color: COLORS.textSub, fontSize: FONT.xs, fontWeight: '600', letterSpacing: 0.8, textTransform: 'uppercase' },
  action:     { flexDirection: 'row', alignItems: 'center' },
  actionText: { color: COLORS.primary, fontSize: FONT.xs, marginRight: 2 },
});

// ─── Screen principal ─────────────────────────────────────────────────────────
export default function DashboardScreen() {
  const { user } = useAuth();
  const insets = useSafeAreaInsets();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      setError(null);
      const [recentWeights, earliestWeight, todayNutrition, recentSessions, activeAssignment] =
        await Promise.all([
          weightsApi.recent(10),
          weightsApi.earliest(),
          nutritionApi.today(),
          sessionsApi.recent(3),
          programsApi.activeAssignment().catch(() => null),
        ]);
      setData({ recentWeights, earliestWeight, todayNutrition, recentSessions, activeAssignment });
    } catch (e) {
      setError(e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const onRefresh = useCallback(() => { setRefreshing(true); load(); }, [load]);

  if (loading && !data) return <ScreenLoader message="Chargement..." />;
  if (error && !data)   return <ErrorBlock error={error} onRetry={load} />;

  const { recentWeights, earliestWeight, todayNutrition, recentSessions, activeAssignment } = data;

  // ─── Calculs poids ───────────────────────────────────────────────────────
  const lastKg      = recentWeights[0] ? parseFloat(recentWeights[0].weightKg) : null;
  const startKg     = earliestWeight   ? parseFloat(earliestWeight.weightKg)   : lastKg;
  const lostKg      = startKg != null && lastKg != null ? startKg - lastKg : 0;
  const totalToLose = startKg != null ? startKg - GOAL_KG : 1;
  const weightProgress = totalToLose > 0 ? Math.max(0, Math.min(lostKg / totalToLose, 1)) : 0;
  const remainingKg = lastKg != null ? Math.max(0, lastKg - GOAL_KG) : 0;

  // ─── Calculs nutrition ───────────────────────────────────────────────────
  const actualMacros = todayNutrition
    ? { proteins: todayNutrition.proteins ?? 0, carbs: todayNutrition.carbs ?? 0, fats: todayNutrition.fats ?? 0 }
    : { proteins: 0, carbs: 0, fats: 0 };
  const actualKcal = todayNutrition?.kcal ?? 0;

  // ─── Séance du jour ──────────────────────────────────────────────────────
  const todayWorkout = findTodayWorkout(activeAssignment);
  const firstName = user?.profile?.firstName ?? 'Champion';

  return (
    <>
      <StatusBar barStyle="light-content" backgroundColor={COLORS.background} />
      <ScrollView
        style={styles.root}
        contentContainerStyle={[styles.content, { paddingTop: insets.top + SPACING.md }]}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor={COLORS.primary}
            colors={[COLORS.primary]}
          />
        }
      >
        {/* ── HEADER ──────────────────────────────────────────────────────── */}
        <View style={styles.header}>
          <View>
            <Text style={styles.greeting}>{greeting()},</Text>
            <Text style={styles.name}>{firstName}</Text>
          </View>
          <View style={styles.dateBadge}>
            <Calendar size={12} color={COLORS.textSub} />
            <Text style={styles.dateText}>
              {new Date().toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })}
            </Text>
          </View>
        </View>

        {/* ── OBJECTIF POIDS — grande ring centrale ───────────────────────── */}
        <SectionCard style={styles.goalCard}>
          <SectionHeader icon={TrendingDown} iconColor={COLORS.primary} title="Objectif poids" />

          <View style={styles.goalBody}>
            {/* Ring principale */}
            <ActivityRing
              size={140}
              progress={weightProgress}
              color={COLORS.primary}
              trackColor={opacity(COLORS.primary, 0.15)}
              strokeWidth={12}
            >
              <View style={styles.ringInner}>
                <Text style={styles.ringKg}>
                  {lastKg != null ? lastKg.toFixed(1) : '—'}
                </Text>
                <Text style={styles.ringUnit}>kg</Text>
                <Text style={styles.ringPct}>
                  {Math.round(weightProgress * 100)}%
                </Text>
              </View>
            </ActivityRing>

            {/* Stats à droite */}
            <View style={styles.goalStats}>
              <GoalStat
                label="Départ"
                value={startKg != null ? `${startKg.toFixed(1)} kg` : '—'}
                color={COLORS.textSub}
              />
              <GoalStat
                label="Perdu"
                value={lostKg > 0 ? `−${lostKg.toFixed(1)} kg` : '— kg'}
                color={COLORS.success}
              />
              <GoalStat
                label="Restant"
                value={`${remainingKg.toFixed(1)} kg`}
                color={COLORS.primary}
              />
              <GoalStat
                label="Objectif"
                value={`${GOAL_KG} kg`}
                color={COLORS.textMuted}
              />
            </View>
          </View>
        </SectionCard>

        {/* ── SÉANCE DU JOUR ──────────────────────────────────────────────── */}
        <SectionCard>
          <SectionHeader icon={Dumbbell} iconColor={COLORS.activity} title="Séance du jour" />
          {todayWorkout ? (
            <TouchableOpacity style={styles.workoutCard} activeOpacity={0.75}>
              <View style={[styles.workoutAccent, { backgroundColor: COLORS.activity }]} />
              <View style={styles.workoutBody}>
                <Text style={styles.workoutName}>{todayWorkout.name}</Text>
                <Text style={styles.workoutSub}>
                  {todayWorkout.exerciseTemplates?.length ?? 0} exercices prévus
                </Text>
              </View>
              <View style={[styles.workoutBadge, { backgroundColor: opacity(COLORS.activity, 0.15) }]}>
                <Zap size={18} color={COLORS.activity} />
              </View>
            </TouchableOpacity>
          ) : (
            <View style={styles.restCard}>
              <View style={[styles.workoutBadge, { backgroundColor: opacity(COLORS.success, 0.12) }]}>
                <Calendar size={18} color={COLORS.success} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.restTitle}>Jour de repos</Text>
                <Text style={styles.restSub}>Marche · Étirements · Récupération</Text>
              </View>
            </View>
          )}
        </SectionCard>

        {/* ── NUTRITION ───────────────────────────────────────────────────── */}
        <SectionCard>
          <SectionHeader icon={Flame} iconColor={COLORS.energy} title="Nutrition du jour" />

          {/* Barre kcal */}
          <KcalBar actual={actualKcal} target={TARGETS.kcal} />

          {/* Divider */}
          <View style={styles.divider} />

          {/* Rings macros */}
          <MacroRingsRow actual={actualMacros} targets={TARGETS} />
        </SectionCard>

        {/* ── DERNIÈRES SÉANCES ───────────────────────────────────────────── */}
        <SectionCard style={styles.lastSection}>
          <SectionHeader icon={Scale} iconColor={COLORS.textSub} title="Dernières séances" />

          {recentSessions.length === 0 ? (
            <View style={styles.emptyState}>
              <Text style={styles.emptyText}>Aucune séance loggée.</Text>
              <Text style={styles.emptyHint}>C'est l'heure de suer 💪</Text>
            </View>
          ) : (
            recentSessions.map((s, i) => (
              <View
                key={s['@id'] ?? s.id}
                style={[styles.sessionRow, i < recentSessions.length - 1 && styles.sessionBorder]}
              >
                <View style={[styles.sessionDot, { backgroundColor: COLORS.activity }]} />
                <View style={styles.sessionInfo}>
                  <Text style={styles.sessionName}>{s.name}</Text>
                  <Text style={styles.sessionMeta}>
                    {formatDate(s.performedAt)}
                    {s.durationMinutes ? ` · ${s.durationMinutes} min` : ''}
                  </Text>
                </View>
                <View style={styles.sessionRight}>
                  {s.sets?.length > 0 && (
                    <Text style={styles.sessionSets}>{s.sets.length} séries</Text>
                  )}
                  {s.rpe && (
                    <View style={[styles.rpeBadge, { backgroundColor: opacity(rpeColor(s.rpe), 0.15) }]}>
                      <Text style={[styles.rpeText, { color: rpeColor(s.rpe) }]}>
                        RPE {s.rpe}
                      </Text>
                    </View>
                  )}
                </View>
              </View>
            ))
          )}
        </SectionCard>

        {/* Padding bas de sécurité */}
        <View style={{ height: SPACING.xl }} />
      </ScrollView>
    </>
  );
}

// ─── GoalStat (mini) ──────────────────────────────────────────────────────────
function GoalStat({ label, value, color }) {
  return (
    <View style={gsStyles.item}>
      <Text style={gsStyles.label}>{label}</Text>
      <Text style={[gsStyles.value, { color }]}>{value}</Text>
    </View>
  );
}
const gsStyles = StyleSheet.create({
  item:  { marginBottom: SPACING.md },
  label: { color: COLORS.textMuted, fontSize: FONT.xs, marginBottom: 2 },
  value: { fontSize: FONT.sm, fontWeight: '700' },
});

// ─── Styles globaux ───────────────────────────────────────────────────────────
const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  content: {
    paddingHorizontal: SPACING.md,
  },

  // Header
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: SPACING.lg,
  },
  greeting: {
    color: COLORS.textSub,
    fontSize: FONT.sm,
  },
  name: {
    color: COLORS.text,
    fontSize: FONT.xl,
    fontWeight: '800',
    letterSpacing: -0.5,
  },
  dateBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    backgroundColor: COLORS.surface,
    paddingHorizontal: SPACING.sm,
    paddingVertical: 6,
    borderRadius: RADIUS.full,
  },
  dateText: {
    color: COLORS.textSub,
    fontSize: FONT.xs,
    textTransform: 'capitalize',
  },

  // Goal card
  goalCard: {
    paddingBottom: SPACING.lg,
  },
  goalBody: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  ringInner: {
    alignItems: 'center',
  },
  ringKg: {
    color: COLORS.text,
    fontSize: FONT.xl,
    fontWeight: '800',
    lineHeight: FONT.xl * 1.1,
  },
  ringUnit: {
    color: COLORS.textSub,
    fontSize: FONT.xs,
    marginTop: -2,
  },
  ringPct: {
    color: COLORS.primary,
    fontSize: FONT.xs,
    fontWeight: '700',
    marginTop: 4,
  },
  goalStats: {
    flex: 1,
    paddingLeft: SPACING.lg,
  },

  // Workout
  workoutCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.surfaceHigh,
    borderRadius: RADIUS.md,
    overflow: 'hidden',
  },
  workoutAccent: {
    width: 4,
    alignSelf: 'stretch',
  },
  workoutBody: {
    flex: 1,
    paddingVertical: SPACING.md,
    paddingHorizontal: SPACING.md,
  },
  workoutName: {
    color: COLORS.text,
    fontSize: FONT.md,
    fontWeight: '700',
  },
  workoutSub: {
    color: COLORS.textSub,
    fontSize: FONT.sm,
    marginTop: 3,
  },
  workoutBadge: {
    width: 40,
    height: 40,
    borderRadius: RADIUS.full,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: SPACING.md,
  },
  restCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.md,
    backgroundColor: COLORS.surfaceHigh,
    borderRadius: RADIUS.md,
    padding: SPACING.md,
  },
  restTitle: {
    color: COLORS.text,
    fontSize: FONT.md,
    fontWeight: '600',
  },
  restSub: {
    color: COLORS.textSub,
    fontSize: FONT.sm,
    marginTop: 2,
  },

  // Divider
  divider: {
    height: 1,
    backgroundColor: COLORS.divider,
    marginVertical: SPACING.md,
  },

  // Sessions
  lastSection: {
    marginBottom: 0,
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: SPACING.lg,
  },
  emptyText: {
    color: COLORS.textSub,
    fontSize: FONT.sm,
  },
  emptyHint: {
    color: COLORS.textMuted,
    fontSize: FONT.sm,
    marginTop: 4,
  },
  sessionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: SPACING.md,
  },
  sessionBorder: {
    borderBottomWidth: 1,
    borderBottomColor: COLORS.divider,
  },
  sessionDot: {
    width: 8,
    height: 8,
    borderRadius: RADIUS.full,
    marginRight: SPACING.md,
  },
  sessionInfo: {
    flex: 1,
  },
  sessionName: {
    color: COLORS.text,
    fontSize: FONT.sm,
    fontWeight: '600',
  },
  sessionMeta: {
    color: COLORS.textMuted,
    fontSize: FONT.xs,
    marginTop: 2,
    textTransform: 'capitalize',
  },
  sessionRight: {
    alignItems: 'flex-end',
    gap: 4,
  },
  sessionSets: {
    color: COLORS.textSub,
    fontSize: FONT.xs,
  },
  rpeBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: RADIUS.full,
  },
  rpeText: {
    fontSize: FONT.xs,
    fontWeight: '700',
  },
});
