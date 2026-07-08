#include <WiFi.h>
#include <DNSServer.h>

const char* AP_SSID = "ESP32_Test";
const char* AP_PASSWORD = "12345678";
const IPAddress AP_IP(192, 168, 4, 1);
const IPAddress AP_GATEWAY(192, 168, 4, 1);
const IPAddress AP_SUBNET(255, 255, 255, 0);

DNSServer dnsServer;

void setup() {
  Serial.begin(115200);
  Serial.println("\n--- Tes WiFi AP ESP32 dengan DNS ---");
  
  // Reset WiFi
  WiFi.mode(WIFI_OFF);
  delay(500);
  
  // Setup AP
  WiFi.mode(WIFI_AP);
  delay(500);
  WiFi.softAPConfig(AP_IP, AP_GATEWAY, AP_SUBNET);
  delay(500);
  
  // Coba beberapa channel jika gagal
  bool apStarted = false;
  int channels[] = {1, 6, 11, 3, 9, 13};
  
  for (int i = 0; i < 6; i++) {
    Serial.print("Mencoba channel ");
    Serial.println(channels[i]);
    
    apStarted = WiFi.softAP(AP_SSID, AP_PASSWORD, channels[i], 0, 4);
    
    if (apStarted) {
      Serial.println("Berhasil pada channel " + String(channels[i]));
      break;
    }
    delay(1000);
  }
  
  if (apStarted) {
    Serial.println("\n=== INFORMASI AP ===");
    Serial.print("SSID: ");
    Serial.println(AP_SSID);
    Serial.print("Password: ");
    Serial.println(AP_PASSWORD);
    Serial.print("Channel: ");
    Serial.println(WiFi.channel());
    Serial.print("IP Address: ");
    Serial.println(WiFi.softAPIP());
    Serial.print("MAC Address: ");
    Serial.println(WiFi.softAPmacAddress());
    Serial.println("\n=== PETUNJUK ===");
    Serial.println("1. Buka pengaturan WiFi di ponsel Anda");
    Serial.println("2. Cari jaringan 'ESP32_Test'");
    Serial.println("3. Masukkan password: 12345678");
    Serial.println("4. Jika gagal, coba 'Lupakan Jaringan' lalu hubungkan lagi");
    Serial.println("5. Jika masih gagal, restart ponsel Anda");
    Serial.println("===================");
    
    // Setup DNS server (berguna jika ponsel mencoba redirect ke halaman login)
    dnsServer.start(53, "*", AP_IP);
  } else {
    Serial.println("GAGAL membuat AP setelah semua percobaan!");
  }
}

void loop() {
  dnsServer.processNextRequest();
  
  static unsigned long lastPrint = 0;
  if (millis() - lastPrint > 5000) {
    lastPrint = millis();
    int clients = WiFi.softAPgetStationNum();
    if (clients > 0) {
      Serial.print("Perangkat terhubung: ");
      Serial.println(clients);
    } else {
      Serial.println("Tidak ada perangkat terhubung. Cari 'ESP32_Test' di WiFi ponsel Anda.");
    }
  }
}
