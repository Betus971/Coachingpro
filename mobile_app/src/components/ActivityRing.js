/**
 * ActivityRing — anneau de progression circulaire (style Google Fit / Samsung Health).
 * Utilise react-native-svg (dépendance transitive Expo, pas besoin d'install extra).
 *
 * Props :
 *   size         — diamètre total en dp (default 80)
 *   progress     — 0 à 1 (ex: 0.72 = 72 %)
 *   color        — couleur de l'arc actif
 *   trackColor   — couleur de la piste (fond de l'arc)
 *   strokeWidth  — épaisseur du trait (default 8)
 *   children     — contenu centré dans le ring (texte, icône…)
 *   style        — style du wrapper View
 */
import React from 'react';
import { View, StyleSheet } from 'react-native';
import Svg, { Circle } from 'react-native-svg';
import { COLORS } from '../theme/tokens';

export default function ActivityRing({
  size = 80,
  progress = 0,
  color = COLORS.primary,
  trackColor = COLORS.track,
  strokeWidth = 8,
  children,
  style,
}) {
  const clampedProgress = Math.max(0, Math.min(1, progress));

  // Géométrie SVG
  const radius = (size - strokeWidth) / 2;
  const cx = size / 2;
  const cy = size / 2;
  const circumference = 2 * Math.PI * radius;
  const strokeDashoffset = circumference * (1 - clampedProgress);

  return (
    <View style={[{ width: size, height: size }, style]}>
      <Svg width={size} height={size} style={StyleSheet.absoluteFill}>
        {/* Piste (fond) */}
        <Circle
          cx={cx}
          cy={cy}
          r={radius}
          stroke={trackColor}
          strokeWidth={strokeWidth}
          fill="none"
        />
        {/* Arc actif — rotation pour partir du haut */}
        <Circle
          cx={cx}
          cy={cy}
          r={radius}
          stroke={color}
          strokeWidth={strokeWidth}
          fill="none"
          strokeDasharray={`${circumference} ${circumference}`}
          strokeDashoffset={strokeDashoffset}
          strokeLinecap="round"
          rotation="-90"
          origin={`${cx}, ${cy}`}
        />
      </Svg>
      {/* Contenu centré */}
      {children && (
        <View style={styles.center}>
          {children}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  center: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
