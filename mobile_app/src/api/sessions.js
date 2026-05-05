import apiClient from './client';
import { unwrapCollection } from './_utils';

export const sessionsApi = {
  /** N dernières séances. */
  recent: async (limit = 5) => {
    const { data } = await apiClient.get('/api/workout_sessions', {
      params: { 'order[performedAt]': 'desc', itemsPerPage: limit },
    });
    return unwrapCollection(data);
  },

  /** Récupère une séance complète (avec ses sets). */
  get: async (id) => {
    const { data } = await apiClient.get(`/api/workout_sessions/${id}`);
    return data;
  },

  /** Crée une séance avec ses sets en cascade. */
  create: async ({ name, performedAt, sourceTemplate, durationMinutes, rpe, notes, sets }) => {
    const payload = {
      name,
      performedAt: performedAt ?? new Date().toISOString(),
      sets: (sets ?? []).map((s) => ({
        exercise: s.exercise, // IRI: "/api/exercises/{uuid}"
        exercisePosition: Number(s.exercisePosition ?? 0),
        setNumber: Number(s.setNumber),
        reps: Number(s.reps),
        ...(s.weightKg != null ? { weightKg: String(s.weightKg) } : {}),
        ...(s.rpe != null ? { rpe: Number(s.rpe) } : {}),
        ...(s.isWarmup ? { isWarmup: true } : {}),
      })),
    };
    if (sourceTemplate) payload.sourceTemplate = sourceTemplate;
    if (durationMinutes != null) payload.durationMinutes = Number(durationMinutes);
    if (rpe != null) payload.rpe = Number(rpe);
    if (notes) payload.notes = notes;

    const { data } = await apiClient.post('/api/workout_sessions', payload);
    return data;
  },
};
