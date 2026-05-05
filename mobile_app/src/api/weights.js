import apiClient from './client';
import { unwrapCollection } from './_utils';

export const weightsApi = {
  /** Dernières N pesées (les plus récentes en premier). */
  recent: async (limit = 10) => {
    const { data } = await apiClient.get('/api/weight_logs', {
      params: { 'order[loggedOn]': 'desc', itemsPerPage: limit, page: 1 },
    });
    return unwrapCollection(data);
  },

  /** Pesée la plus ancienne (point de départ). */
  earliest: async () => {
    const { data } = await apiClient.get('/api/weight_logs', {
      params: { 'order[loggedOn]': 'asc', itemsPerPage: 1, page: 1 },
    });
    const list = unwrapCollection(data);
    return list[0] ?? null;
  },

  /** Crée une pesée. weightKg en string (decimal). */
  create: async ({ weightKg, loggedOn, notes, fatPercent, muscleKg }) => {
    const payload = {
      weightKg: String(weightKg),
      loggedOn,
    };
    if (notes) payload.notes = notes;
    if (fatPercent != null) payload.fatPercent = String(fatPercent);
    if (muscleKg != null) payload.muscleKg = String(muscleKg);

    const { data } = await apiClient.post('/api/weight_logs', payload);
    return data;
  },

  delete: async (id) => apiClient.delete(`/api/weight_logs/${id}`),
};
