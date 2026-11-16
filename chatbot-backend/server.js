// ============================================================
// server.js - Chatbot Backend untuk Sistem Absensi Guru (SAG)
// Multi-Provider AI Dinamis: Mendukung Banyak API Key & Model
// ============================================================

const fs      = require('fs');
const path    = require('path');
const dotenv  = require('dotenv');
const express = require('express');
const cors    = require('cors');

// Muat konfigurasi awal
dotenv.config();

const app  = express();
const PORT = process.env.PORT || 3000;

// ─── Pembacaan Daftar Konfigurasi AI Multi-Provider ──────────
function getAllAiConfigs() {
  const jsonPath = path.join(__dirname, 'ai_config.json');
  let configs = [];

  if (fs.existsSync(jsonPath)) {
    try {
      configs = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
      if (Array.isArray(configs)) {
        return configs;
      }
    } catch (_) {}
  }

  // Fallback membaca .env jika ai_config.json belum dibuat
  const envPath = path.join(__dirname, '.env');
  let fileEnv = {};
  if (fs.existsSync(envPath)) {
    try {
      fileEnv = dotenv.parse(fs.readFileSync(envPath));
    } catch (_) {}
  }

  const groqApiKey = (fileEnv.GROQ_API_KEY || process.env.GROQ_API_KEY || '').trim();
  const groqModel  = (fileEnv.GROQ_MODEL || process.env.GROQ_MODEL || 'openai/gpt-oss-20b').trim();
  const groqStatus = (fileEnv.GROQ_STATUS || 'aktif').trim().toLowerCase();

  const geminiApiKey = (fileEnv.GEMINI_API_KEY || process.env.GEMINI_API_KEY || '').trim();
  let rawGeminiModel = (fileEnv.GEMINI_MODEL || process.env.GEMINI_MODEL || 'gemini-1.5-flash').trim();
  if (!rawGeminiModel || rawGeminiModel === 'MODEL_GEMINI_YANG_DIDUKUNG') {
    rawGeminiModel = 'gemini-1.5-flash';
  }
  const geminiModel  = rawGeminiModel.replace(/^models\//i, '');
  const geminiStatus = (fileEnv.GEMINI_STATUS || 'nonaktif').trim().toLowerCase();

  const primaryProvider = (fileEnv.AI_PRIMARY_PROVIDER || 'groq').trim().toLowerCase();

  const list = [];
  if (groqApiKey && groqApiKey !== 'ISI_API_KEY_GROQ_DI_LOKAL') {
    list.push({
      id: 1,
      provider: 'groq',
      model: groqModel,
      priority: primaryProvider === 'groq' ? 'utama' : 'cadangan',
      is_primary: primaryProvider === 'groq',
      is_active: groqStatus === 'aktif',
      apiKey: groqApiKey
    });
  }

  if (geminiApiKey && geminiApiKey !== 'ISI_API_KEY_GEMINI_BARU_DI_LOKAL') {
    list.push({
      id: 2,
      provider: 'gemini',
      model: geminiModel,
      priority: primaryProvider === 'gemini' ? 'utama' : 'cadangan',
      is_primary: primaryProvider === 'gemini',
      is_active: geminiStatus === 'aktif',
      apiKey: geminiApiKey
    });
  }

  return list;
}

// ─── Utility: Sanitasi Log (Mencegah Kebocoran API Key) ───────
function sanitizeText(input, configs) {
  if (!input) return '';
  let str = typeof input === 'object' ? JSON.stringify(input) : String(input);
  const cfgList = configs || getAllAiConfigs();

  for (const c of cfgList) {
    if (c.apiKey && c.apiKey.length > 5) {
      str = str.split(c.apiKey).join('[REDACTED_API_KEY]');
    }
  }

  str = str.replace(/key=[a-zA-Z0-9_\-]+/gi, 'key=[REDACTED]');
  str = str.replace(/Bearer\s+[a-zA-Z0-9_\-]+/gi, 'Bearer [REDACTED]');
  return str;
}

// ─── Utility: Klasifikasi Error AI ────────────────────────────
function classifyError(status, errorObj) {
  if (errorObj && (errorObj.name === 'AbortError' || errorObj.name === 'TimeoutError' || (errorObj.message && errorObj.message.includes('timeout')))) {
    return { type: 'TIMEOUT', detail: 'Batas waktu permintaan terlampaui (timeout).' };
  }
  if (status === 429) {
    return { type: 'RATE_LIMIT_OR_QUOTA', detail: 'Rate limit tercapai atau kuota penggunaan habis (HTTP 429).' };
  }
  if (status === 401 || status === 403) {
    return { type: 'AUTH_INVALID', detail: 'API Key tidak valid atau otorisasi ditolak (HTTP ' + status + ').' };
  }
  if (status === 404) {
    return { type: 'MODEL_UNAVAILABLE', detail: 'Model AI tidak ditemukan atau tidak tersedia (HTTP 404).' };
  }
  if (status >= 500) {
    return { type: 'SERVER_DISRUPTION', detail: 'Gangguan server pada penyedia AI (HTTP ' + status + ').' };
  }
  if (status === 400) {
    return { type: 'BAD_REQUEST', detail: 'Format parameter permintaan tidak valid (HTTP 400).' };
  }
  return { type: 'NETWORK_ERROR', detail: (errorObj && errorObj.message) || 'Koneksi jaringan terputus atau tidak stabil.' };
}

// ─── Caller: Groq ─────────────────────────────────────────────
async function callGroqInstance(message, systemPrompt, apiKey, model) {
  if (!apiKey) {
    return { success: false, errorType: 'NOT_CONFIGURED', errorDetail: 'API Key Groq kosong.' };
  }

  const groqBaseUrl = 'https://api.groq.com/openai/v1';

  try {
    const response = await fetch(`${groqBaseUrl}/chat/completions`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        model: model || 'openai/gpt-oss-20b',
        messages: [
          { role: 'system', content: systemPrompt },
          { role: 'user', content: message }
        ],
        temperature: 0.2,
        max_tokens: 300
      }),
      signal: AbortSignal.timeout(12000)
    });

    if (!response.ok) {
      const classified = classifyError(response.status, null);
      console.error(`[GROQ API ERROR] Status ${response.status}: ${classified.detail}`);
      return {
        success: false,
        status: response.status,
        errorType: classified.type,
        errorDetail: classified.detail
      };
    }

    const data = await response.json();
    if (data.choices && data.choices.length > 0 && data.choices[0].message && data.choices[0].message.content) {
      return {
        success: true,
        text: data.choices[0].message.content,
        provider: 'groq'
      };
    }

    return {
      success: false,
      errorType: 'EMPTY_RESPONSE',
      errorDetail: 'Groq mengembalikan respon kosong.'
    };
  } catch (err) {
    const classified = classifyError(0, err);
    console.error(`[GROQ NETWORK/TIMEOUT] ${classified.detail}`);
    return {
      success: false,
      errorType: classified.type,
      errorDetail: classified.detail
    };
  }
}

// ─── Caller: Google Gemini ────────────────────────────────────
async function callGeminiInstance(message, systemPrompt, apiKey, model) {
  if (!apiKey) {
    return { success: false, errorType: 'NOT_CONFIGURED', errorDetail: 'API Key Gemini kosong.' };
  }

  let cleanModel = (model || 'gemini-3.5-flash').trim().replace(/^models\//i, '');
  // Gunakan ?key= pada URL agar tidak ada masalah header stripping pada proxy/firewall
  const endpoint = `https://generativelanguage.googleapis.com/v1beta/models/${encodeURIComponent(cleanModel)}:generateContent?key=${encodeURIComponent(apiKey)}`;

  const standardPayload = {
    systemInstruction: {
      parts: [{ text: systemPrompt }]
    },
    contents: [
      {
        role: 'user',
        parts: [{ text: message }]
      }
    ],
    generationConfig: {
      temperature: 0.2,
      maxOutputTokens: 300
    }
  };

  try {
    let response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(standardPayload),
      signal: AbortSignal.timeout(12000)
    });

    if (response.status === 400) {
      console.warn('[GEMINI WARNING] Mencoba payload gabungan tanpa systemInstruction...');
      const fallbackPayload = {
        contents: [
          {
            role: 'user',
            parts: [{ text: `${systemPrompt}\n\n[PERTANYAAN PENGGUNA]:\n${message}` }]
          }
        ],
        generationConfig: {
          temperature: 0.2,
          maxOutputTokens: 300
        }
      };

      response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(fallbackPayload),
        signal: AbortSignal.timeout(12000)
      });
    }

    if (!response.ok) {
      let rawDetail = '';
      try {
        const errJson = await response.json();
        rawDetail = errJson.error ? errJson.error.message : '';
      } catch (_) {}

      const classified = classifyError(response.status, null);
      let customDetail = classified.detail;

      if (response.status === 404) {
        customDetail = `Model '${cleanModel}' tidak tersedia untuk API Key ini.`;
      } else if (response.status === 400 && (rawDetail.includes('API key') || rawDetail.includes('API_KEY'))) {
        customDetail = 'API Key Gemini tidak valid.';
      } else if (rawDetail) {
        customDetail = `${classified.detail} (${rawDetail.slice(0, 100)})`;
      }

      console.error(`[GEMINI API ERROR] Status ${response.status} (Model: ${cleanModel}): ${customDetail}`);
      return {
        success: false,
        status: response.status,
        errorType: classified.type,
        errorDetail: customDetail
      };
    }

    const data = await response.json();
    const candidate = data.candidates && data.candidates[0];
    const textPart = candidate && candidate.content && candidate.content.parts && candidate.content.parts[0];
    const replyText = textPart && textPart.text ? textPart.text.trim() : '';

    if (replyText) {
      return {
        success: true,
        text: replyText,
        provider: 'gemini'
      };
    }

    return {
      success: false,
      errorType: 'EMPTY_RESPONSE',
      errorDetail: 'Google Gemini mengembalikan konten kosong.'
    };
  } catch (err) {
    const classified = classifyError(0, err);
    console.error(`[GEMINI NETWORK/TIMEOUT] (Model: ${cleanModel}): ${classified.detail}`);
    return {
      success: false,
      errorType: classified.type,
      errorDetail: classified.detail
    };
  }
}

// ─── Middleware ───────────────────────────────────────────────
app.use(cors());
app.use(express.json());

// ─── Routes ──────────────────────────────────────────────────

app.get('/', (req, res) => {
  const configs = getAllAiConfigs();
  const activeCount = configs.filter(c => c.is_active).length;
  res.json({
    status   : 'ok',
    message  : 'Sistem Absensi Guru - Chatbot Backend Multi-Provider AI',
    total_configs: configs.length,
    active_configs: activeCount
  });
});

app.get('/health', (req, res) => {
  const configs = getAllAiConfigs();
  const activeConfigs = configs.filter(c => c.is_active && Boolean(c.apiKey));
  
  const primaryItem = activeConfigs.find(c => c.is_primary) || activeConfigs[0];
  const primaryName = primaryItem ? (primaryItem.provider === 'gemini' ? 'Google Gemini' : 'Groq') : '-';

  res.json({
    status            : 'ok',
    service           : 'SAG Chatbot',
    message           : 'Chatbot backend aktif',
    port              : Number(PORT),
    has_active_ai     : activeConfigs.length > 0,
    active_count      : activeConfigs.length,
    primary_provider  : primaryName,
    primary_model     : primaryItem ? primaryItem.model : '-'
  });
});

app.post('/api/chat', async (req, res) => {
  const configs = getAllAiConfigs();
  const message = (req.body && req.body.message) ? String(req.body.message).trim() : '';
  const roleContext = req.body.role ? req.body.role : 'pengguna';
  const dataContext = req.body.context ? req.body.context : {};
  
  // Validasi input pengguna
  if (!message) {
    return res.status(400).json({
      status : 'error',
      message: 'Pesan tidak boleh kosong.'
    });
  }

  // --- Scope Check Heuristics ---
  const lowerMessage = message.toLowerCase();
  const sagKeywords = [
    'absen', 'hadir', 'jadwal', 'guru', 'kelas', 'mapel', 'mata pelajaran', 'pelajaran',
    'wajah', 'kamera', 'riwayat', 'rekap', 'login', 'sag', 'sistem', 'aplikasi',
    'terlambat', 'pulang', 'masuk', 'jam', 'admin', 'kepsek', 'kepala sekolah',
    'akun', 'password', 'error', 'gagal', 'bantuan', 'cara', 'mengajar', 'terdaftar', 'data',
    'fitur', 'fungsi', 'bisa apa', 'siapa', 'apa', 'kapan', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'
  ];
  
  const isSagRelated = sagKeywords.some(kw => lowerMessage.includes(kw));
  const isGreeting = /^(halo|hai|hey|pagi|siang|sore|malam|assalamualaikum|bantu|tolong|tes|test|siapa|apa)/.test(lowerMessage) && lowerMessage.length < 50;

  const blacklistRegex = /(sepak bola|olahraga|resep|masakan|politik|presiden|pemilu|game|majapahit|cuaca|puisi|lagu|juara|rumus matematika|soal matematika|perkalian|pembagian|kalkulus|coding|skrip|script|html|php|css|javascript|buatkan script|buatkan kode)/i;
  
  const hasBlacklist = blacklistRegex.test(lowerMessage);

  if (hasBlacklist || (!isSagRelated && !isGreeting && lowerMessage.length > 20)) {
    return res.json({
      reply: 'Maaf, saya hanya dapat membantu pertanyaan yang berkaitan dengan penggunaan fitur Sistem Absensi Guru (SAG), seperti absensi, jadwal mengajar, riwayat kehadiran, data guru, kelas, mata pelajaran, dan panduan sistem SAG.'
    });
  }

  // Format context to text
  let contextText = '';
  if (Object.keys(dataContext).length > 0) {
    contextText = 'Data Database Saat Ini:\n';
    for (const [key, value] of Object.entries(dataContext)) {
      contextText += `- ${key.replace(/_/g, ' ')}: ${value}\n`;
    }
  }

  // System Prompt Konsisten untuk Seluruh Provider (SAG Context & Rules)
  const systemPrompt = `Kamu adalah Asisten AI resmi Sistem Absensi Guru (SAG).
Jawab pertanyaan pengguna secara singkat, langsung, dan sesuai data SAG. Jangan yapping. Jangan memberikan pembukaan, penutup, atau informasi tambahan yang tidak diminta. Untuk pertanyaan sederhana gunakan satu kalimat. Untuk pertanyaan daftar tampilkan hanya daftar yang diminta. Untuk pertanyaan database gunakan data aktual dari SAG dan jangan mengarang data.

ATURAN GAYA JAWABAN (WAJIB DIIKUTI):
1. Menjawab LANGSUNG ke inti pertanyaan pengguna.
2. Gunakan kalimat sesingkat mungkin (maksimal 1-5 baris, kecuali daftar panjang).
3. DILARANG KERAS memberikan pembukaan basa-basi atau penutup ramah tamah seperti:
   - "Baik, saya akan membantu..."
   - "Terima kasih atas pertanyaannya..."
   - "Silakan..."
   - "Jika Anda membutuhkan bantuan lebih lanjut..."
   - "Ada hal lain yang bisa dibantu?"
4. DILARANG mengulang atau memparafrase pertanyaan pengguna.
5. DILARANG memberikan penjelasan panjang jika tidak diminta eksplisit.
6. DILARANG memberikan informasi tambahan yang tidak ditanyakan.
7. Jika jawabannya berupa daftar, tampilkan daftar saja (gunakan bullet point '-' tanpa paragraf pembuka/penutup).
8. Tetap gunakan Bahasa Indonesia yang baku dan tepat.

CONTOH FORMAT JAWABAN:
- Pertanyaan identitas: "siapa saya?" -> Jawab: "Anda adalah ${roleContext === 'admin' ? 'Admin' : (roleContext === 'kepala_sekolah' ? 'Kepala Sekolah' : 'Guru')}."
- Pertanyaan role: "apa role saya?" -> Jawab: "${roleContext === 'admin' ? 'Admin' : (roleContext === 'kepala_sekolah' ? 'Kepala Sekolah' : 'Guru')}."
- Pertanyaan jumlah: "ada berapa guru yang belum absen?" -> Jawab: "X guru."
- Pertanyaan status absensi pribadi:
  * Jika sudah absen: "Sudah absen pukul [HH:MM]."
  * Jika belum: "Belum absen hari ini."
- Pertanyaan daftar:
  Guru belum absen:
  - Nama Guru 1
  - Nama Guru 2
- Pertanyaan jadwal hari ini:
  Jadwal hari ini:
  - 07:00 — Matematika — X IPA 1
  - 09:00 — Informatika — XI IPA 2
- Jika data kosong / tidak ada:
  * "Semua guru sudah absen."
  * "Tidak ada guru yang terlambat."
  * "Tidak ada guru yang alpa."
  * "Tidak ada jadwal mengajar untuk hari ini."
- Jika data tidak ditemukan: "Data tidak ditemukan."

ATURAN DATA ABSENSI SAG:
- Bedakan status: Sudah Absen, Terlambat, Belum Absen, Alpa/Tidak Hadir.
- "siapa yang belum absen?": Tampilkan HANYA guru dengan status Belum Absen.
- "siapa yang alpa?": Tampilkan HANYA guru dengan status Alpa/Tidak Hadir.
- "siapa yang terlambat?": Tampilkan HANYA guru dengan status Terlambat.
- Jangan menampilkan query SQL.
- DILARANG membuat kode program (PHP, JS, SQL, HTML). Jika diminta coding, jawab singkat: "Saya adalah asisten sistem SAG, tidak melayani pembuatan kode."
- DILARANG menjawab topik di luar sekolah/SAG (resep, gosip, game, dll). Jawab singkat: "Pertanyaan di luar topik Sistem Absensi Guru (SAG)."

Peran pengguna saat ini: ${roleContext}.

${contextText}`;

  // ─── Urutan Eksekusi Dinamis (Primary -> Backup 1 -> Backup 2 ...) ─
  const activeConfigs = configs.filter(c => c.is_active && Boolean(c.apiKey));

  // Sort: yang is_primary paling depan, sisanya berurutan
  activeConfigs.sort((a, b) => {
    if (a.is_primary && !b.is_primary) return -1;
    if (!a.is_primary && b.is_primary) return 1;
    return a.id - b.id;
  });

  if (activeConfigs.length === 0) {
    return res.json({
      status: 'error',
      reply: 'Layanan AI belum dapat digunakan karena belum ada konfigurasi API Key yang aktif di Dashboard Admin.'
    });
  }

  const lastErrors = [];

  for (let i = 0; i < activeConfigs.length; i++) {
    const item = activeConfigs[i];
    const provName = item.provider === 'gemini' ? 'Google Gemini' : 'Groq';
    if (i > 0) {
      console.warn(`[AI FALLBACK] Mencoba provider cadangan ${i} (${provName} - ${item.model})...`);
    }

    let result;
    if (item.provider === 'gemini') {
      result = await callGeminiInstance(message, systemPrompt, item.apiKey, item.model);
    } else {
      result = await callGroqInstance(message, systemPrompt, item.apiKey, item.model);
    }

    if (result && result.success) {
      if (i > 0) {
        console.log(`[AI FALLBACK] Berhasil mendapatkan respon dari cadangan: ${provName}.`);
      }
      return res.json({
        reply   : result.text,
        provider: item.provider
      });
    }

    console.warn(`[AI WARN] Provider ${provName} (${item.model}) gagal [${result.errorType}: ${result.errorDetail}].`);
    lastErrors.push(`${provName} (${item.model}): ${result.errorDetail || result.errorType}`);
  }

  // Jika seluruh provider dalam antrian gagal
  console.error(`[AI ERROR] Seluruh provider cadangan gagal. Detail:`, lastErrors.join(' | '));

  return res.json({
    status : 'error',
    reply  : 'Maaf, layanan asisten AI sementara tidak tersedia karena sedang mengalami gangguan atau pembatasan kuota pada seluruh provider. Silakan coba kembali beberapa saat lagi.'
  });
});

// ─── Error Handler ───────────────────────────────────────────
app.use((err, req, res, next) => {
  const safeMsg = sanitizeText(err.message);
  console.error('[UNCAUGHT ERROR]', safeMsg);
  res.status(500).json({
    status  : 'error',
    message : 'Terjadi kesalahan pada server backend chatbot.'
  });
});

// ─── Start Server ─────────────────────────────────────────────
app.listen(PORT, () => {
  const configs = getAllAiConfigs();
  const activeConfigs = configs.filter(c => c.is_active);
  console.log('============================================================');
  console.log('  SAG Chatbot Backend (Multi-Provider Dynamic Fallback) aktif');
  console.log('  Port           : ' + PORT);
  console.log('  Active AI Keys : ' + activeConfigs.length + ' dari ' + configs.length + ' total');
  console.log('  Endpoint       : POST http://localhost:' + PORT + '/api/chat');
  console.log('============================================================');
});
