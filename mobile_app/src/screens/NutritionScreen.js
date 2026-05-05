import React, { useCallback, useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  ScrollView,
  Alert,
  RefreshControl,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { COLORS, SPACING, SIZES } from '../theme/tokens';
import { nutritionApi } from '../api/nutrition';
import { ScreenLoader, ErrorBlock } from '../components/ScreenLoader';
import { MacroBar } from '../components/MacroBar';
import { idFromIri } from '../api/_utils';

const TARGETS = { proteins: 190, carbs: 270, fats: 75, kcal: 2700 };

export default function NutritionScreen() {
  const [todayLog, setTodayLog] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [refreshing, setRefreshing] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const [form, setForm] = useState({ proteinsG: '', carbsG: '', fatsG: '', kcal: '' });

  const load = useCallback(async () => {
    try {
      setError(null);
      const log = await nutritionApi.today();
      setTodayLog(log);
      if (log) {
        setForm({
          proteinsG: String(log.proteinsG ?? ''),
          carbsG: String(log.carbsG ?? ''),
          fatsG: String(log.fatsG ?? ''),
          kcal: String(log.kcal ?? ''),
        });
      } else {
        setForm({ proteinsG: '', carbsG: '', fatsG: '', kcal: '' });
      }
    } catch (e) {
      setError(e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  // Auto-calcul des kcal si l'utilisateur n'a pas saisi
  useEffect(() => {
    const p = parseFloat(form.proteinsG) || 0;
    const c = parseFloat(form.carbsG) || 0;
    const f = parseFloat(form.fatsG) || 0;
    if (p > 0 || c > 0 || f > 0) {
      const computed = Math.round(p * 4 + c * 4 + f * 9);
      // Ne remplace que si le user n'a rien saisi manuellement
      if (!form.kcal || (todayLog && parseInt(form.kcal, 10) === todayLog.kcal)) {
        setForm((prev) => ({ ...prev, kcal: String(computed) }));
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.proteinsG, form.carbsG, form.fatsG]);

  const handleSubmit = async () => {
    const payload = {
      proteinsG: parseInt(form.proteinsG, 10) || 0,
      carbsG: parseInt(form.carbsG, 10) || 0,
      fatsG: parseInt(form.fatsG, 10) || 0,
      kcal: parseInt(form.kcal, 10) || 0,
    };
    if (payload.proteinsG === 0 && payload.carbsG === 0 && payload.fatsG === 0) {
      Alert.alert('Erreur', 'Saisis au moins une valeur.');
      return;
    }
    setSubmitting(true);
    try {
      if (todayLog) {
        const id = todayLog.id ?? idFromIri(todayLog['@id']);
        await nutritionApi.update(id, payload);
      } else {
        await nutritionApi.create(payload);
      }
      await load();
    } catch (e) {
      const msg = e?.response?.data?.['hydra:description']
        || e?.response?.data?.detail
        || 'Erreur enregistrement.';
      Alert.alert('Erreur', msg);
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) return <ScreenLoader message="Chargement..." />;
  if (error && !todayLog) return <ErrorBlock error={error} onRetry={load} />;

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      style={styles.container}
    >
      <ScrollView
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={COLORS.primary} />}
        keyboardShouldPersistTaps="handled"
      >
        {/* Récap macros */}
        {todayLog && (
          <View style={styles.section}>
            <Text style={styles.sectionTag}>MACROS DU JOUR</Text>
            <View style={styles.kcalRow}>
              <Text style={styles.kcalValue}>{todayLog.kcal}</Text>
              <Text style={styles.kcalTarget}> / {TARGETS.kcal} kcal</Text>
            </View>
            <View style={{ marginTop: SPACING.md }}>
              <MacroBar label="Protéines" value={todayLog.proteinsG} target={TARGETS.proteins} kind="proteins" />
              <MacroBar label="Glucides" value={todayLog.carbsG} target={TARGETS.carbs} kind="carbs" />
              <MacroBar label="Lipides" value={todayLog.fatsG} target={TARGETS.fats} kind="fats" />
            </View>
          </View>
        )}

        {/* Form */}
        <View style={styles.section}>
          <Text style={styles.sectionTag}>{todayLog ? 'MODIFIER' : 'SAISIR LES MACROS'}</Text>

          {[
            { key: 'proteinsG', label: 'Protéines', unit: 'g', target: TARGETS.proteins },
            { key: 'carbsG', label: 'Glucides', unit: 'g', target: TARGETS.carbs },
            { key: 'fatsG', label: 'Lipides', unit: 'g', target: TARGETS.fats },
            { key: 'kcal', label: 'Calories', unit: 'kcal', target: TARGETS.kcal, hint: 'auto-calculé' },
          ].map((field) => (
            <View key={field.key} style={styles.field}>
              <View style={styles.fieldHeader}>
                <Text style={styles.fieldLabel}>{field.label.toUpperCase()}</Text>
                <Text style={styles.fieldTarget}>cible {field.target}{field.unit}</Text>
              </View>
              <View style={styles.fieldRow}>
                <TextInput
                  style={styles.fieldInput}
                  placeholder="0"
                  placeholderTextColor={COLORS.muted}
                  value={form[field.key]}
                  onChangeText={(v) => setForm((prev) => ({ ...prev, [field.key]: v }))}
                  keyboardType="numeric"
                  editable={!submitting}
                />
                <Text style={styles.fieldUnit}>{field.unit}</Text>
              </View>
              {field.hint && <Text style={styles.fieldHint}>{field.hint}</Text>}
            </View>
          ))}

          <TouchableOpacity
            style={[styles.submitBtn, submitting && styles.btnDisabled]}
            onPress={handleSubmit}
            disabled={submitting}
          >
            <Text style={styles.submitBtnText}>
              {submitting ? '...' : todayLog ? 'METTRE À JOUR' : 'ENREGISTRER'}
            </Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.black },
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
  kcalRow: { flexDirection: 'row', alignItems: 'baseline' },
  kcalValue: { color: COLORS.primary, fontSize: SIZES.font_xl, fontWeight: '900' },
  kcalTarget: { color: COLORS.muted, fontSize: SIZES.font_md },
  field: { marginBottom: SPACING.md },
  fieldHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  fieldLabel: { color: COLORS.white, fontSize: SIZES.font_sm, letterSpacing: 1, fontWeight: 'bold' },
  fieldTarget: { color: COLORS.muted, fontSize: 11 },
  fieldRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.grey,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  fieldInput: {
    flex: 1,
    color: COLORS.white,
    padding: SPACING.md,
    fontSize: SIZES.font_md,
    fontWeight: 'bold',
  },
  fieldUnit: { color: COLORS.muted, paddingHorizontal: SPACING.md },
  fieldHint: { color: COLORS.muted, fontSize: 10, fontStyle: 'italic', marginTop: 4 },
  submitBtn: {
    backgroundColor: COLORS.primary,
    padding: SPACING.md,
    alignItems: 'center',
    marginTop: SPACING.md,
  },
  btnDisabled: { opacity: 0.5 },
  submitBtnText: { color: COLORS.white, fontWeight: '900', letterSpacing: 2 },
});
