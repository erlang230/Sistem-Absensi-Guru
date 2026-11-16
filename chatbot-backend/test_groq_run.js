const fs = require('fs');
const path = require('path');

const jsonPath = path.join(__dirname, 'ai_config.json');
const configs = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
const primary = configs.find(c => c.is_primary && c.is_active);

async function testGroq() {
  const apiKey = primary.apiKey;
  const model = primary.model;
  const systemPrompt = "Kamu adalah Asisten AI resmi Sistem Absensi Guru (SAG). Jawab pertanyaan pengguna secara singkat, langsung, dan sesuai data SAG. Jangan yapping.";
  const message = "siapa saya?";

  console.log('Sending to Groq with model:', model);
  try {
    const response = await fetch('https://api.groq.com/openai/v1/chat/completions', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        model: model,
        messages: [
          { role: 'system', content: systemPrompt },
          { role: 'user', content: message }
        ],
        temperature: 0.2,
        max_tokens: 300
      })
    });
    console.log('Status:', response.status);
    const data = await response.json();
    console.log('Data:', JSON.stringify(data, null, 2));
  } catch (e) {
    console.error('Fetch error:', e);
  }
}

testGroq();

