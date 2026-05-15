/**
 * Design System — CoachingPro
 * Inspiré Samsung Health + Google Fit : dark profond, accents colorés, coins arrondis.
 */

// ─── Palette de base ─────────────────────────────────────────────────────────
export const PALETTE = {
  // Backgrounds
  bg0: '#0d0d12',   // fond racine (presque noir bleuté)
  bg1: '#14141c',   // surface principale
  bg2: '#1c1c28',   // surface card
  bg3: '#24243a',   // surface card élevée / hover
  border: '#2a2a3e',
  divider: '#1e1e2e',

  // Texte
  textPrimary: '#eeeef5',
  textSecondary: '#9898b0',
  textMuted: '#5a5a72',

  // Accents
  blue:   '#4f8ef7',   // activité / hydratation (Google Fit)
  teal:   '#00c9a7',   // calories brûlées / séances (Samsung Health)
  purple: '#9b6dff',   // objectif poids / progression
  coral:  '#ff6b6b',   // alerte / écart diète
  yellow: '#ffc947',   // avertissement / macros glucides
  green:  '#3dd68c',   // succès / objectif atteint

  // Macro colors (Samsung Health style)
  macroProt:  '#4f8ef7',  // bleu — protéines
  macroCarbs: '#ffc947',  // jaune — glucides
  macroFat:   '#ff6b6b',  // rouge doux — lipides

  // Tracks (arrière-plan des rings/bars)
  track: '#222235',
  white: '#ffffff',
};

// ─── Sémantique ──────────────────────────────────────────────────────────────
export const COLORS = {
  // Arrière-plans
  background: PALETTE.bg0,
  surface:    PALETTE.bg2,
  surfaceHigh: PALETTE.bg3,
  border:     PALETTE.border,
  divider:    PALETTE.divider,

  // Texte
  text:        PALETTE.textPrimary,
  textSub:     PALETTE.textSecondary,
  textMuted:   PALETTE.textMuted,

  // Accents principaux
  primary:   PALETTE.purple,   // progression / objectif
  activity:  PALETTE.teal,     // séances sport
  energy:    PALETTE.blue,     // calories / énergie
  success:   PALETTE.green,
  warning:   PALETTE.yellow,
  danger:    PALETTE.coral,

  // Macros
  protein:   PALETTE.macroProt,
  carbs:     PALETTE.macroCarbs,
  fat:       PALETTE.macroFat,

  track:     PALETTE.track,
  white:     PALETTE.white,
};

// ─── Espacement ──────────────────────────────────────────────────────────────
export const SPACING = {
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
  xxl: 48,
};

// ─── Typographie ─────────────────────────────────────────────────────────────
export const FONT = {
  xs:   11,
  sm:   13,
  md:   15,
  lg:   18,
  xl:   22,
  xxl:  28,
  hero: 42,
};

// ─── Géométrie ───────────────────────────────────────────────────────────────
export const RADIUS = {
  sm:   8,
  md:   14,
  lg:   20,
  xl:   28,
  full: 999,
};

// ─── Opacité utilitaire ──────────────────────────────────────────────────────
export const opacity = (hex, alpha) => {
  const a = Math.round(Math.max(0, Math.min(1, alpha)) * 255)
    .toString(16)
    .padStart(2, '0');
  return `${hex}${a}`;
};

// ─── Compatibilité anciens imports (SIZES) ───────────────────────────────────
export const SIZES = {
  font_sm:   FONT.sm,
  font_md:   FONT.md,
  font_lg:   FONT.lg,
  font_xl:   FONT.xl,
  font_hero: FONT.hero,
};
