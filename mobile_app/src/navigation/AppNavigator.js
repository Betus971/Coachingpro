import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { useAuth } from '../context/AuthContext';
import { COLORS } from '../theme/tokens';
import { LayoutDashboard, Scale, Utensils, User } from 'lucide-react-native';

// Screens
import LoginScreen from '../screens/LoginScreen';
import DashboardScreen from '../screens/DashboardScreen';
import WeightScreen from '../screens/WeightScreen';
import NutritionScreen from '../screens/NutritionScreen';
import ProfileScreen from '../screens/ProfileScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

const AppTabs = () => (
  <Tab.Navigator
    screenOptions={{
      tabBarStyle: {
        backgroundColor: COLORS.card,
        borderTopColor: COLORS.border,
        height: 60,
        paddingBottom: 8,
      },
      tabBarActiveTintColor: COLORS.primary,
      tabBarInactiveTintColor: COLORS.muted,
      headerStyle: {
        backgroundColor: COLORS.black,
        borderBottomColor: COLORS.border,
      },
      headerTitleStyle: {
        color: COLORS.white,
        fontWeight: 'bold',
      },
    }}
  >
    <Tab.Screen 
      name="Dashboard" 
      component={DashboardScreen}
      options={{
        tabBarIcon: ({ color }) => <LayoutDashboard color={color} size={24} />,
      }}
    />
    <Tab.Screen 
      name="Poids" 
      component={WeightScreen}
      options={{
        tabBarIcon: ({ color }) => <Scale color={color} size={24} />,
      }}
    />
    <Tab.Screen 
      name="Nutrition" 
      component={NutritionScreen}
      options={{
        tabBarIcon: ({ color }) => <Utensils color={color} size={24} />,
      }}
    />
    <Tab.Screen 
      name="Profil" 
      component={ProfileScreen}
      options={{
        tabBarIcon: ({ color }) => <User color={color} size={24} />,
      }}
    />
  </Tab.Navigator>
);

export const AppNavigator = () => {
  const { user, loading } = useAuth();

  if (loading) return null; // Ou un écran de splash

  return (
    <NavigationContainer>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {user ? (
          <Stack.Screen name="Main" component={AppTabs} />
        ) : (
          <Stack.Screen name="Login" component={LoginScreen} />
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
};
