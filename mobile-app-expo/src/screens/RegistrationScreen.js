import React, { useState } from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import {
  Card,
  Title,
  TextInput,
  Button,
  ActivityIndicator,
  Text,
} from 'react-native-paper';
import { apiService } from '../services/api';

const RegistrationScreen = ({ navigation, route }) => {
  const { event } = route.params;
  const [formData, setFormData] = useState({
    nome: '',
    cognome: '',
    email: '',
    telefono: '',
    evento: event.id,
  });
  const [loading, setLoading] = useState(false);

  const handleInputChange = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value,
    }));
  };

  const validateForm = () => {
    if (!formData.nome.trim()) {
      Alert.alert('Errore', 'Il nome è obbligatorio');
      return false;
    }
    if (!formData.cognome.trim()) {
      Alert.alert('Errore', 'Il cognome è obbligatorio');
      return false;
    }
    if (!formData.email.trim()) {
      Alert.alert('Errore', 'L\'email è obbligatoria');
      return false;
    }
    if (!formData.email.includes('@')) {
      Alert.alert('Errore', 'Inserisci un\'email valida');
      return false;
    }
    return true;
  };

  const handleSubmit = async () => {
    if (!validateForm()) return;

    setLoading(true);
    try {
      const response = await apiService.registerUser(formData);
      
      if (response.success) {
        Alert.alert(
          'Registrazione Completata!',
          'Ti è stata inviata un\'email con il tuo QR code personalizzato.',
          [
            {
              text: 'OK',
              onPress: () => navigation.navigate('Events'),
            },
          ]
        );
      } else {
        Alert.alert('Errore', response.message || 'Errore durante la registrazione');
      }
    } catch (error) {
      console.error('Errore registrazione:', error);
      Alert.alert('Errore', 'Errore di connessione. Riprova più tardi.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView 
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView contentContainerStyle={styles.scrollContainer}>
        <Card style={styles.card}>
          <Card.Content>
            <Title style={styles.title}>📝 Registrazione Evento</Title>
            <Text style={styles.eventTitle}>{event.titolo}</Text>
            <Text style={styles.eventDate}>
              📅 {new Date(event.event_date).toLocaleDateString('it-IT', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
              })}
            </Text>

            <TextInput
              label="Nome *"
              value={formData.nome}
              onChangeText={(text) => handleInputChange('nome', text)}
              style={styles.input}
              mode="outlined"
            />

            <TextInput
              label="Cognome *"
              value={formData.cognome}
              onChangeText={(text) => handleInputChange('cognome', text)}
              style={styles.input}
              mode="outlined"
            />

            <TextInput
              label="Email *"
              value={formData.email}
              onChangeText={(text) => handleInputChange('email', text)}
              style={styles.input}
              mode="outlined"
              keyboardType="email-address"
              autoCapitalize="none"
            />

            <TextInput
              label="Telefono"
              value={formData.telefono}
              onChangeText={(text) => handleInputChange('telefono', text)}
              style={styles.input}
              mode="outlined"
              keyboardType="phone-pad"
            />

            <Button
              mode="contained"
              onPress={handleSubmit}
              style={styles.submitButton}
              loading={loading}
              disabled={loading}
            >
              {loading ? 'Registrazione...' : 'Registrati'}
            </Button>
          </Card.Content>
        </Card>
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  scrollContainer: {
    padding: 16,
  },
  card: {
    elevation: 3,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#6f42c1',
    marginBottom: 16,
    textAlign: 'center',
  },
  eventTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#343a40',
    marginBottom: 8,
    textAlign: 'center',
  },
  eventDate: {
    fontSize: 16,
    color: '#6c757d',
    marginBottom: 24,
    textAlign: 'center',
  },
  input: {
    marginBottom: 16,
  },
  submitButton: {
    marginTop: 16,
    paddingVertical: 8,
  },
});

export default RegistrationScreen;





