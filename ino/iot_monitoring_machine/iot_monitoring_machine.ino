#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>   // Install from Library Manager
#include <Preferences.h>   // Built-in for ESP32

// --- Konfigurasi WiFi ---
const char* ssid = "LaTek_5G";
const char* password = "34222101";

const String BASE_URL = "http://192.168.1.179/lantaburateknologi/api/"; 

// --- Penyimpanan Permanen ---
Preferences preferences;
String storedDeviceId = ""; // Ini akan menyimpan device_id (MAC Address) dari server
String storedApiKey = "";   // Ini akan menyimpan API Key dari server

void connectToWiFi() {
    Serial.print("Menghubungkan ke WiFi ");
    Serial.println(ssid);
    WiFi.begin(ssid, password);
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) { // Coba 20 kali (10 detik)
        delay(500);
        Serial.print(".");
        attempts++;
    }
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\nTerhubung ke WiFi!");
        Serial.print("Alamat IP: ");
        Serial.println(WiFi.localIP());
    } else {
        Serial.println("\nGagal terhubung ke WiFi.");
    }
}

// Fungsi untuk mendapatkan kredensial (device_id dan api_key) dari server
bool fetchDeviceCredentials() {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("WiFi tidak terhubung. Tidak bisa mengambil kredensial.");
        return false;
    }

    HTTPClient http;
    String url = BASE_URL + "get_device_credentials.php"; // Endpoint baru
    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    // Dapatkan MAC Address unik ESP32 ini
    String macAddress = WiFi.macAddress();
    
    // Buat payload JSON
    StaticJsonDocument<200> doc;
    doc["mac_address"] = macAddress;

    String requestBody;
    serializeJson(doc, requestBody);

    Serial.print("Mengirim permintaan kredensial ke: "); Serial.println(url);
    Serial.print("Body Permintaan: "); Serial.println(requestBody);

    int httpResponseCode = http.POST(requestBody);

    if (httpResponseCode > 0) {
        String response = http.getString();
        Serial.print("Kode Respon HTTP: "); Serial.println(httpResponseCode);
        Serial.print("Respon Server: "); Serial.println(response);

        StaticJsonDocument<200> responseDoc;
        DeserializationError error = deserializeJson(responseDoc, response);

        if (error) {
            Serial.print(F("Gagal parsing JSON respon: "));
            Serial.println(error.f_str());
            http.end();
            return false;
        }

        if (responseDoc["status"] == "success") {
            storedDeviceId = responseDoc["device_id"].as<String>();
            storedApiKey = responseDoc["api_key"].as<String>();

            // Simpan ke Preferences agar tidak hilang saat reboot
            preferences.putString("device_id", storedDeviceId);
            preferences.putString("api_key", storedApiKey);

            Serial.println("Kredensial perangkat berhasil diambil dan disimpan!");
            Serial.print("Device ID yang Ditetapkan: "); Serial.println(storedDeviceId);
            Serial.print("API Key yang Ditetapkan: "); Serial.println(storedApiKey);
            http.end();
            return true;
        } else {
            Serial.print("Gagal mengambil kredensial: "); Serial.println(responseDoc["message"].as<String>());
            http.end();
            return false;
        }
    } else {
        Serial.print("Error saat permintaan HTTP: "); Serial.println(httpResponseCode);
        http.end();
        return false;
    }
}

// Fungsi untuk mengirim data sensor (setelah kredensial berhasil didapatkan)
void sendSensorData() {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("WiFi tidak terhubung. Tidak bisa mengirim data.");
        return;
    }

    if (storedDeviceId == "" || storedApiKey == "") {
        Serial.println("Kredensial belum tersedia. Tidak bisa mengirim data.");
        return;
    }

    HTTPClient http;
    String url = BASE_URL + "receive_data.php"; // Endpoint untuk menerima data sensor
    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    // --- GANTI DENGAN PEMBACAAN SENSOR AKTUAL ANDA ---
    float power = random(10, 100);   // Contoh: daya acak
    float voltage = random(220, 230); // Contoh: tegangan acak
    float current = power / voltage; // Contoh: hitung arus
    float energy = 0.05;             // Contoh: energi tambahan

    // Buat payload JSON
    StaticJsonDocument<300> doc;
    doc["device_id"] = storedDeviceId; // Gunakan device_id yang sudah disimpan
    doc["api_key"] = storedApiKey;     // Gunakan API Key yang sudah disimpan
    doc["power"] = power;
    doc["voltage"] = voltage;
    doc["current"] = current;
    doc["energy"] = energy; 

    String requestBody;
    serializeJson(doc, requestBody);

    Serial.print("Mengirim data sensor ke: "); Serial.println(url);
    Serial.print("Body Permintaan: "); Serial.println(requestBody);

    int httpResponseCode = http.POST(requestBody);

    if (httpResponseCode > 0) {
        String response = http.getString();
        Serial.print("Kode Respon HTTP: "); Serial.println(httpResponseCode);
        Serial.print("Respon Server: "); Serial.println(response);

        StaticJsonDocument<200> responseDoc;
        DeserializationError error = deserializeJson(responseDoc, response);

        if (error) {
            Serial.print(F("Gagal parsing JSON respon: "));
            Serial.println(error.f_str());
            http.end();
            return;
        }

        if (responseDoc["status"] == "success") {
            Serial.println("Data sensor berhasil dikirim.");
        } else {
            Serial.print("Gagal mengirim data sensor: "); Serial.println(responseDoc["message"].as<String>());
        }
    } else {
        Serial.print("Error saat permintaan HTTP: "); Serial.println(httpResponseCode);
    }
    http.end();
}

void setup() {
    Serial.begin(115200);
    // Tambahan untuk menampilkan MAC Address
    Serial.println("\n------------------------------------");
    Serial.print("MAC Address ESP32 ini: ");
    Serial.println(WiFi.macAddress());
    Serial.println("------------------------------------");

    connectToWiFi(); // Hubungkan ke WiFi

    // Inisialisasi Preferences
    preferences.begin("device-config", false); // "device-config" adalah namespace, false = mode baca/tulis

    // Coba muat device_id dan api_key yang sudah tersimpan
    storedDeviceId = preferences.getString("device_id", "");
    storedApiKey = preferences.getString("api_key", "");

    if (storedDeviceId == "" || storedApiKey == "") {
        Serial.println("Kredensial perangkat belum tersimpan. Mencoba mengambil dari server...");
        if (fetchDeviceCredentials()) {
            Serial.println("Pengambilan kredensial berhasil!");
        } else {
            Serial.println("Pengambilan kredensial gagal. Pastikan perangkat sudah didaftarkan di dashboard.");
            Serial.println("Mencoba lagi dalam beberapa saat...");
            // Jika gagal, bisa jadi perangkat belum didaftarkan di dashboard
            // ESP32 akan terus mencoba di loop sampai berhasil
        }
    } else {
        Serial.println("Kredensial perangkat sudah tersimpan. Menggunakan yang tersimpan.");
        Serial.print("Device ID: "); Serial.println(storedDeviceId);
        Serial.print("API Key: "); Serial.println(storedApiKey);
    }
}

void loop() {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("WiFi terputus. Menghubungkan ulang...");
        connectToWiFi();
        delay(5000); // Beri waktu untuk terhubung kembali
        return;
    }

    // Jika kredensial belum ada, coba ambil lagi
    if (storedDeviceId == "" || storedApiKey == "") {
        Serial.println("Kredensial hilang atau belum didapat. Mencoba mengambil lagi...");
        if (!fetchDeviceCredentials()) {
            Serial.println("Gagal mendapatkan kredensial. Menunggu...");
            delay(15000); // Tunggu lebih lama sebelum mencoba lagi
            return;
        }
    }

    // Hanya kirim data jika kredensial sudah tersedia
    sendSensorData();

    delay(1500); 

}


