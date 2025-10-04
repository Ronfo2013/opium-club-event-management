import React from 'react';
import {
  View,
  StyleSheet,
  ScrollView,
} from 'react-native';
import {
  Card,
  Title,
  Button,
  Text,
  Paragraph,
} from 'react-native-paper';

const HomeScreen = ({ navigation }) => {
  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <Title style={styles.title}>🎉 Opium Club</Title>
        <Text style={styles.subtitle}>Pordenone</Text>
        <Paragraph style={styles.description}>
          Benvenuto nell'app mobile di Opium Club Pordenone!
        </Paragraph>
      </View>

      <Card style={styles.card}>
        <Card.Content>
          <Title style={styles.cardTitle}>📅 Eventi</Title>
          <Paragraph style={styles.cardDescription}>
            Scopri tutti gli eventi disponibili e registrati per ricevere il tuo QR code personalizzato.
          </Paragraph>
          <Button
            mode="contained"
            onPress={() => navigation.navigate('Events')}
            style={styles.button}
            icon="calendar"
          >
            Visualizza Eventi
          </Button>
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Content>
          <Title style={styles.cardTitle}>📱 Scanner QR</Title>
          <Paragraph style={styles.cardDescription}>
            Scannerizza il QR code ricevuto via email per validare l'accesso all'evento.
          </Paragraph>
          <Button
            mode="contained"
            onPress={() => navigation.navigate('QRScanner')}
            style={styles.button}
            icon="qrcode-scan"
          >
            Apri Scanner
          </Button>
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Content>
          <Title style={styles.cardTitle}>✨ Funzionalità</Title>
          <View style={styles.featuresList}>
            <Text style={styles.feature}>• 📝 Registrazione eventi</Text>
            <Text style={styles.feature}>• 📧 Ricezione QR code via email</Text>
            <Text style={styles.feature}>• 📱 Scanner QR per accesso</Text>
            <Text style={styles.feature}>• 🔔 Notifiche eventi</Text>
          </View>
        </Card.Content>
      </Card>

      <Card style={styles.card}>
        <Card.Content>
          <Title style={styles.cardTitle}>ℹ️ Informazioni</Title>
          <Paragraph style={styles.cardDescription}>
            Questa app è collegata al sistema web di Opium Club. Tutti i dati sono sincronizzati in tempo reale.
          </Paragraph>
          <View style={styles.infoList}>
            <Text style={styles.info}>🌐 Backend: http://localhost:8000</Text>
            <Text style={styles.info}>📊 Database: MySQL</Text>
            <Text style={styles.info}>🔒 Sicurezza: Connessione sicura</Text>
          </View>
        </Card.Content>
      </Card>

      <View style={styles.footer}>
        <Text style={styles.footerText}>Sviluppato da Benhanced</Text>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  header: {
    alignItems: 'center',
    padding: 20,
    backgroundColor: '#fff',
    marginBottom: 16,
  },
  title: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#6f42c1',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 20,
    color: '#6c757d',
    fontWeight: '600',
    marginBottom: 16,
  },
  description: {
    fontSize: 16,
    color: '#6c757d',
    textAlign: 'center',
    lineHeight: 24,
  },
  card: {
    margin: 16,
    marginTop: 0,
    elevation: 3,
  },
  cardTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#343a40',
    marginBottom: 8,
  },
  cardDescription: {
    fontSize: 16,
    color: '#6c757d',
    marginBottom: 16,
    lineHeight: 22,
  },
  button: {
    marginTop: 8,
  },
  featuresList: {
    marginTop: 8,
  },
  feature: {
    fontSize: 16,
    color: '#495057',
    marginBottom: 8,
    paddingLeft: 8,
  },
  infoList: {
    marginTop: 12,
  },
  info: {
    fontSize: 14,
    color: '#6c757d',
    marginBottom: 4,
  },
  footer: {
    alignItems: 'center',
    padding: 20,
  },
  footerText: {
    fontSize: 14,
    color: '#adb5bd',
    fontStyle: 'italic',
  },
});

export default HomeScreen;





