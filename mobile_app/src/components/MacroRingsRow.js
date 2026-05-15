/**
 * MacroRingsRow — ligne de 3 mini-rings pour Protéines / Glucides / Lipides.
 * Style Samsung Health : petit ring + label + valeur sous le ring.
 */
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import ActivityRing from './ActivityRing';
import { COLORS, FONT, SPACING, RADIUS, opacity } from '../theme/tokens';

const MACROS = [
  { key: 'proteins', label: 'Protéines', unit: 'g', color: COLORS.protein },
  { key: 'carbs',    label: 'Glucides',  unit: 'g', color: COLORS.carbs   },
  { key: 'fats',     label: 'Lipides',   unit: 'g', color: COLORS.fat     },
];

/**
 * @param {{ actual: {proteins, carbs, fats}, targets: {proteins, carbs, fats} }} props
 */
export default function MacroRingsRow({ actual = {}, targets = {} }) {
  return (
    <View style={styles.row}>
      {MACROS.map(({ key, label, unit, color }) => {
        const val = actual[key] ?? 0;
        const target = targets[key] ?? 1;
        const progress = Math.min(val / target, 1);
        const pct = Math.round(progress * 100);

        return (
          <View key={key} style={styles.item}>
            <ActivityRing
              size={72}
              progress={progress}
              color={color}
              trackColor={opacity(color, 0.15)}
              strokeWidth={7}
            >
              <Text style={[styles.pct, { color }]}>{pct}%</Text>
            </ActivityRing>
            <Text style={styles.value}>
              {val}<Text style={styles.unit}>{unit}</Text>
            </Text>
            <Text style={styles.label}>{label}</Text>
            <Text style={styles.target}>/ {target}{unit}</Text>
          </View>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    alignItems: 'flex-start',
    paddingVertical: SPACING.md,
  },
  item: {
    alignItems: 'center',
    flex: 1,
  },
  pct: {
    fontSize: FONT.xs,
    fontWeight: '700',
  },
  value: {
    color: COLORS.text,
    fontSize: FONT.md,
    fontWeight: '700',
    marginTop: SPACING.sm,
  },
  unit: {
    fontSize: FONT.xs,
    color: COLORS.textSub,
    fontWeight: '400',
  },
  label: {
    color: COLORS.textSub,
    fontSize: FONT.xs,
    marginTop: 2,
  },
  target: {
    color: COLORS.textMuted,
    fontSize: FONT.xs,
  },
});
