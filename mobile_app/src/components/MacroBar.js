import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { COLORS, SPACING, SIZES } from '../theme/tokens';

const COLOR_BY_MACRO = {
  proteins: COLORS.primary,
  carbs: COLORS.accent,
  fats: COLORS.secondary,
};

export const MacroBar = ({ label, value, target, kind = 'proteins', unit = 'g' }) => {
  const pct = target > 0 ? Math.min((value / target) * 100, 100) : 0;
  const color = COLOR_BY_MACRO[kind] ?? COLORS.primary;

  return (
    <View style={styles.wrap}>
      <View style={styles.row}>
        <Text style={styles.label}>{label}</Text>
        <Text style={styles.value}>
          {value}{unit} / <Text style={styles.target}>{target}{unit}</Text>
        </Text>
      </View>
      <View style={styles.barBg}>
        <View style={[styles.barFill, { width: `${pct}%`, backgroundColor: color }]} />
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  wrap: { marginBottom: SPACING.md },
  row: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  label: { color: COLORS.muted, fontSize: SIZES.font_sm },
  value: { color: COLORS.white, fontSize: SIZES.font_sm, fontWeight: 'bold' },
  target: { color: COLORS.muted, fontWeight: 'normal' },
  barBg: { height: 4, backgroundColor: COLORS.grey, width: '100%' },
  barFill: { height: 4 },
});
