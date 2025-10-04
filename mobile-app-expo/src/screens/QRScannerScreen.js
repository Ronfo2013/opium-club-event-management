import React, { useState } from 'react';
import {
  View,
  StyleSheet,
  Alert,
  Text,
} from 'react-native';
import { BarCodeScanner } from 'expo-barcode-scanner';
import { Button, Card, Title, ActivityIndicator } from 'react-native-paper';
import { apiService } from '../services/api';

const QRScannerScreen = ({ navigation }) => {
  const [hasPermission, setHasPermission] = useState(null);
  const [scanned, setScanned] = useState(false);
  const [loading, setLoading] = useState(false);

  const askForCameraPermission = async () => {
    const { status } = await BarCodeScanner.requestPermissionsAsync();
    setHasPermission(status === 'granted');
  };

  React.useEffect(() => {
    askForCameraPermission();
  }, []);

  const handleBarCodeScanned = async ({ type, data }) => {
    setScanned(true);
    setLoading(true);

    try {
      const response = await apiService.validateQR(data);
      
      if (response.success) {
        Alert.alert(
          '✅ QR Code Valido!',
          `Benvenuto ${response.user?.nome || 'Utente'}! Accesso autorizzato.`,
          [
            {
              text: 'OK',
              onPress: () => {
                setScanned(false);
                setLoading(false);
              },
            },
          ]
        );
      } else {
        Alert.alert(
          '❌ QR Code Non Valido',
          response.message || 'Questo QR code non è valido o è già stato utilizzato.',
          [
            {
              text: 'Riprova',
              onPress: () => {
                setScanned(false);
                setLoading(false);
              },
            },
          ]
        );
      }
    } catch (error) {
      console.error('Errore validazione QR:', error);
      Alert.alert(
        'Errore',
        'Errore di connessione al server. Riprova più tardi.',
        [
          {
            text: 'OK',
            onPress: () => {
              setScanned(false);
              setLoading(false);
            },
          },
        ]
      );
    }
  };

  if (hasPermission === null) {
    return (
      <View style={styles.centerContainer}>
        <Text style={styles.message}>Richiesta permessi fotocamera...</Text>
      </View>
    );
  }

  if (hasPermission === false) {
    return (
      <View style={styles.centerContainer}>
        <Card style={styles.card}>
          <Card.Content>
            <Title style={styles.title}>📷 Accesso Fotocamera</Title>
            <Text style={styles.message}>
              Per utilizzare lo scanner QR è necessario concedere l'accesso alla fotocamera.
            </Text>
            <Button
              mode="contained"
              onPress={askForCameraPermission}
              style={styles.button}
            >
              Concedi Permessi
            </Button>
          </Card.Content>
        </Card>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Card style={styles.headerCard}>
        <Card.Content>
          <Title style={styles.title}>📱 Scanner QR Code</Title>
          <Text style={styles.subtitle}>
            Inquadra il QR code per validare l'accesso
          </Text>
        </Card.Content>
      </Card>

      <View style={styles.scannerContainer}>
        <BarCodeScanner
          onBarCodeScanned={scanned ? undefined : handleBarCodeScanned}
          style={styles.scanner}
        />
        
        {loading && (
          <View style={styles.loadingOverlay}>
            <ActivityIndicator size="large" color="#6f42c1" />
            <Text style={styles.loadingText}>Validazione in corso...</Text>
          </View>
        )}
      </View>

      <Card style={styles.instructionsCard}>
        <Card.Content>
          <Text style={styles.instructionsTitle}>ℹ️ Istruzioni:</Text>
          <Text style={styles.instructions}>
            • Inquadra il QR code ricevuto via email{'\n'}
            • Mantieni il codice ben visibile{'\n'}
            • Attendere la conferma di validazione
          </Text>
        </Card.Content>
      </Card>

      {scanned && (
        <Button
          mode="contained"
          onPress={() => setScanned(false)}
          style={styles.rescanButton}
        >
          Scansiona di Nuovo
        </Button>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
    padding: 16,
  },
  centerContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    padding: 16,
  },
  card: {
    elevation: 3,
    marginBottom: 16,
  },
  headerCard: {
    elevation: 3,
    marginBottom: 16,
  },
  instructionsCard: {
    elevation: 2,
    marginTop: 16,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#6f42c1',
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 16,
    color: '#6c757d',
    textAlign: 'center',
    marginTop: 8,
  },
  message: {
    fontSize: 16,
    color: '#6c757d',
    textAlign: 'center',
    marginBottom: 20,
  },
  scannerContainer: {
    flex: 1,
    position: 'relative',
  },
  scanner: {
    flex: 1,
    borderRadius: 12,
  },
  loadingOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 12,
  },
  loadingText: {
    color: 'white',
    marginTop: 16,
    fontSize: 16,
  },
  instructionsTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#343a40',
    marginBottom: 8,
  },
  instructions: {
    fontSize: 14,
    color: '#6c757d',
    lineHeight: 20,
  },
  button: {
    marginTop: 16,
  },
  rescanButton: {
    marginTop: 16,
  },
});

export default QRScannerScreen;





