import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Image,
  RefreshControl,
  Alert,
} from 'react-native';
import { Card, Title, Paragraph, Button, FAB } from 'react-native-paper';
import Icon from 'react-native-vector-icons/MaterialIcons';
import LinearGradient from 'react-native-linear-gradient';
import { useAnalytics } from '../hooks/useAnalytics';

const HomeScreen = ({ navigation }) => {
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const { trackEvent } = useAnalytics();

  useEffect(() => {
    fetchEvents();
  }, []);

  const fetchEvents = async () => {
    try {
      setLoading(true);
      const response = await fetch('http://localhost:8000/api/events');
      const data = await response.json();
      setEvents(data.events || []);
      trackEvent('mobile_events_loaded', { count: data.events?.length || 0 });
    } catch (error) {
      console.error('Errore caricamento eventi:', error);
      Alert.alert('Errore', 'Impossibile caricare gli eventi');
    } finally {
      setLoading(false);
    }
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await fetchEvents();
    setRefreshing(false);
  };

  const handleEventPress = (event) => {
    trackEvent('mobile_event_viewed', { event_id: event.id });
    navigation.navigate('EventDetail', { event });
  };

  const handleRegisterPress = (event) => {
    trackEvent('mobile_registration_started', { event_id: event.id });
    navigation.navigate('Registration', { event });
  };

  const handleAdminPress = () => {
    trackEvent('mobile_admin_access');
    navigation.navigate('Admin');
  };

  return (
    <View style={styles.container}>
      <ScrollView
        style={styles.scrollView}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
      >
        {/* Header */}
        <LinearGradient
          colors={['#8B5CF6', '#A855F7']}
          style={styles.header}
        >
          <Text style={styles.headerTitle}>Opium Club</Text>
          <Text style={styles.headerSubtitle}>Pordenone</Text>
        </LinearGradient>

        {/* Quick Actions */}
        <View style={styles.quickActions}>
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => navigation.navigate('Scanner')}
          >
            <Icon name="qr-code-scanner" size={24} color="#8B5CF6" />
            <Text style={styles.quickActionText}>Scanner QR</Text>
          </TouchableOpacity>
          
          <TouchableOpacity
            style={styles.quickAction}
            onPress={() => navigation.navigate('Events')}
          >
            <Icon name="event" size={24} color="#8B5CF6" />
            <Text style={styles.quickActionText}>Eventi</Text>
          </TouchableOpacity>
          
          <TouchableOpacity
            style={styles.quickAction}
            onPress={handleAdminPress}
          >
            <Icon name="admin-panel-settings" size={24} color="#8B5CF6" />
            <Text style={styles.quickActionText}>Admin</Text>
          </TouchableOpacity>
        </View>

        {/* Events List */}
        <View style={styles.eventsSection}>
          <Text style={styles.sectionTitle}>Eventi Recenti</Text>
          
          {loading ? (
            <View style={styles.loadingContainer}>
              <Text>Caricamento eventi...</Text>
            </View>
          ) : events.length === 0 ? (
            <Card style={styles.emptyCard}>
              <Card.Content>
                <Text style={styles.emptyText}>
                  Nessun evento disponibile al momento
                </Text>
              </Card.Content>
            </Card>
          ) : (
            events.slice(0, 3).map((event) => (
              <Card key={event.id} style={styles.eventCard}>
                <Card.Content>
                  <Title>{event.name}</Title>
                  <Paragraph>{event.description}</Paragraph>
                  <View style={styles.eventInfo}>
                    <View style={styles.eventInfoItem}>
                      <Icon name="calendar-today" size={16} color="#666" />
                      <Text style={styles.eventInfoText}>
                        {new Date(event.date).toLocaleDateString('it-IT')}
                      </Text>
                    </View>
                    <View style={styles.eventInfoItem}>
                      <Icon name="access-time" size={16} color="#666" />
                      <Text style={styles.eventInfoText}>{event.time}</Text>
                    </View>
                  </View>
                </Card.Content>
                <Card.Actions>
                  <Button
                    mode="outlined"
                    onPress={() => handleEventPress(event)}
                  >
                    Dettagli
                  </Button>
                  <Button
                    mode="contained"
                    onPress={() => handleRegisterPress(event)}
                  >
                    Registrati
                  </Button>
                </Card.Actions>
              </Card>
            ))
          )}
        </View>
      </ScrollView>

      {/* Floating Action Button */}
      <FAB
        style={styles.fab}
        icon="add"
        onPress={() => navigation.navigate('Events')}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F9FAFB',
  },
  scrollView: {
    flex: 1,
  },
  header: {
    padding: 20,
    paddingTop: 40,
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 28,
    fontWeight: 'bold',
    color: 'white',
  },
  headerSubtitle: {
    fontSize: 16,
    color: 'white',
    opacity: 0.9,
  },
  quickActions: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    padding: 20,
    backgroundColor: 'white',
    marginTop: -10,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
  },
  quickAction: {
    alignItems: 'center',
    padding: 10,
  },
  quickActionText: {
    marginTop: 5,
    fontSize: 12,
    color: '#666',
  },
  eventsSection: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 15,
    color: '#1F2937',
  },
  loadingContainer: {
    alignItems: 'center',
    padding: 20,
  },
  emptyCard: {
    marginBottom: 10,
  },
  emptyText: {
    textAlign: 'center',
    color: '#666',
  },
  eventCard: {
    marginBottom: 15,
    elevation: 2,
  },
  eventInfo: {
    flexDirection: 'row',
    marginTop: 10,
  },
  eventInfoItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginRight: 15,
  },
  eventInfoText: {
    marginLeft: 5,
    fontSize: 12,
    color: '#666',
  },
  fab: {
    position: 'absolute',
    margin: 16,
    right: 0,
    bottom: 0,
    backgroundColor: '#8B5CF6',
  },
});

export default HomeScreen;
