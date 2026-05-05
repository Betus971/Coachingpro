import React, { useState } from 'react';
import { 
  StyleSheet, 
  View, 
  Text, 
  TextInput, 
  TouchableOpacity, 
  KeyboardAvoidingView, 
  Platform,
  ActivityIndicator
} from 'react-native';
import { useAuth } from '../context/AuthContext';
import { COLORS, SPACING, SIZES } from '../theme/tokens';

export default function LoginScreen() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const { login } = useAuth();

  const handleLogin = async () => {
    if (!email || !password) return;
    setError('');
    setLoading(true);
    const result = await login(email, password);
    setLoading(false);
    if (!result.success) {
      setError(result.error);
    }
  };

  return (
    <KeyboardAvoidingView 
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={styles.container}
    >
      <View style={styles.content}>
        <View style={styles.header}>
          <Text style={styles.logoPrimary}>COACH</Text>
          <Text style={styles.logoSecondary}>PRO</Text>
          <Text style={styles.subtitle}>PROGRAMME 121 → 95 KG</Text>
        </View>

        <View style={styles.form}>
          {error ? <Text style={styles.errorText}>{error}</Text> : null}
          
          <View style={styles.inputGroup}>
            <Text style={styles.label}>EMAIL</Text>
            <TextInput
              style={styles.input}
              placeholder="leo@example.com"
              placeholderTextColor={COLORS.muted}
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoCapitalize="none"
            />
          </View>

          <View style={styles.inputGroup}>
            <Text style={styles.label}>MOT DE PASSE</Text>
            <TextInput
              style={styles.input}
              placeholder="••••••••"
              placeholderTextColor={COLORS.muted}
              value={password}
              onChangeText={setPassword}
              secureTextEntry
            />
          </View>

          <TouchableOpacity 
            style={styles.button} 
            onPress={handleLogin}
            disabled={loading}
          >
            {loading ? (
              <ActivityIndicator color={COLORS.white} />
            ) : (
              <Text style={styles.buttonText}>CONNEXION</Text>
            )}
          </TouchableOpacity>

          <TouchableOpacity style={styles.linkButton}>
            <Text style={styles.linkText}>Créer un compte</Text>
          </TouchableOpacity>
        </View>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.black,
  },
  content: {
    flex: 1,
    justifyContent: 'center',
    padding: SPACING.xl,
  },
  header: {
    alignItems: 'center',
    marginBottom: SPACING.xl * 2,
  },
  logoPrimary: {
    color: COLORS.primary,
    fontSize: SIZES.font_hero,
    fontWeight: '900',
    letterSpacing: 2,
  },
  logoSecondary: {
    color: COLORS.white,
    fontSize: SIZES.font_hero,
    fontWeight: '900',
    letterSpacing: 2,
    marginTop: -10,
  },
  subtitle: {
    color: COLORS.muted,
    fontSize: 10,
    letterSpacing: 4,
    marginTop: SPACING.sm,
  },
  form: {
    width: '100%',
  },
  inputGroup: {
    marginBottom: SPACING.lg,
  },
  label: {
    color: COLORS.muted,
    fontSize: 10,
    letterSpacing: 2,
    marginBottom: SPACING.xs,
  },
  input: {
    backgroundColor: COLORS.grey,
    borderWidth: 1,
    borderColor: COLORS.border,
    borderRadius: 0,
    color: COLORS.white,
    padding: SPACING.md,
    fontSize: SIZES.font_md,
  },
  button: {
    backgroundColor: COLORS.primary,
    padding: SPACING.md,
    alignItems: 'center',
    marginTop: SPACING.md,
  },
  buttonText: {
    color: COLORS.white,
    fontSize: SIZES.font_md,
    fontWeight: '900',
    letterSpacing: 2,
  },
  errorText: {
    color: COLORS.error,
    fontSize: SIZES.font_sm,
    marginBottom: SPACING.md,
    textAlign: 'center',
  },
  linkButton: {
    marginTop: SPACING.lg,
    alignItems: 'center',
  },
  linkText: {
    color: COLORS.muted,
    fontSize: SIZES.font_sm,
  }
});
