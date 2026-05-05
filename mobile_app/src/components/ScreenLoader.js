import React from 'react';
import { View, ActivityIndicator, StyleSheet, Text } from 'react-native';
import { COLORS, SPACING, SIZES } from '../theme/tokens';

export const ScreenLoader = ({ message }) => (
  <View style={styles.wrap}>
    <ActivityIndicator color={COLORS.primary} size="large" />
    {message ? <Text style={styles.msg}>{message}</Text> : null}
  </View>
);

export const ErrorBlock = ({ error, onRetry }) => (
  <View style={styles.wrap}>
    <Text style={styles.errTitle}>Erreur</Text>
    <Text style={styles.err}>
      {error?.response?.data?.detail ||
        error?.response?.data?.message ||
        error?.message ||
        'Impossible de charger les données.'}
    </Text>
    {onRetry ? <Text style={styles.retry} onPress={onRetry}>Réessayer</Text> : null}
  </View>
);

const styles = StyleSheet.create({
  wrap: {
    flex: 1,
    backgroundColor: COLORS.black,
    alignItems: 'center',
    justifyContent: 'center',
    padding: SPACING.xl,
  },
  msg: { color: COLORS.muted, marginTop: SPACING.md, fontSize: SIZES.font_sm },
  errTitle: {
    color: COLORS.error,
    fontWeight: '900',
    fontSize: SIZES.font_md,
    letterSpacing: 2,
    marginBottom: SPACING.sm,
  },
  err: { color: COLORS.muted, textAlign: 'center', fontSize: SIZES.font_sm },
  retry: {
    color: COLORS.primary,
    marginTop: SPACING.lg,
    fontWeight: 'bold',
    letterSpacing: 2,
  },
});
