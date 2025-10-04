import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Alert,
  TouchableOpacity,
  Dimensions,
} from 'react-native';
import { RNCamera } from 'react-native-camera';
import { request, PERMISSIONS, RESULTS } from 'react-native-permissions';
import Icon from 'react-native-vector-icons/MaterialIcons';
import { useAnalytics } from '../hooks/useAnalytics';

const { width, height } = Dimensions.get('window');

const QRScannerScreen = ({ navigation }) => {
  const [hasPermission, setHasPermission] = useState(null);
  const [scanned, setScanned] = useState(false);
  const [flashOn, setFlashOn] = useState(false);
  const { trackQRCodeScan } = useAnalytics();

  useEffect(() => {
    requestCameraPermission();
  }, []);

  const requestCameraPermission = async () => {
    try {
      const result = await request(PERMISSIONS.ANDROID.CAMERA);
      setHasPermission(result === RESULTS.GRANTED);
    } catch (error) {
      console.error('Errore richiesta permessi camera:', error);
      setHasPermission(false);
    }
  };

  const handleBarCodeScanned = ({ type, data }) => {
    if (scanned) return;
    
    setScanned(true);
    trackQRCodeScan(data);
    
    // Processa il QR code
    processQRCode(data);
  };

  const processQRCode = (data) => {
    try {
      // Prova a parsare come JSON
      const qrData = JSON.parse(data);
      
      if (qrData.type === 'event') {
        // QR code di un evento
        Alert.alert(
          'Evento Trovato',
          `Evento: ${qrData.event_name}\nData: ${qrData.date}`,
          [
            { text: 'Annulla', onPress: () => setScanned(false) },
            { 
              text: 'Visualizza', 
              onPress: () => {
                navigation.navigate('EventDetail', { eventId: qrData.event_id });
                setScanned(false);
              }
            }
          ]
        );
      } else if (qrData.type === 'registration') {
        // QR code di registrazione
        Alert.alert(
          'Registrazione',
          'QR code di registrazione rilevato',
          [
            { text: 'Annulla', onPress: () => setScanned(false) },
            { 
              text: 'Conferma', 
              onPress: () => {
                confirmRegistration(qrData);
                setScanned(false);
              }
            }
          ]
        );
      } else {
        // QR code generico
        Alert.alert(
          'QR Code Scansionato',
          data,
          [
            { text: 'OK', onPress: () => setScanned(false) }
          ]
        );
      }
    } catch (error) {
      // QR code non è JSON, tratta come testo semplice
      Alert.alert(
        'QR Code Scansionato',
        data,
        [
          { text: 'OK', onPress: () => setScanned(false) }
        ]
      );
    }
  };

  const confirmRegistration = async (qrData) => {
    try {
      const response = await fetch('http://localhost:8000/api/confirm-registration', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          qr_code: qrData,
          timestamp: new Date().toISOString()
        })
      });

      const result = await response.json();
      
      if (result.success) {
        Alert.alert('Successo', 'Registrazione confermata!');
      } else {
        Alert.alert('Errore', result.message || 'Errore durante la conferma');
      }
    } catch (error) {
      console.error('Errore conferma registrazione:', error);
      Alert.alert('Errore', 'Impossibile confermare la registrazione');
    }
  };

  const toggleFlash = () => {
    setFlashOn(!flashOn);
  };

  if (hasPermission === null) {
    return (
      <View style={styles.container}>
        <Text style={styles.message}>Richiesta permessi camera...</Text>
      </View>
    );
  }

  if (hasPermission === false) {
    return (
      <View style={styles.container}>
        <Icon name="camera-alt" size={64} color="#666" />
        <Text style={styles.message}>Permessi camera non concessi</Text>
        <TouchableOpacity
          style={styles.button}
          onPress={requestCameraPermission}
        >
          <Text style={styles.buttonText}>Riprova</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <RNCamera
        style={styles.camera}
        type={RNCamera.Constants.Type.back}
        flashMode={flashOn ? RNCamera.Constants.FlashMode.torch : RNCamera.Constants.FlashMode.off}
        onBarCodeRead={handleBarCodeScanned}
        barCodeTypes={[RNCamera.Constants.BarCodeType.qr]}
        captureAudio={false}
      >
        <View style={styles.overlay}>
          {/* Header */}
          <View style={styles.header}>
            <TouchableOpacity
              style={styles.headerButton}
              onPress={() => navigation.goBack()}
            >
              <Icon name="arrow-back" size={24} color="white" />
            </TouchableOpacity>
            <Text style={styles.headerTitle}>Scanner QR</Text>
            <TouchableOpacity
              style={styles.headerButton}
              onPress={toggleFlash}
            >
              <Icon 
                name={flashOn ? "flash-on" : "flash-off"} 
                size={24} 
                color="white" 
              />
            </TouchableOpacity>
          </View>

          {/* Scanner Area */}
          <View style={styles.scannerArea}>
            <View style={styles.scannerFrame}>
              <View style={[styles.corner, styles.topLeft]} />
              <View style={[styles.corner, styles.topRight]} />
              <View style={[styles.corner, styles.bottomLeft]} />
              <View style={[styles.corner, styles.bottomRight]} />
            </View>
          </View>

          {/* Instructions */}
          <View style={styles.instructions}>
            <Text style={styles.instructionText}>
              Inquadra il QR code nell'area evidenziata
            </Text>
            {scanned && (
              <Text style={styles.scannedText}>
                QR code scansionato! Elaborazione in corso...
              </Text>
            )}
          </View>

          {/* Bottom Actions */}
          <View style={styles.bottomActions}>
            <TouchableOpacity
              style={styles.actionButton}
              onPress={() => setScanned(false)}
            >
              <Icon name="refresh" size={24} color="white" />
              <Text style={styles.actionButtonText}>Riprova</Text>
            </TouchableOpacity>
          </View>
        </View>
      </RNCamera>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: 'black',
  },
  camera: {
    flex: 1,
  },
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    paddingTop: 40,
  },
  headerButton: {
    padding: 10,
  },
  headerTitle: {
    color: 'white',
    fontSize: 18,
    fontWeight: 'bold',
  },
  scannerArea: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scannerFrame: {
    width: width * 0.7,
    height: width * 0.7,
    position: 'relative',
  },
  corner: {
    position: 'absolute',
    width: 30,
    height: 30,
    borderColor: '#8B5CF6',
    borderWidth: 3,
  },
  topLeft: {
    top: 0,
    left: 0,
    borderRightWidth: 0,
    borderBottomWidth: 0,
  },
  topRight: {
    top: 0,
    right: 0,
    borderLeftWidth: 0,
    borderBottomWidth: 0,
  },
  bottomLeft: {
    bottom: 0,
    left: 0,
    borderRightWidth: 0,
    borderTopWidth: 0,
  },
  bottomRight: {
    bottom: 0,
    right: 0,
    borderLeftWidth: 0,
    borderTopWidth: 0,
  },
  instructions: {
    alignItems: 'center',
    padding: 20,
  },
  instructionText: {
    color: 'white',
    fontSize: 16,
    textAlign: 'center',
  },
  scannedText: {
    color: '#8B5CF6',
    fontSize: 14,
    textAlign: 'center',
    marginTop: 10,
  },
  bottomActions: {
    flexDirection: 'row',
    justifyContent: 'center',
    padding: 20,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(139, 92, 246, 0.8)',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 25,
  },
  actionButtonText: {
    color: 'white',
    marginLeft: 5,
    fontSize: 16,
  },
  message: {
    fontSize: 18,
    textAlign: 'center',
    color: '#666',
    marginTop: 20,
  },
  button: {
    backgroundColor: '#8B5CF6',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 5,
    marginTop: 20,
    alignSelf: 'center',
  },
  buttonText: {
    color: 'white',
    fontSize: 16,
  },
});

export default QRScannerScreen;
