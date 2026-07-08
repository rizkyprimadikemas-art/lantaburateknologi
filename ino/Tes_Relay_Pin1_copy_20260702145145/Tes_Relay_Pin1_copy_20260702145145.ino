#include <Arduino.h>

// --- Konfigurasi Pin Relay ---
#define RELAY_PIN 1 // GPIO 1 - Sesuaikan jika pin relay Anda berbeda
const int RELAY_ON = HIGH; // Sesuaikan jika relay Anda aktif LOW (maka ganti ke LOW)
const int RELAY_OFF = LOW; // Sesuaikan jika relay Anda aktif LOW (maka ganti ke HIGH)

// --- Variabel untuk menyimpan status relay saat ini ---
bool currentRelayState = false; // true = ON, false = OFF

void setup() {
  Serial.begin(115200); // Inisialisasi komunikasi serial
  Serial.println("\n--- Program Uji Relay ESP32 ---");
  Serial.println("Hubungkan relay ke GPIO 1.");
  Serial.println("Ketik '1' di Serial Monitor untuk menyalakan relay.");
  Serial.println("Ketik '0' di Serial Monitor untuk mematikan relay.");
  Serial.println("----------------------------------");

  pinMode(RELAY_PIN, OUTPUT); // Set pin relay sebagai output

  // Set status awal relay ke OFF
  digitalWrite(RELAY_PIN, RELAY_OFF);
  currentRelayState = false;
  Serial.println("Relay diinisialisasi ke status OFF.");
}

void loop() {
  // Cek apakah ada data yang masuk dari Serial Monitor
  if (Serial.available()) {
    String command = Serial.readStringUntil('\n'); // Baca perintah hingga karakter newline
    command.trim(); // Hapus spasi di awal/akhir

    if (command == "1") {
      if (!currentRelayState) { // Hanya ubah jika status berbeda
        digitalWrite(RELAY_PIN, RELAY_ON);
        currentRelayState = true;
        Serial.println("Perintah diterima: Relay ON.");
      } else {
        Serial.println("Relay sudah ON.");
      }
    } else if (command == "0") {
      if (currentRelayState) { // Hanya ubah jika status berbeda
        digitalWrite(RELAY_PIN, RELAY_OFF);
        currentRelayState = false;
        Serial.println("Perintah diterima: Relay OFF.");
      } else {
        Serial.println("Relay sudah OFF.");
      }
    } else {
      Serial.print("Perintah tidak dikenal: ");
      Serial.println(command);
      Serial.println("Gunakan '1' untuk ON atau '0' untuk OFF.");
    }
  }

  // Anda bisa menambahkan delay di sini jika tidak ingin loop terlalu cepat
  // delay(100);
}
