#include <Arduino.h> // Diperlukan untuk fungsi Arduino seperti Serial, analogRead, delay

// --- Pin Sensor ---
#define VOLTAGE_PIN 0 // GPIO 0 (ADC1_CH0) - Sesuaikan jika pin Anda berbeda

// --- Deklarasi Fungsi (opsional untuk program sederhana ini, tapi praktik yang baik) ---
float readRawVoltageRMS();

void setup() {
  Serial.begin(115200); // Inisialisasi komunikasi serial
  Serial.println("\n--- Pembacaan Raw Voltage RMS ADC ---");
  Serial.println("Pastikan sensor tegangan terhubung ke GPIO 0.");
  Serial.println("Hubungkan sensor ke sumber tegangan AC yang stabil.");
  Serial.println("Gunakan multimeter untuk mengukur tegangan AC nyata.");
  Serial.println("--------------------------------------");

  pinMode(VOLTAGE_PIN, INPUT); // Set pin sebagai input
  analogReadResolution(12);    // Set resolusi ADC ke 12 bit (0-4095)
}

void loop() {
  float rawRms = readRawVoltageRMS(); // Panggil fungsi untuk mendapatkan nilai RMS mentah

  Serial.print("Raw Voltage RMS ADC: ");
  Serial.println(rawRms, 2); // Tampilkan dengan 2 angka di belakang koma

  delay(1000); // Tunggu 1 detik sebelum pembacaan berikutnya
}

// Fungsi untuk membaca nilai RMS mentah dari ADC untuk tegangan
float readRawVoltageRMS() {
  const int samples = 400; // Jumlah sampel untuk perhitungan RMS
  float sum = 0;           // Untuk menghitung offset DC
  float sumSq = 0;         // Untuk menghitung kuadrat sampel

  // Tahap 1: Hitung offset DC rata-rata
  // Ini membantu mengeliminasi komponen DC dari sinyal AC
  for (int i = 0; i < samples; i++) {
    sum += analogRead(VOLTAGE_PIN);
    delayMicroseconds(150); // Penundaan kecil untuk stabilitas ADC
  }
  float offset = sum / samples; // Offset DC rata-rata

  // Tahap 2: Hitung RMS setelah mengeliminasi offset DC
  for (int i = 0; i < samples; i++) {
    float val = analogRead(VOLTAGE_PIN);
    float centered = val - offset; // Kurangi dengan offset DC
    sumSq += centered * centered;  // Akumulasi kuadrat dari nilai yang sudah di-centered
    delayMicroseconds(150);        // Penundaan kecil
  }

  // Hitung nilai RMS
  float rms = sqrt(sumSq / samples);
  return rms;
}
