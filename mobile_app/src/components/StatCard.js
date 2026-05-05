import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { COLORS, SPACING, SIZES } from '../theme/tokens';

export const StatCard = ({ label, value, color = COLORS.primary, suffix }) => (
  <View style={styles.wrap}>
    <Text style={[styles.value, { color }]}>
      {value}
      {suffix ? <Text style={styles.suffix}>{suffix}</Text> : null}
    </Text>
    <Text style={styles.label}>{label}</Text>
  </View>
);

const styles = StyleSheet.create({
  wrap: { marginRight: SPACING.xl },
  value: { fontSize: SIZES.font_xl, fontWeight: '900' },
  suffix: { fontSize: SIZES.font_md, fontWeight: 'normal' },
  label: {
    color: COLORS.muted,
    fontSize: 10,
    letterSpacing: 1,
    marginTop: 4,
  },
});
