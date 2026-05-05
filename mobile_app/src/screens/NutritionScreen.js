import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { COLORS } from '../theme/tokens';

export default function NutritionScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.text}>Suivi Nutrition</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: COLORS.black, justifyContent: 'center', alignItems: 'center' },
  text: { color: COLORS.white }
});
