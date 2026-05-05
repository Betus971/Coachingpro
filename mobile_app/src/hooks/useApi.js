import { useCallback, useEffect, useState } from 'react';

/**
 * Hook minimaliste pour appels API avec loading/error/refetch.
 * Pas de cache (intentionnel — on veut des données fraîches à chaque mount).
 * Si on a besoin de cache plus tard, on remplacera par react-query.
 *
 * Usage :
 *   const { data, loading, error, refetch } = useApi(weightsApi.recent, []);
 */
export const useApi = (fetcher, deps = []) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const run = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const result = await fetcher();
      setData(result);
    } catch (e) {
      setError(e);
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);

  useEffect(() => {
    run();
  }, [run]);

  return { data, loading, error, refetch: run };
};
