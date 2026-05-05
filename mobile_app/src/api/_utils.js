/**
 * API Platform 4 renvoie par défaut du JSON-LD :
 *   { "@context": "...", "@id": "...", "@type": "Collection", "member": [...], "totalItems": N }
 * Cet utilitaire extrait la liste, peu importe que le serveur renvoie JSON-LD ou JSON brut.
 */
export const unwrapCollection = (data) => {
  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.member)) return data.member;        // JSON-LD AP4
  if (Array.isArray(data?.['hydra:member'])) return data['hydra:member']; // ancien Hydra
  return [];
};

/** Extrait l'UUID d'un IRI ApiPlatform : "/api/users/{uuid}" → "{uuid}". */
export const idFromIri = (iri) => {
  if (typeof iri !== 'string') return null;
  const parts = iri.split('/');
  return parts[parts.length - 1] || null;
};
