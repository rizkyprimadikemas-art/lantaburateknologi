/*
  =====================================================================
  ENERGY MONITORING v3.0 + FIREBASE ONLINE (Merged - Update Kalibrasi Baru)
  =====================================================================
  Sumber:
  - Bagian SENSOR (sampling 200ms, kalibrasi kuadratik terbaru, zero-crossing,
    smoothing, tampilan 1 angka desimal) diambil dari sketch v3.0 terbaru.
  - Bagian KOMUNIKASI (WiFiManager + Firebase REST API: kirim data
    sensor & baca perintah relay) diambil dari sketch referensi
    "ENERGY MONITORING v3.0 + FIREBASE ONLINE (Merged)".

  Alur:
  1. Sampling sensor tiap 200ms  -> measureWithZeroCrossing()
  2. Tampilkan ke Serial Monitor tiap 1000ms (1 angka desimal)
  3. Kirim data ke Firebase tiap 3000ms
  4. Cek perintah relay dari Firebase tiap 2000ms
  =====================================================================
*/
#include <Arduino.h>
#include <WiFi.h>
#include <WiFiManager.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// =====================================================================
// --- Konfigurasi Firebase ---
// =====================================================================
#define FIREBASE_HOST "esp-ems-iot-default-rtdb.asia-southeast1.firebasedatabase.app"
#define FIREBASE_SECRET ""  // Biarkan kosong untuk test mode

// =====================================================================
// --- Pin ---
// =====================================================================
#define VOLTAGE_PIN 0
#define CURRENT_PIN 2
#define RELAY_PIN   1
#define LED_PIN     4   // indikator status WiFi

// =====================================================================
// --- Parameter Sampling Sensor (v3.0, dioptimalkan untuk 200ms) ---
// =====================================================================
const float VOLTAGE_CALIBRATION_FACTOR = 0.48791;
const int SAMPLE_PAIRS    = 320;   // agar sampling selesai < 150ms
const int SAMPLE_DELAY_US = 62;

// --- Kalibrasi Linier Lama (Fallback) ---
const float CAL_SLOPE  = 0.02869;
const float CAL_OFFSET = -5.363;

// --- Kalibrasi Kuadratik Terbaru (Koreksi Non-Linearitas) ---
float CAL_A = 0.000026214;
float CAL_B = 0.0026534;
float CAL_C = -0.01287;

// --- Variabel Kalibrasi Mandiri Lewat Serial ---
float calRaw0    = 187.0;
float calRawMid  = 302.0;
float calMidAmp  = 3.3;
float calRawHigh = 354.0;
float calHighAmp = 6.0;

// --- Smoothing (moving average 5 sampel @200ms = delay tampil 1 detik) ---
const int SMOOTHING_WINDOW = 5;
float currentHistory[SMOOTHING_WINDOW] = {0};
float powerHistory[SMOOTHING_WINDOW]   = {0};
int smoothingIndex = 0;
int smoothingCount = 0;

// --- Hasil Pengukuran ---
float voltageRMS      = 0;
float currentRMS      = 0;
float realPower       = 0;
float apparentPower   = 0;
float powerFactor     = 0;
float smoothedCurrent = 0;
float smoothedPower   = 0;
float lastIRmsRaw     = 0;

// =====================================================================
// --- Status WiFi & Relay ---
// =====================================================================
bool wifiConnected     = false;
bool currentRelayState = false;

// =====================================================================
// --- Timer & Buffer Serial ---
// =====================================================================
String serialBuffer = "";
unsigned long measureTimer = 0;
unsigned long displayTimer = 0;
unsigned long sendTimer    = 0;
unsigned long relayTimer   = 0;

// =====================================================================
// Prototype (agar bisa saling memanggil sebelum didefinisikan)
// =====================================================================
void updateRelayState(String state);

// =====================================================================
// Smoothing helpers
// =====================================================================
void applySmoothing(float newCurrent, float newPower) {
  currentHistory[smoothingIndex] = newCurrent;
  powerHistory[smoothingIndex]   = newPower;
  smoothingIndex = (smoothingIndex + 1) % SMOOTHING_WINDOW;
  if (smoothingCount < SMOOTHING_WINDOW) smoothingCount++;

  float sumCurrent = 0, sumPower = 0;
  for (int i = 0; i < smoothingCount; i++) {
    sumCurrent += currentHistory[i];
    sumPower   += powerHistory[i];
  }
  smoothedCurrent = sumCurrent / smoothingCount;
  smoothedPower   = sumPower / smoothingCount;
}

void resetSmoothing() {
  smoothingCount = 0;
  smoothingIndex = 0;
}

// =====================================================================
// Pemrosesan Sinyal Single-Pass (Zero-Crossing / RMS langsung dari AC)
// =====================================================================
void measureWithZeroCrossing() {
  double sumV = 0, sumI = 0;
  double sumVsq = 0, sumIsq = 0;
  double sumVI = 0;

  const double V_APPROX = 2048.0; // titik tengah ADC 12-bit
  const double I_APPROX = 2048.0;

  for (int i = 0; i < SAMPLE_PAIRS; i++) {
    analogRead(VOLTAGE_PIN);
    double rawV = (double)analogRead(VOLTAGE_PIN) - V_APPROX;

    analogRead(CURRENT_PIN);
    double rawI = (double)analogRead(CURRENT_PIN) - I_APPROX;

    sumV   += rawV;
    sumI   += rawI;
    sumVsq += rawV * rawV;
    sumIsq += rawI * rawI;
    sumVI  += rawV * rawI;

    delayMicroseconds(SAMPLE_DELAY_US);
  }

  double meanV_diff = sumV / SAMPLE_PAIRS;
  double meanI_diff = sumI / SAMPLE_PAIRS;

  double vVar = (sumVsq / SAMPLE_PAIRS) - (meanV_diff * meanV_diff);
  double iVar = (sumIsq / SAMPLE_PAIRS) - (meanI_diff * meanI_diff);
  if (vVar < 0) vVar = 0;
  if (iVar < 0) iVar = 0;

  float vRmsRaw = sqrt(vVar);
  float iRmsRaw = sqrt(iVar);
  lastIRmsRaw = iRmsRaw;

  // --- Tegangan RMS ---
  voltageRMS = vRmsRaw * VOLTAGE_CALIBRATION_FACTOR;
  if (voltageRMS < 30) voltageRMS = 0;

  // --- Arus RMS dengan Koreksi Polinomial Orde-2 ---
  if (CAL_A != 0.0) {
    currentRMS = (CAL_A * iRmsRaw * iRmsRaw) + (CAL_B * iRmsRaw) + CAL_C;
  } else {
    currentRMS = CAL_SLOPE * iRmsRaw + CAL_OFFSET;
  }

  // Adaptive Noise Gate
  if (currentRMS < 0.10) currentRMS = 0.0;

  // --- Real Power (Kovariansi AC V & I) ---
  double covVI = (sumVI / SAMPLE_PAIRS) - (meanV_diff * meanI_diff);

  // --- Power Factor ---
  if (vRmsRaw > 0 && iRmsRaw > 0) {
    float rawPF = covVI / (vRmsRaw * iRmsRaw);
    powerFactor = rawPF;
    if (powerFactor > 1.0) powerFactor = 1.0;
    if (powerFactor < 0.0) powerFactor = 0.0;
  } else {
    powerFactor = 0;
  }

  // --- Daya ---
  realPower = voltageRMS * currentRMS * powerFactor;
  if (currentRMS <= 0 || voltageRMS <= 0) realPower = 0;
  apparentPower = voltageRMS * currentRMS;

  applySmoothing(currentRMS, realPower);
}

// =====================================================================
// Perhitungan Koefisien Kurva Kalibrasi Kuadratik
// =====================================================================
void calculateQuadraticCoefficients() {
  float x1 = calRaw0,    y1 = 0.0;
  float x2 = calRawMid,  y2 = calMidAmp;
  float x3 = calRawHigh, y3 = calHighAmp;

  if (abs(x2 - x1) < 0.1 || abs(x3 - x2) < 0.1 || abs(x3 - x1) < 0.1) {
    Serial.println("Error: Titik kalibrasi terlalu dekat atau sama!");
    return;
  }

  float d1 = (y2 - y1) / (x2 - x1);
  float d2 = (y3 - y2) / (x3 - x2);
  CAL_A = (d2 - d1) / (x3 - x1);
  CAL_B = d1 - CAL_A * (x1 + x2);
  CAL_C = y1 - CAL_A * x1 * x1 - CAL_B * x1;

  Serial.println("\n=== BERHASIL MENGHITUNG KOEFISIEN BARU ===");
  Serial.print("CAL_A = "); Serial.println(CAL_A, 9);
  Serial.print("CAL_B = "); Serial.println(CAL_B, 7);
  Serial.print("CAL_C = "); Serial.println(CAL_C, 5);
  Serial.println("==========================================");
  Serial.println("Silakan salin nilai di atas ke deklarasi variabel CAL_A/CAL_B/CAL_C.");
}

// =====================================================================
// Perintah Serial (kalibrasi & kontrol manual lokal)
// =====================================================================
void handleSerialCommand() {
  while (Serial.available() > 0) {
    char c = Serial.read();
    if (c == '\n' || c == '\r') {
      if (serialBuffer.length() > 0) {
        serialBuffer.trim();

        if (serialBuffer.startsWith("CAL0")) {
          calRaw0 = lastIRmsRaw;
          Serial.print("OK: Titik 0A disimpan. Raw RMS = ");
          Serial.println(calRaw0, 1);
        }
        else if (serialBuffer.startsWith("CALMID ")) {
          calMidAmp = serialBuffer.substring(7).toFloat();
          calRawMid = lastIRmsRaw;
          Serial.print("OK: Titik Mid "); Serial.print(calMidAmp, 1);
          Serial.print("A disimpan. Raw RMS = "); Serial.println(calRawMid, 1);
        }
        else if (serialBuffer.startsWith("CALHIGH ")) {
          calHighAmp = serialBuffer.substring(8).toFloat();
          calRawHigh = lastIRmsRaw;
          Serial.print("OK: Titik High "); Serial.print(calHighAmp, 1);
          Serial.print("A disimpan. Raw RMS = "); Serial.println(calRawHigh, 1);
          calculateQuadraticCoefficients();
        }
        else {
          serialBuffer.toUpperCase();
          if (serialBuffer == "ON") {
            digitalWrite(RELAY_PIN, HIGH);
            currentRelayState = true;
            Serial.println("Relay ON (manual)");
            updateRelayState("ON");
          } else if (serialBuffer == "OFF") {
            digitalWrite(RELAY_PIN, LOW);
            currentRelayState = false;
            Serial.println("Relay OFF (manual)");
            updateRelayState("OFF");
          } else if (serialBuffer == "RAW") {
            Serial.print("ADC_RMS="); Serial.print(lastIRmsRaw, 1);
            Serial.print(" I="); Serial.print(currentRMS, 1);
            Serial.println("A");
          } else if (serialBuffer == "STATUS") {
            Serial.println("=== EMS STATUS & CALIBRATION ===");
            Serial.print("CAL_A: "); Serial.println(CAL_A, 9);
            Serial.print("CAL_B: "); Serial.println(CAL_B, 7);
            Serial.print("CAL_C: "); Serial.println(CAL_C, 5);
            Serial.print("WiFi: "); Serial.println(wifiConnected ? "Connected" : "Disconnected");
            Serial.println("=================================");
          }
        }
        serialBuffer = "";
      }
    } else {
      serialBuffer += c;
    }
  }
}

// =====================================================================
// --- WiFi Setup (WiFiManager) ---
// =====================================================================
void setupWiFi() {
  WiFiManager wm;
  Serial.println("Konfigurasi WiFi...");
  if (wm.autoConnect("ESP32_Config_AP", "password")) {
    Serial.println("WiFi OK!");
    digitalWrite(LED_PIN, HIGH);
    wifiConnected = true;
  }
}

// =====================================================================
// --- Firebase REST API: kirim data sensor ---
// =====================================================================
void sendDataToFirebase() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = "https://" + String(FIREBASE_HOST) + "/sensor.json";

  http.begin(url);
  http.addHeader("Content-Type", "application/json");

  StaticJsonDocument<256> doc;
  doc["voltage"]        = voltageRMS;
  doc["current"]        = smoothedCurrent;
  doc["power"]          = smoothedPower;
  doc["apparent_power"] = apparentPower;
  doc["power_factor"]   = powerFactor;
  doc["relay_state"]    = currentRelayState ? "ON" : "OFF";
  doc["timestamp"]      = String(millis());

  String jsonString;
  serializeJson(doc, jsonString);

  int httpCode = http.PUT(jsonString);
  if (httpCode <= 0) {
    Serial.print("Gagal kirim data: ");
    Serial.println(httpCode);
  }

  http.end();
}

// =====================================================================
// --- Firebase REST API: baca & terapkan perintah relay ---
// =====================================================================
void updateRelayState(String state) {
  HTTPClient http;
  String url = "https://" + String(FIREBASE_HOST) + "/sensor/relay_state.json";
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.PUT("\"" + state + "\"");
  http.end();
}

void checkRelayCommand() {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;
  String url = "https://" + String(FIREBASE_HOST) + "/relay/command.json";
  http.begin(url);

  int httpCode = http.GET();

  if (httpCode > 0) {
    String payload = http.getString();
    payload.trim();
    if (payload.startsWith("\"") && payload.endsWith("\"")) {
      payload = payload.substring(1, payload.length() - 1);
    }

    if (payload == "ON" && !currentRelayState) {
      digitalWrite(RELAY_PIN, HIGH);
      currentRelayState = true;
      Serial.println(">>> Relay ON (Firebase)!");
      updateRelayState("ON");
    } else if (payload == "OFF" && currentRelayState) {
      digitalWrite(RELAY_PIN, LOW);
      currentRelayState = false;
      Serial.println(">>> Relay OFF (Firebase)!");
      updateRelayState("OFF");
    }
  } else if (httpCode == HTTPC_ERROR_CONNECTION_REFUSED || httpCode == -1) {
    Serial.println("Gagal cek perintah relay dari Firebase");
  } else if (httpCode == 404) {
    // Path belum ada, buat default OFF
    http.end();

    HTTPClient http2;
    String url2 = "https://" + String(FIREBASE_HOST) + "/relay/command.json";
    http2.begin(url2);
    http2.addHeader("Content-Type", "application/json");
    http2.PUT("\"OFF\"");
    http2.end();
  }

  http.end();
}

// =====================================================================
// SETUP
// =====================================================================
void setup() {
  Serial.begin(115200);
  delay(2000);

  pinMode(VOLTAGE_PIN, INPUT);
  pinMode(CURRENT_PIN, INPUT);
  analogReadResolution(12);
  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, LOW);
  pinMode(LED_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);

  Serial.println("=== Energy Monitoring v3.0 + Firebase Online ===");
  Serial.println("Perintah Kontrol: ON / OFF / RAW / STATUS");
  Serial.println("Perintah Kalibrasi:");
  Serial.println("  1. Matikan beban, kirim: CAL0");
  Serial.println("  2. Nyalakan beban rendah (misal 3.3A), kirim: CALMID 3.3");
  Serial.println("  3. Nyalakan beban tinggi (misal 6.0A), kirim: CALHIGH 6.0");
  Serial.println();

  setupWiFi();

  measureTimer = millis();
  displayTimer = millis();
  sendTimer    = millis();
  relayTimer   = millis();
}

// =====================================================================
// LOOP
// =====================================================================
void loop() {
  handleSerialCommand();

  // Pantau status WiFi
  if (WiFi.status() != WL_CONNECTED) {
    if (wifiConnected) {
      Serial.println("\nWiFi putus...");
      digitalWrite(LED_PIN, LOW);
      wifiConnected = false;
    }
  } else if (!wifiConnected) {
    Serial.println("\nWiFi terhubung kembali!");
    digitalWrite(LED_PIN, HIGH);
    wifiConnected = true;
  }

  unsigned long now = millis();

  // 1. Sampling sensor tiap 200ms
  if (now - measureTimer >= 200) {
    measureTimer = now;
    measureWithZeroCrossing();
  }

  // 2. Tampilkan ke Serial Monitor tiap 1000ms (1 angka desimal)
  if (now - displayTimer >= 1000) {
    displayTimer = now;
    Serial.print("V="); Serial.print(voltageRMS, 1);
    Serial.print("V  I="); Serial.print(smoothedCurrent, 1);
    Serial.print("A  P="); Serial.print(smoothedPower, 1);
    Serial.print("W  S="); Serial.print(apparentPower, 1);
    Serial.print("VA  PF="); Serial.print(powerFactor, 1);
    Serial.print("  WiFi="); Serial.println(wifiConnected ? "OK" : "-");
  }

  // 3. Kirim data ke Firebase tiap 3000ms
  if (now - sendTimer >= 3000) {
    sendTimer = now;
    sendDataToFirebase();
  }

  // 4. Cek perintah relay dari Firebase tiap 2000ms
  if (now - relayTimer >= 2000) {
    relayTimer = now;
    checkRelayCommand();
  }
}
