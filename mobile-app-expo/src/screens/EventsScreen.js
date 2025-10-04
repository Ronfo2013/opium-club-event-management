import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  Alert,
  RefreshControl,
} from 'react-native';
import { Card, Title, Paragraph, Button, ActivityIndicator } from 'react-native-paper';
import { apiService } from '../services/api';

const EventsScreen = ({ navigation }) => {
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadEvents = async () => {
    try {
      setLoading(true);
      const response = await apiService.getEvents();
      if (response.success) {
        setEvents(response.data || []);
      } else {
        Alert.alert('Errore', response.message || 'Impossibile caricare gli eventi');
      }
    } catch (error) {
      console.error('Errore caricamento eventi:', error);
      Alert.alert('Errore', 'Errore di connessione al server');
    } finally {
      setLoading(false);
    }
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await loadEvents();
    setRefreshing(false);
  };

  useEffect(() => {
    loadEvents();
  }, []);

  const renderEvent = ({ item }) => (
    <Card style={styles.eventCard}>
      <Card.Content>
        <Title>{item.titolo}</Title>
        <Paragraph>
          📅 {new Date(item.event_date).toLocaleDateString('it-IT', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          })}
        </Paragraph>
        {item.descrizione && (
          <Paragraph style={styles.description}>{item.descrizione}</Paragraph>
        )}
        <View style={styles.statusContainer}>
          <Text style={[
            styles.status,
            { color: item.chiuso ? '#dc3545' : '#28a745' }
          ]}>
            {item.chiuso ? '🔒 Evento Chiuso' : '🔓 Iscrizioni Aperte'}
          </Text>
        </View>
      </Card.Content>
      <Card.Actions>
        <Button
          mode="contained"
          onPress={() => navigation.navigate('Registration', { event: item })}
          disabled={item.chiuso}
        >
          Registrati
        </Button>
      </Card.Actions>
    </Card>
  );

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#6f42c1" />
        <Text style={styles.loadingText}>Caricamento eventi...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>📅 Eventi Opium Club</Text>
      
      <FlatList
        data={events}
        renderItem={renderEvent}
        keyExtractor={(item) => item.id.toString()}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Text style={styles.emptyText}>Nessun evento disponibile</Text>
          </View>
        }
        contentContainerStyle={styles.listContainer}
      />
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
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#6f42c1',
    marginBottom: 20,
    textAlign: 'center',
  },
  listContainer: {
    paddingBottom: 20,
  },
  eventCard: {
    marginBottom: 16,
    elevation: 3,
  },
  description: {
    marginTop: 8,
    fontStyle: 'italic',
  },
  statusContainer: {
    marginTop: 12,
    alignItems: 'flex-end',
  },
  status: {
    fontWeight: 'bold',
    fontSize: 14,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: '#6c757d',
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingTop: 50,
  },
  emptyText: {
    fontSize: 18,
    color: '#6c757d',
    textAlign: 'center',
  },
});

export default EventsScreen;





