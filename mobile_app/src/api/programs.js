import apiClient from './client';
import { unwrapCollection } from './_utils';

export const programsApi = {
  /** Programme actuellement assigné (le premier actif). */
  activeAssignment: async () => {
    const { data } = await apiClient.get('/api/program_assignments', {
      params: { isActive: true, itemsPerPage: 1 },
    });
    const list = unwrapCollection(data);
    return list[0] ?? null;
  },

  /** Récupère un programme avec ses workoutTemplates et exerciseTemplates. */
  get: async (id) => {
    const { data } = await apiClient.get(`/api/programs/${id}`);
    return data;
  },
};

/**
 * Trouve le WorkoutTemplate du jour dans une assignment.
 * dayOfWeek ISO: 1=Lundi ... 7=Dimanche.
 */
export const findTodayWorkout = (assignment) => {
  if (!assignment?.program?.workoutTemplates) return null;
  const today = new Date();
  // getDay(): 0=Dimanche, 1=Lundi ... → mapper à ISO 1-7
  const isoDay = today.getDay() === 0 ? 7 : today.getDay();
  return assignment.program.workoutTemplates.find((t) => t.dayOfWeek === isoDay) ?? null;
};
