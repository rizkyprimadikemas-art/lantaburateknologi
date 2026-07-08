<?php
// config/ai_config.php
// Konfigurasi untuk integrasi AI (OpenAI atau Ollama)

define('AI_PROVIDER', getenv('AI_PROVIDER') ?: 'ollama');
// --- Konfigurasi Ollama ---
// URL endpoint API Ollama Anda (biasanya berjalan lokal)
define('OLLAMA_API_URL', getenv('OLLAMA_API_URL') ?: 'http://localhost:11434/api/chat'); // Gunakan /api/chat untuk respons terstruktur
// Model Ollama yang akan digunakan
define('OLLAMA_MODEL', getenv('OLLAMA_MODEL') ?: 'mistral');
?>
