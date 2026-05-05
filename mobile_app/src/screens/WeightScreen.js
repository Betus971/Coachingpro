import React, { useCallback, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  FlatList,
  Alert,
  RefreshControl,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { COLORS, SPACING, SIZES } from '../theme/tokens';
import { Trash2 } from 'lucide-react-native';
import { weightsApi } from '../api/weights';
import { ScreenLoader, ErrorBlock } from '../components/ScreenLoader';
import { idFromIri } from '../api/_utils';

export default function WeightScreen() {
  const [weights, setWeights] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [refreshing, setRefreshing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [weightInput, setWeightInput] = useState('');

  const load = useCallback(async () => {
    try {
      setError(null);
      const data = await weightsApi.recent(30);
      setWeights(data);
    } catch (e) {
      setError(e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    load();
  }, [load]);

  const handleSubmit = async () => {
    const weight = weightInput.replace(',', '.').trim();
    if (!weight || isNaN(parseFloat(weight))) {
      Alert.alert('Erreur', 'Saisis un poids valide.');
      return;
    }
    const num = parseFloat(weight);
    if (num < 30 || num > 350) {
      Alert.alert('Erreur', 'Poids hors limite (30 – 350 kg).');
      return;
    }

    setSubmitting(true);
    try {
      await weightsApi.create({
        weightKg: num.toFixed(2),
        loggedOn: new Date().toISOString().slice(0, 10),
      });
      setWeightInput('');
      await load();
    } catch (e) {
      const msg = e?.response?.data?.['hydra:description']
        || e?.response?.data?.detail
        || 'Impossible d\'enregistrer la pesée.';
      Alert.alert('Erreur', msg);
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = (log) => {
    Alert.alert('Supprimer', `Supprimer la pesée du ${formatDate(log.loggedOn)} ?`, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Supprimer',
        style: 'destructive',
        onPress: async () => {
          try {
            const id = log.id ?? idFromIri(log['@id']);
            await weightsApi.delete(id);
            await load();
          } catch (e) {
            Alert.alert('Erreur', 'Suppression impossible.');
          }
        },
      },
    ]);
  };

  if (loading) return <ScreenLoader message="Chargement des pesées..." />;
  if (error && weights.length === 0) return <ErrorBlock error={error} onRetry={load} />;

  // Calcul delta vs pesée précédente
  const enrichedWeights = weights.map((w, i) => {
    const next = weights[i + 1]; // suivant dans la liste = pesée précédente dans le temps (desc)
    const delta = next ? parseFloat(w.weightKg) - parseFloat(next.weightKg) : null;
    return { ...w, _delta: delta };
  });

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      style={styles.container}
    >
      {/* Form */}
      <View style={styles.formCard}>
        <Text style={styles.formLabel}>NOUVELLE PESÉE</Text>
        <View style={styles.formRow}>
          <TextInput
            style={styles.input}
            placeholder="113.5"
            placeholderTextColor={COLORS.muted}
            value={weightInput}
            onChangeText={setWeightInput}
            keyboardType="decimal-pad"
            editable={!submitting}
          />
          <Text style={styles.unit}>kg</Text>
          <TouchableOpacity
            style={[styles.btn, submitting && styles.btnDisabled]}
            onPress={handleSubmit}
            disabled={submitting}
          >
            <Text style={styles.btnText}>{submitting ? '...' : 'AJOUTER'}</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Liste */}
      <FlatList
        data={enrichedWeights}
        keyExtractor={(item) => item['@id'] ?? item.id}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={COLORS.primary} />}
        ListEmptyComponent={
          <View style={styles.empty}>
            <Text style={styles.emptyText}>Aucune pesée enregistrée.</Text>
          </View>
        }
        renderItem={({ item }) => (
          <View style={styles.row}>
            <View style={{ flex: 1 }}>
              <Text style={styles.rowDate}>{formatDate(item.loggedOn)}</Text>
              {item.source === 'boditrax_csv' && (
                <Text style={styles.rowSource}>BODITRAX</Text>
              )}
            </View>
            <View style={styles.rowMid}>
              <Text style={styles.rowWeight}>{parseFloat(item.weightKg).toFixed(1)} kg</Text>
              {item._delta != null && (
                <Text style={[
                  styles.rowDelta,
                  { color: item._delta < 0 ? COLORS.secondary : COLORS.error },
                ]}>
                  {item._delta > 0 ? '+' : ''}{item._delta.toFixed(1)} kg
                </Text>
              )}
            </View>
            <TouchableOpacity onPress={() => handleDelete(item)} style={styles.deleteBtn}>
              <Trash2 size={16} color={COLORS.muted} />
            </TouchableOpacity>
          </View>
        )}
      />
    </KeyboardAvoidingView>
  );
}

const formatDate = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleDateString('fr-FR', { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric' });
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.black },
  formCard: {
    backgroundColor: COLORS.card,
    padding: SPACING.lg,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  formLabel: {
    color: COLORS.muted,
    fontSize: 10,
    letterSpacing: 2,
    fontWeight: 'bold',
    marginBottom: SPACING.sm,
  },
  formRow: { flexDirection: 'row', alignItems: 'center' },
  input: {
    backgroundColor: COLORS.grey,
    borderWidth: 1,
    borderColor: COLORS.border,
    color: COLORS.white,
    padding: SPACING.md,
    fontSize: SIZES.font_lg,
    fontWeight: 'bold',
    width: 110,
  },
  unit: { color: COLORS.muted, marginHorizontal: SPACING.sm },
  btn: {
    flex: 1,
    backgroundColor: COLORS.primary,
    padding: SPACING.md,
    alignItems: 'center',
  },
  btnDisabled: { opacity: 0.5 },
  btnText: { color: COLORS.white, fontWeight: '900', letterSpacing: 2 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: SPACING.lg,
    paddingVertical: SPACING.md,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.grey,
  },
  rowDate: { color: COLORS.white, fontSize: SIZES.font_sm, textTransform: 'capitalize' },
  rowSource: { color: COLORS.accent, fontSize: 9, letterSpacing: 1, marginTop: 2 },
  rowMid: { alignItems: 'flex-end', marginRight: SPACING.md },
  rowWeight: { color: COLORS.primary, fontSize: SIZES.font_md, fontWeight: 'bold' },
  rowDelta: { fontSize: SIZES.font_sm, marginTop: 2 },
  deleteBtn: { padding: SPACING.sm },
  empty: { padding: SPACING.xl, alignItems: 'center' },
  emptyText: { color: COLORS.muted, fontStyle: 'italic' },
});
