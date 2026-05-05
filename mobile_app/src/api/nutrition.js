import apiClient from './client';
import { unwrapCollection } from './_utils';

const todayIso = () => new Date().toISOString().slice(0, 10); // YYYY-MM-DD

export const nutritionApi = {
  /** Le log d'aujourd'hui (ou null s'il n'existe pas encore). */
  today: async () => {
    const { data } = await apiClient.get('/api/nutrition_logs', {
      params: { loggedOn: todayIso(), itemsPerPage: 1 },
    });
    const list = unwrapCollection(data);
    return list[0] ?? null;
  },

  /** N derniers logs nutrition. */
  recent: async (limit = 7) => {
    const { data } = await apiClient.get('/api/nutrition_logs', {
      params: { 'order[loggedOn]': 'desc', itemsPerPage: limit },
    });
    return unwrapCollection(data);
  },

  /** Crée le log d'aujourd'hui. */
  create: async ({ proteinsG, carbsG, fatsG, kcal, fiberG, waterL, notes }) => {
    const payload = {
      loggedOn: todayIso(),
      proteinsG: Number(proteinsG),
      carbsG: Number(carbsG),
      fatsG: Number(fatsG),
      kcal: Number(kcal),
    };
    if (fiberG != null && fiberG !== '') payload.fiberG = Number(fiberG);
    if (waterL != null && waterL !== '') payload.waterL = String(waterL);
    if (notes) payload.notes = notes;

    const { data } = await apiClient.post('/api/nutrition_logs', payload);
    return data;
  },

  /** Met à jour un log existant (PATCH JSON-LD). */
  update: async (id, fields) => {
    const payload = { ...fields };
    // Normaliser les types
    ['proteinsG', 'carbsG', 'fatsG', 'kcal', 'fiberG'].forEach((k) => {
      if (payload[k] != null) payload[k] = Number(payload[k]);
    });
    if (payload.waterL != null) payload.waterL = String(payload.waterL);

    const { data } = await apiClient.patch(`/api/nutrition_logs/${id}`, payload, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    });
    return data;
  },
};
