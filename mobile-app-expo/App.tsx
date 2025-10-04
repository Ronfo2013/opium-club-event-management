import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createStackNavigator } from '@react-navigation/stack';
import { Provider as PaperProvider } from 'react-native-paper';
import { StatusBar } from 'expo-status-bar';

// Screens
import HomeScreen from './src/screens/HomeScreen';
import EventsScreen from './src/screens/EventsScreen';
import RegistrationScreen from './src/screens/RegistrationScreen';
import QRScannerScreen from './src/screens/QRScannerScreen';

const Stack = createStackNavigator();

const theme = {
  colors: {
    primary: '#6f42c1',
    accent: '#6f42c1',
    background: '#f8f9fa',
    surface: '#ffffff',
    text: '#343a40',
    placeholder: '#6c757d',
  },
};

export default function App() {
  return (
    <PaperProvider theme={theme}>
      <NavigationContainer>
        <StatusBar style="auto" />
        <Stack.Navigator
          initialRouteName="Home"
          screenOptions={{
            headerStyle: {
              backgroundColor: '#6f42c1',
            },
            headerTintColor: '#fff',
            headerTitleStyle: {
              fontWeight: 'bold',
            },
          }}
        >
          <Stack.Screen 
            name="Home" 
            component={HomeScreen} 
            options={{ title: '🎉 Opium Club' }}
          />
          <Stack.Screen 
            name="Events" 
            component={EventsScreen} 
            options={{ title: '📅 Eventi' }}
          />
          <Stack.Screen 
            name="Registration" 
            component={RegistrationScreen} 
            options={{ title: '📝 Registrazione' }}
          />
          <Stack.Screen 
            name="QRScanner" 
            component={QRScannerScreen} 
            options={{ title: '📱 Scanner QR' }}
          />
        </Stack.Navigator>
      </NavigationContainer>
    </PaperProvider>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  content: {
    padding: 20,
  },
  header: {
    alignItems: 'center',
    marginBottom: 30,
    paddingTop: 20,
  },
  title: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#6f42c1',
    marginBottom: 5,
  },
  subtitle: {
    fontSize: 20,
    color: '#6c757d',
    fontWeight: '600',
  },
  section: {
    backgroundColor: '#fff',
    padding: 20,
    borderRadius: 12,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#343a40',
    marginBottom: 10,
  },
  description: {
    fontSize: 16,
    color: '#6c757d',
    lineHeight: 24,
  },
  feature: {
    fontSize: 16,
    color: '#495057',
    marginBottom: 8,
    paddingLeft: 10,
  },
  footer: {
    alignItems: 'center',
    marginTop: 20,
    paddingBottom: 20,
  },
  footerText: {
    fontSize: 14,
    color: '#adb5bd',
    fontStyle: 'italic',
  },
});
