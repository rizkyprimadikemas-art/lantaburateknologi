#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <WebServer.h>
#include <DNSServer.h>
#include "esp_wifi.h"
#include <vector>

// --- Konfigurasi WiFi ---
const char* ssid = "NAMA_WIFI_ANDA"; 
const char* password = "PASSWORD_WIFI_ANDA"; 

// Konfigurasi Access Point (Mode Setup)
char AP_SSID[32];
const char* AP_PASSWORD = "12345678";
const IPAddress AP_IP(192, 168, 4, 1);
const IPAddress AP_GATEWAY(192, 168, 4, 1);
const IPAddress AP_SUBNET(255, 255, 255, 0);

const String BASE_URL = "http://192.168.1.169/lantaburateknologi/api/"; 
const int RESET_BUTTON_PIN = 20; 

// --- Variabel Global ---
WebServer server(80);
Preferences preferences;
DNSServer dnsServer;

String wifiSSID = "";
String wifiPassword = "";
String storedDeviceId = "";
String storedApiKey = "";

bool setupMode = false;
bool wifiConnected = false;
bool deviceConfigured = false; // Status apakah device_id dan api_key sudah ada
String deviceOnlineStatus = "offline"; // Default status perangkat ke server

// --- Pin dan Sensor ---
#define VOLTAGE_PIN 0 // GPIO 0 (ADC1_CH0)
#define CURRENT_PIN 2 // GPIO 2 (ADC1_CH2)
#define RELAY_PIN 1
const int RELAY_ON = HIGH; 
const int RELAY_OFF = LOW; 

// --- Kontrol Relay ---
const unsigned long RELAY_POLLING_INTERVAL = 1000; // Cek status relay setiap 10 detik
unsigned long lastRelayPollingTime = 0;
bool currentRelayState = false; // true = ON, false = OFF (status internal ESP32)

// --- Kalibrasi Sensor ---
float calibrationFactor = 0.1958; // Diperbarui dari 225V / 1113.67 Raw RMS ADC Value
float sensitivity = 0.185; // Sensitivitas ACS712-05A adalah 185mV/A atau 0.185V/A
float autoZeroCurrent = 0; // Ini akan diisi oleh calibrateZeroCurrent() atau dimuat dari Preferences

// --- Filter & Threshold ---
#define NOISE_CURRENT 0.010 // Arus di bawah ini akan dianggap 0 (10mA)
#define OFF_THRESHOLD 0.015 // Jika mesin ON dan arus turun di bawah ini, dianggap OFF (15mA)
#define ON_THRESHOLD  0.025 // Jika mesin OFF dan arus naik di atas ini, dianggap ON (25mA)

// --- Smoothing ---
float smoothVoltage = 0;
float smoothCurrent = 0;

// --- Energy Calculation ---
float accumulatedEnergy = 0.0; 
unsigned long lastEnergyTime = 0;

// --- Variabel untuk menyimpan energi secara berkala ---
unsigned long lastEnergySaveTime = 0;
const unsigned long ENERGY_SAVE_INTERVAL = 60000; // Simpan energi setiap 60 detik (1 menit)

// --- Debounce untuk status mesin ---
unsigned long lastMachineStateChangeTime = 0;
bool machineStatePendingOff = false; // True jika mesin berpotensi OFF dan sedang dalam periode debounce
const unsigned long MACHINE_OFF_DEBOUNCE_MS = 5000; // 5 detik untuk debounce status OFF

// --- Status Mesin ---
bool isMachineOn = false; // Status ON/OFF perangkat yang terhubung ke relay

// --- Variabel untuk Agregasi Data ---
std::vector<float> voltageBuffer;
std::vector<float> currentBuffer;
std::vector<float> powerBuffer;

unsigned long lastCollectionTime = 0;
const unsigned long COLLECTION_INTERVAL = 1000; // Kumpulkan data setiap 1 detik
const int AGGREGATE_READINGS_COUNT = 60; // Mengirim data setiap 60 detik (60 * 1 detik)

// --- Variabel untuk Penyimpanan Data Offline ---
std::vector<String> offlineDataQueue;
const int MAX_OFFLINE_RECORDS = 100; // Maksimal 100 record data offline di RAM
unsigned long lastWifiCheckTime = 0;
const unsigned long WIFI_RECONNECT_INTERVAL = 15000; // Coba rekoneksi setiap 15 detik

// --- Deklarasi Fungsi ---
String getMacAddress();
void connectToWiFi(const char* ssid, const char* password);
bool fetchDeviceCredentials();
float readVoltage();
float readCurrentRMS();
void calibrateZeroCurrent();
float readCurrent();
void calculateEnergy(float power, unsigned long now);
void collectAndAggregateData();
void handleRoot();
void handleSave();
void handleNotFound();
void startAPMode();
void startNormalMode();
void checkResetButton();
void saveAccumulatedEnergy();
void loadAccumulatedEnergy();
void saveOfflineData(String jsonData);
void sendOfflineData();
void checkAndReconnectWiFi();
void getRelayCommand(); 

String getMacAddress() {
  uint8_t mac[6];
  esp_wifi_get_mac(WIFI_IF_STA, mac);
  char macStr[18];
  sprintf(macStr, "%02X:%02X:%02X:%02X:%02X:%02X", mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
  return String(macStr);
}

void connectToWiFi(const char* ssid, const char* password) {
  WiFi.disconnect(true);
  delay(500);
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);
  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) { 
    delay(500);
    Serial.print(".");
    attempts++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nConnected!");
    Serial.print("IP: "); Serial.println(WiFi.localIP());
    Serial.print("MAC (STA): "); Serial.println(getMacAddress());
    deviceOnlineStatus = "online";
    wifiConnected = true;
  } else {
    Serial.println("\nFailed to connect!");
    deviceOnlineStatus = "offline";
    wifiConnected = false;
  }
}

bool fetchDeviceCredentials() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("fetchDeviceCredentials: WiFi not connected.");
    deviceConfigured = false;
    return false;
  }
  HTTPClient http;
  String url = BASE_URL + "get_device_credentials.php";
  Serial.print("Attempting to fetch credentials from: ");
  Serial.println(url);
  http.begin(url); 
  http.addHeader("Content-Type", "application/json");
  StaticJsonDocument<200> doc;
  doc["mac_address"] = getMacAddress();
  String body;
  serializeJson(doc, body);
  Serial.print("Sending JSON body: ");
  Serial.println(body);
  int httpCode = http.POST(body);
  if (httpCode > 0) {
    Serial.print("HTTP Response code: ");
    Serial.println(httpCode);
    String response = http.getString();
    Serial.print("Server Response: ");
    Serial.println(response);
    StaticJsonDocument<200> res;
    DeserializationError error = deserializeJson(res, response);
    if (error) {
      Serial.print(F("deserializeJson() failed: "));
      Serial.println(error.f_str());
      http.end();
      deviceConfigured = false;
      return false;
    }
    if (res["status"] == "success") {
      storedDeviceId = res["device_id"].as<String>();
      storedApiKey = res["api_key"].as<String>();
      if (storedDeviceId.length() > 0 && storedApiKey.length() > 0) {
        preferences.putString("device_id", storedDeviceId);
        preferences.putString("api_key", storedApiKey);
        Serial.println("Device credentials successfully stored.");
        deviceConfigured = true;
        http.end();
        return true;
      } else {
        Serial.println("Device ID or API Key was empty in server response.");
        http.end();
        deviceConfigured = false;
        return false;
      }
    } else {
      Serial.print("Server response status was not 'success': ");
      Serial.println(res["status"].as<String>());
      http.end();
      deviceConfigured = false;
      return false;
    }
  } else {
    Serial.print("HTTP Request failed: ");
    Serial.println(http.errorToString(httpCode).c_str());
    http.end();
    deviceConfigured = false;
    return false;
  }
}

float readVoltage() {
  const int samples = 400;
  float sum = 0, sumSq = 0;
  for (int i = 0; i < samples; i++) {
    float val = analogRead(VOLTAGE_PIN);
    sum += val;
    delayMicroseconds(150);
  }
  float offset = sum / samples;
  for (int i = 0; i < samples; i++) {
    float val = analogRead(VOLTAGE_PIN);
    float centered = val - offset;
    sumSq += centered * centered;
    delayMicroseconds(150);
  }
  float rms = sqrt(sumSq / samples);

  float voltage = rms * calibrationFactor;
  if (voltage < 30) voltage = 0; 
  return voltage;
}

float readCurrentRMS() {
  const int samples = 800;
  float sum = 0, sumSq = 0;
  for (int i = 0; i < samples; i++) {
    float val = analogRead(CURRENT_PIN);
    sum += val;
    sumSq += val * val;
    delayMicroseconds(80);
  }
  float mean = sum / samples;
  float variance = (sumSq / samples) - (mean * mean);
  if (variance < 0) variance = 0;
  float Vrms_adc = sqrt(variance);
  float Vrms = Vrms_adc * (3.3 / 4095.0); 
  return Vrms / sensitivity; 
}

void calibrateZeroCurrent() {
  Serial.println("Kalibrasi arus cepat... PASTIKAN TIDAK ADA BEBAN TERHUBUNG!");
  float total = 0;
  for (int i = 0; i < 10; i++) {
    total += readCurrentRMS();
    delay(20);
    Serial.print(".");
  }
  autoZeroCurrent = total / 10;
  Serial.println();
  Serial.print("Zero Current (nilai RMS mentah saat kalibrasi): ");
  Serial.println(autoZeroCurrent, 5);
}

float readCurrent() {
  float current = readCurrentRMS();
  current -= autoZeroCurrent;
  if (current < 0) current = 0; 
  return current;
}

void calculateEnergy(float power, unsigned long now) {
  if (!isMachineOn) {
    lastEnergyTime = now; 
    return;
  }
  // Hanya akumulasi jika mesin ON
  float deltaTimeSeconds = (now - lastEnergyTime) / 1000.0; // Konversi ms ke detik
  accumulatedEnergy += (power * deltaTimeSeconds) / 3600.0; // (Watt * detik) / 3600 = Watt-hour
  lastEnergyTime = now;
}

// Fungsi untuk menyimpan accumulatedEnergy
void saveAccumulatedEnergy() {
  preferences.putFloat("acc_energy", accumulatedEnergy);
  Serial.print("DEBUG: Accumulated Energy saved: ");
  Serial.println(accumulatedEnergy, 4);
}

// Fungsi untuk memuat accumulatedEnergy
void loadAccumulatedEnergy() {
  accumulatedEnergy = preferences.getFloat("acc_energy", 0.0); // Default 0.0 jika belum ada
  Serial.print("DEBUG: Accumulated Energy loaded: ");
  Serial.println(accumulatedEnergy, 4);
}

// Fungsi baru untuk menyimpan data ke antrean offline
void saveOfflineData(String jsonData) {
  if (offlineDataQueue.size() < MAX_OFFLINE_RECORDS) {
    offlineDataQueue.push_back(jsonData);
    Serial.print("DEBUG: Data stored offline. Queue size: ");
    Serial.println(offlineDataQueue.size());
  } else {
    Serial.println("WARNING: Offline data queue is full. Discarding oldest data.");
    offlineDataQueue.erase(offlineDataQueue.begin()); // Hapus data tertua
    offlineDataQueue.push_back(jsonData); // Tambahkan data baru
  }
}

// Fungsi baru untuk mengirim data dari antrean offline
void sendOfflineData() {
  if (!wifiConnected) {
    Serial.println("DEBUG: Cannot send offline data, WiFi not connected.");
    return;
  }
  if (offlineDataQueue.empty()) {
    Serial.println("DEBUG: Offline data queue is empty. Nothing to send.");
    return;
  }
  if (!deviceConfigured) {
    Serial.println("WARNING: Device not configured. Cannot send offline data.");
    return;
  }

  Serial.print("DEBUG: Attempting to send ");
  Serial.print(offlineDataQueue.size());
  Serial.println(" stored offline records.");

  std::vector<String> failedToSend; // Untuk menyimpan item yang gagal dikirim

  // Kirim data satu per satu dari antrean
  for (const String& jsonData : offlineDataQueue) {
    HTTPClient http;
    http.begin(BASE_URL + "receive_data.php"); 
    http.addHeader("Content-Type", "application/json");

    Serial.print("Sending stored JSON: ");
    Serial.println(jsonData);

    int httpCode = http.POST(jsonData);

    if (httpCode > 0) {
      Serial.print("HTTP (Offline): "); Serial.println(httpCode);
      if (httpCode != HTTP_CODE_OK && httpCode != HTTP_CODE_CREATED) { // Jika server tidak mengembalikan kode sukses
         Serial.println("WARNING: Server did not return success for offline data.");
         failedToSend.push_back(jsonData); // Simpan untuk dicoba lagi
      } else {
        Serial.println("Offline data sent successfully.");
      }
    } else {
      Serial.print("HTTP ERR (Offline): "); Serial.println(http.errorToString(httpCode).c_str());
      failedToSend.push_back(jsonData); // Simpan jika gagal koneksi/kirim
    }
    http.end();
    delay(100); // Penundaan kecil antar permintaan untuk stabilitas
  }
  offlineDataQueue.clear(); // Hapus semua setelah mencoba mengirim
  // Jika ada yang gagal, tambahkan kembali ke antrean
  for (const String& failedJson : failedToSend) {
      offlineDataQueue.push_back(failedJson);
  }
  if (!failedToSend.empty()) {
      Serial.print("WARNING: Failed to send ");
      Serial.print(failedToSend.size());
      Serial.println(" offline records. They remain in the queue.");
  } else {
      Serial.println("DEBUG: All offline records successfully sent.");
  }
}

// Fungsi baru untuk memeriksa dan mencoba rekoneksi WiFi
void checkAndReconnectWiFi() {
  if (WiFi.status() != WL_CONNECTED) {
    if (wifiConnected) { // Baru saja kehilangan koneksi
      Serial.println("WiFi connection lost!");
      wifiConnected = false;
      deviceOnlineStatus = "offline"; // Perbarui status perangkat
    }

    if (millis() - lastWifiCheckTime >= WIFI_RECONNECT_INTERVAL) {
      Serial.println("Attempting to reconnect to WiFi...");
      WiFi.disconnect(); // Pastikan mulai dari kondisi bersih
      WiFi.reconnect(); // Gunakan reconnect untuk kredensial yang sudah ada
      lastWifiCheckTime = millis();

      int attempts = 0;
      while (WiFi.status() != WL_CONNECTED && attempts < 10) { // Coba selama 10 detik
        delay(1000);
        Serial.print(".");
        attempts++;
      }

      if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\nWiFi reconnected!");
        wifiConnected = true;
        deviceOnlineStatus = "online";
        sendOfflineData(); // Kirim data offline yang terkumpul segera setelah terhubung
      } else {
        Serial.println("\nFailed to reconnect to WiFi.");
        deviceOnlineStatus = "offline"; // Masih offline setelah mencoba rekoneksi
      }
    }
  } else {
    if (!wifiConnected) { // Baru saja terhubung kembali (terdeteksi di luar fungsi rekoneksi)
      Serial.println("WiFi reconnected (detected in checkAndReconnectWiFi)!");
      wifiConnected = true;
      deviceOnlineStatus = "online";
      sendOfflineData(); // Kirim data offline yang terkumpul
    }
  }
}

// --- FUNGSI UNTUK MENDAPATKAN PERINTAH RELAY DARI SERVER ---
void getRelayCommand() {
  if (!wifiConnected || !deviceConfigured) {
    return; 
  }

  HTTPClient http;
  String url = BASE_URL + "get_relay_command.php"; 
  http.begin(url); 
  http.addHeader("Content-Type", "application/json");

  StaticJsonDocument<200> reqDoc;
  reqDoc["device_id"] = storedDeviceId;
  reqDoc["api_key"] = storedApiKey;
  reqDoc["current_relay_state"] = currentRelayState ? "on" : "off"; 
  String reqBody;
  serializeJson(reqDoc, reqBody);

  int httpCode = http.POST(reqBody); 

  if (httpCode > 0) {
    if (httpCode == HTTP_CODE_OK) {
      String payload = http.getString();

      StaticJsonDocument<200> resDoc;
      DeserializationError error = deserializeJson(resDoc, payload);

      if (error) {
        Serial.print(F("deserializeJson() failed for relay command: "));
        Serial.println(error.f_str());
      } else {
        String command = resDoc["command"].as<String>();
        if (command == "on") {
          if (!currentRelayState) { // Hanya ubah jika status berbeda
            digitalWrite(RELAY_PIN, RELAY_ON);
            currentRelayState = true;
            Serial.println("RELAY ON by server command.");
          } // else { Serial.println("RELAY already ON."); }
        } else if (command == "off") {
          if (currentRelayState) { // Hanya ubah jika status berbeda
            digitalWrite(RELAY_PIN, RELAY_OFF);
            currentRelayState = false;
            Serial.println("RELAY OFF by server command.");
          } // else { Serial.println("RELAY already OFF."); }
        } else {
        }
      }
    }
  } else {
    Serial.printf("HTTP GET failed for relay command, error: %s\n", http.errorToString(httpCode).c_str());
  }
  http.end();
}

void collectAndAggregateData() {
  unsigned long now = millis();

  float rawV = readVoltage();
  float rawI = readCurrent();

  // Smoothing Voltage
  if (abs(rawV - smoothVoltage) > 30) { 
    smoothVoltage = rawV;
  } else {
    smoothVoltage = 0.8 * rawV + 0.2 * smoothVoltage; // Smoothing
  }

  // Smoothing Current
  if (abs(rawI - smoothCurrent) > 0.05) { // Lompatan besar, langsung update
    smoothCurrent = rawI;
  } else {
    smoothCurrent = 0.5 * rawI + 0.5 * smoothCurrent; // Smoothing
  }

  // Noise Filter
  if (smoothCurrent < NOISE_CURRENT) smoothCurrent = 0;
  if (smoothVoltage < 30) smoothVoltage = 0; 

  // --- MODIFIKASI LOGIKA STATUS MESIN DIMULAI DI SINI ---
  // Prioritaskan status relay untuk menentukan status mesin.
  // Jika relay OFF, maka mesin dianggap OFF.
  if (!currentRelayState) { // Jika relay OFF (currentRelayState adalah false)
    if (isMachineOn) { // Hanya update jika sebelumnya ON
      Serial.println("DEBUG: Relay OFF, forcing machine status to OFF.");
      isMachineOn = false;
      machineStatePendingOff = false; // Hapus status pending off
    }
  } else { // Jika relay ON, gunakan logika berdasarkan arus
    if (isMachineOn) { // Saat ini mesin dianggap ON
      if (smoothCurrent < OFF_THRESHOLD) {
        // Arus di bawah batas OFF, berpotensi mati
        if (!machineStatePendingOff) {
          // Baru pertama kali di bawah batas, mulai hitung waktu debounce
          machineStatePendingOff = true;
          lastMachineStateChangeTime = now;
          // Serial.println("DEBUG: Potensi OFF terdeteksi, memulai timer debounce.");
        } else if (now - lastMachineStateChangeTime >= MACHINE_OFF_DEBOUNCE_MS) {
          // Sudah di bawah batas OFF selama durasi debounce, konfirmasi OFF
          isMachineOn = false;
          machineStatePendingOff = false; // Reset flag
          // Serial.println("DEBUG: Mesin dikonfirmasi OFF setelah debounce.");
        }
      } else {
        // Arus kembali di atas batas OFF sebelum debounce selesai,
        // jadi mesin tidak pernah benar-benar OFF (anomali/flicker)
        if (machineStatePendingOff) {
          // Serial.println("DEBUG: Mesin berkedip ON selama debounce OFF, tetap ON.");
        }
        machineStatePendingOff = false; // Reset flag
      }
    } else { // Saat ini mesin dianggap OFF
      if (smoothCurrent > ON_THRESHOLD) {
        // Arus di atas batas ON, langsung nyalakan (tidak ada debounce untuk ON)
        isMachineOn = true;
        lastEnergyTime = now; // Penting: Mulai hitung waktu saat mesin ON
        machineStatePendingOff = false; // Pastikan flag ini bersih
        // Serial.println("DEBUG: Mesin dinyalakan (ON).");
      }
    }
  }

  // --- Step 3: Calculate instantaneous power and accumulate energy ---
  float instantaneousPower = isMachineOn ? smoothVoltage * smoothCurrent : 0;
  calculateEnergy(instantaneousPower, now); // Ini memperbarui accumulatedEnergy

  // --- Step 4: Buffer the current reading ---
  voltageBuffer.push_back(smoothVoltage);
  currentBuffer.push_back(smoothCurrent);
  powerBuffer.push_back(instantaneousPower);

  // --- Step 5: Check if enough readings are collected for aggregation ---
  if (voltageBuffer.size() >= AGGREGATE_READINGS_COUNT) {
    // Calculate averages
    float avgVoltage = 0;
    for (float v : voltageBuffer) avgVoltage += v;
    avgVoltage /= voltageBuffer.size();

    float avgCurrent = 0;
    for (float i : currentBuffer) avgCurrent += i;
    avgCurrent /= currentBuffer.size();

    float avgPower = 0;
    for (float p : powerBuffer) avgPower += p;
    avgPower /= powerBuffer.size();

    // Hitung daya maksimum dalam interval
    float maxPowerInInterval = 0.0;
    if (!powerBuffer.empty()) {
        maxPowerInInterval = powerBuffer[0]; 
        for (float p : powerBuffer) {
            if (p > maxPowerInInterval) {
                maxPowerInInterval = p;
            }
        }
    }

    float totalEnergyKWH = accumulatedEnergy / 1000.0; // Current cumulative energy in kWh

    // --- DEBUG ---
    Serial.print("Aggregated V: "); Serial.print(avgVoltage, 1);
    Serial.print(" | I: "); Serial.print(avgCurrent, 3);
    Serial.print(" | P: "); Serial.print(avgPower, 1);
    Serial.print(" | Max P: "); Serial.print(maxPowerInInterval, 1); // DEBUG untuk Max Power
    Serial.print(" | E: "); Serial.print(totalEnergyKWH, 4);
    Serial.print(" | Status Mesin: "); Serial.println(isMachineOn ? "ON" : "OFF");
    Serial.print(" | Status Relay: "); Serial.println(currentRelayState ? "ON" : "OFF");


    // --- Step 6: Send aggregated data or store offline ---
    if (!deviceConfigured) {
      Serial.println("WARNING: Device not configured. Cannot send or store sensor data.");
    } else {
      StaticJsonDocument<300> doc;
      doc["device_id"] = storedDeviceId;
      doc["api_key"] = storedApiKey;
      doc["voltage"] = avgVoltage;
      doc["current"] = avgCurrent;
      doc["power"] = avgPower;
      doc["max_power_interval"] = maxPowerInInterval; 
      doc["energy"] = totalEnergyKWH;
      doc["machine_status"] = isMachineOn ? "ON" : "OFF"; 
      doc["device_status"] = deviceOnlineStatus; 
      doc["relay_state"] = currentRelayState ? "on" : "off"; 
      String body;
      serializeJson(doc, body);

      if (wifiConnected) { 
        HTTPClient http;
        http.begin(BASE_URL + "receive_data.php");
        http.addHeader("Content-Type", "application/json");


        int httpCode = http.POST(body);

        if (httpCode > 0) {
        } else {
          Serial.print("HTTP ERR: "); Serial.println(http.errorToString(httpCode).c_str());
          saveOfflineData(body);
        }
        http.end();
      } else {
        Serial.println("WiFi not connected. Storing data offline.");
        saveOfflineData(body);
      }
    }

    // --- Step 7: Clear buffers and reset counts ---
    voltageBuffer.clear();
    currentBuffer.clear();
    powerBuffer.clear();

    // Save accumulatedEnergy periodically
    if (now - lastEnergySaveTime >= ENERGY_SAVE_INTERVAL) {
      saveAccumulatedEnergy();
      lastEnergySaveTime = now;
    }
  }
}

void handleRoot() {
  String html = R"rawliteral(
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurasi WiFi - Lantabura</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 40px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: fadeIn 0.6s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo i {
            font-size: 3rem;
            color: #22d3ee;
            text-shadow: 0 0 20px #22d3ee;
        }
        h1 {
            color: #f1f5f9;
            text-align: center;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        p.subtitle {
            color: #94a3b8;
            text-align: center;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #f1f5f9;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            color: #f1f5f9;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }
        input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 10px rgba(59,130,246,0.3);
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(59,130,246,0.5);
        }
        .info {
            text-align: center;
            margin-top: 20px;
            color: #94a3b8;
            font-size: 0.85rem;
        }
        .info i {
            color: #22d3ee;
            margin-right: 5px;
        }
        #status {
            margin-top: 20px;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
            display: none;
        }
        .success { background: rgba(34,197,94,0.2); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); }
        .error { background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-bolt"></i>
        </div>
        <h1>Konfigurasi WiFi</h1>
        <p class="subtitle">Lantabura Teknologi - Energy Monitoring</p>

        <form action="/save" method="POST" onsubmit="return validateForm()">
            <div class="form-group">
                <label for="ssid"><i class="fas fa-wifi"></i> Nama WiFi (SSID)</label>
                <input type="text" id="ssid" name="ssid" placeholder="Masukkan nama WiFi Anda" required>
            </div>
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password WiFi</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password WiFi">
            </div>
            <button type="submit"><i class="fas fa-check-circle"></i> Simpan & Hubungkan</button>
        </form>

        <div id="status"></div>

        <div class="info">
            <i class="fas fa-info-circle"></i> Perangkat akan restart secara otomatis setelah konfigurasi berhasil.
        </div>
    </div>

    <script>
        function validateForm() {
            var ssid = document.getElementById('ssid').value.trim();
            if (ssid === '') {
                alert('Nama WiFi tidak boleh kosong!');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
)rawliteral";
  server.send(200, "text/html", html);
}

void handleSave() {
  String ssid = server.arg("ssid");
  String password = server.arg("password");
  if (ssid.length() == 0) {
    String errorHtml = R"rawliteral(
      <!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Error</title>
      <style>body{font-family:'Segoe UI',Roboto,Arial,sans-serif;background:#1e293b;color:#f1f5f9;text-align:center;padding:50px;}h1{color:#ef4444;}p{margin-top:20px;}a{color:#3b82f6;}</style>
      </head><body><h1>Gagal Menyimpan Konfigurasi!</h1><p>Nama WiFi tidak boleh kosong. Silakan <a href="/">coba lagi</a>.</p></body></html>
    )rawliteral";
    server.send(200, "text/html", errorHtml);
    return;
  }
  preferences.putString("wifi_ssid", ssid);
  preferences.putString("wifi_password", password);
  Serial.println("WiFi credentials saved!");
  Serial.print("SSID: ");
  Serial.println(ssid);
  String successHtml = R"rawliteral(
    <!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Sukses</title>
    <style>body{font-family:'Segoe UI',Roboto,Arial,sans-serif;background:#1e293b;color:#f1f5f9;text-align:center;padding:50px;}h1{color:#22c55e;}p{margin-top:20px;}</style>
    </head><body><h1>Konfigurasi WiFi Berhasil!</h1><p>Perangkat akan restart dan mencoba terhubung ke jaringan Anda.</p><p>Jika tidak terhubung, perangkat akan kembali ke mode konfigurasi.</p></body></html>
  )rawliteral";
  server.send(200, "text/html", successHtml);
  delay(3000);
  ESP.restart();
}

void handleNotFound() {
  server.send(404, "text/plain", "404: Not Found");
}

void startAPMode() {
  Serial.println("Starting Access Point mode...");
  String mac = getMacAddress();
  String lastFourMac = mac.substring(mac.length() - 5);
  lastFourMac.replace(":", "");
  sprintf(AP_SSID, "Lantabura_Setup_%s", lastFourMac.c_str());
  Serial.print("Generated AP SSID: ");
  Serial.println(AP_SSID);

  WiFi.mode(WIFI_AP);
  WiFi.softAPConfig(AP_IP, AP_GATEWAY, AP_SUBNET);
  WiFi.softAP(AP_SSID, AP_PASSWORD);

  Serial.print("AP IP address: ");
  Serial.println(WiFi.softAPIP());
  Serial.print("AP SSID: ");
  Serial.println(AP_SSID);

  dnsServer.start(53, "*", AP_IP);
  server.on("/", handleRoot);
  server.on("/save", HTTP_POST, handleSave);
  server.onNotFound(handleNotFound);
  server.begin();
  Serial.println("HTTP server started (AP mode)");
  setupMode = true;
}

void startNormalMode() {
  Serial.println("Starting normal mode...");
  setupMode = false;

  // Muat accumulatedEnergy dari Preferences
  loadAccumulatedEnergy();

  autoZeroCurrent = preferences.getFloat("zero_current", 0.0);

  // Cek dan ambil kredensial perangkat jika belum ada
  if (storedDeviceId == "" || storedApiKey == "") {
    Serial.println("Device ID or API Key not found in Preferences. Attempting to fetch from server.");
    if (!fetchDeviceCredentials()) {
      Serial.println("Failed to get device credentials! Restarting to re-enter AP mode.");
      delay(5000); // Beri waktu untuk melihat pesan error
      ESP.restart();
    }
  } else {
    deviceConfigured = true; // Kredensial sudah ada di preferences
    Serial.println("Device credentials loaded from Preferences.");
  }

  if (autoZeroCurrent == 0.0) {
    Serial.println("Zero current not found in Preferences or is 0.0. Performing initial calibration.");
    calibrateZeroCurrent();
    preferences.putFloat("zero_current", autoZeroCurrent); // Simpan nilai kalibrasi
    Serial.println("Zero current calibrated and saved to Preferences.");
  } else {
    Serial.print("Zero current loaded from Preferences: ");
    Serial.println(autoZeroCurrent, 5);
  }

  // Initial relay state
  digitalWrite(RELAY_PIN, RELAY_OFF); // Pastikan relay OFF saat startup
  currentRelayState = false; 
  Serial.println("Relay initialized to OFF state.");


  lastEnergyTime = millis();
  lastEnergySaveTime = millis(); // Inisialisasi waktu simpan energi
  lastCollectionTime = millis(); // Inisialisasi waktu koleksi data
  lastWifiCheckTime = millis(); // Inisialisasi waktu cek WiFi
  lastRelayPollingTime = millis(); // Inisialisasi waktu polling relay
}

void checkResetButton() {
  static unsigned long lastPressTime = 0;
  static bool buttonPressed = false;
  static bool resetTriggered = false;

  int buttonState = digitalRead(RESET_BUTTON_PIN);

  if (buttonState == LOW) { // Tombol ditekan
    if (!buttonPressed) {
      lastPressTime = millis();
      buttonPressed = true;
      Serial.println("Button STARTING press detected.");
    } else if (!resetTriggered && (millis() - lastPressTime > 5000)) {
      Serial.println("!!! RESET BUTTON HELD FOR 5 SECONDS !!! Clearing all config (including zero current calibration)...");
      preferences.clear(); // Ini akan menghapus zero_current juga
      delay(1000);
      resetTriggered = true;
      ESP.restart();
    }
  } else { // Tombol dilepas
    if (buttonPressed) {
      Serial.println("Button RELEASED.");
      buttonPressed = false;
      resetTriggered = false;
    }
  }
}

void setup() {
  Serial.begin(115200);
  Serial.println("\n\n=== Lantabura Energy Monitor ===");

  pinMode(RESET_BUTTON_PIN, INPUT_PULLUP);
  pinMode(VOLTAGE_PIN, INPUT);
  analogReadResolution(12);

  pinMode(RELAY_PIN, OUTPUT);
  preferences.begin("device-config", false);

  if (digitalRead(RESET_BUTTON_PIN) == LOW) {
    Serial.println("Reset button pressed during startup! Clearing all config...");
    preferences.clear();
    delay(1000);
  }

  wifiSSID = preferences.getString("wifi_ssid", "");
  wifiPassword = preferences.getString("wifi_password", "");
  storedDeviceId = preferences.getString("device_id", "");
  storedApiKey = preferences.getString("api_key", "");

  Serial.print("Saved WiFi SSID: ");
  Serial.println(wifiSSID);
  Serial.print("Saved Device ID: ");
  Serial.println(storedDeviceId);

  if (wifiSSID.length() > 0) {
    connectToWiFi(wifiSSID.c_str(), wifiPassword.c_str());
    if (wifiConnected) {
      startNormalMode(); // startNormalMode akan menangani kalibrasi arus dan kredensial
      return;
    }
  }
  startAPMode();
}

void loop() {
  if (setupMode) {
    dnsServer.processNextRequest();
    server.handleClient();
    checkResetButton();
  } else {
    // Di mode normal, selalu periksa dan coba rekoneksi WiFi
    checkAndReconnectWiFi();
    unsigned long now = millis();
    if (now - lastCollectionTime >= COLLECTION_INTERVAL) {
      lastCollectionTime = now;
      collectAndAggregateData();
    }
    if (wifiConnected && deviceConfigured && (now - lastRelayPollingTime >= RELAY_POLLING_INTERVAL)) {
      lastRelayPollingTime = now;
      getRelayCommand();		
    }

    checkResetButton();
  }
}
