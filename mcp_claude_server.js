
import express from 'express';
import axios from 'axios';
import dotenv from 'dotenv';
dotenv.config();
const app = express();
app.use(express.json());
const CLAUDE_API_KEY = process.env.ANTHROPIC_API_KEY;

app.post('/claude', async (req, res) => {
  const { prompt } = req.body;
  try {
    const response = await axios.post(
      'https://api.anthropic.com/v1/messages',
      {
        model: 'claude-3-opus-20240229', // ose modeli që ke akses
        max_tokens: 1024,
        messages: [{ role: 'user', content: prompt }]
      },
      {
        headers: {
          'x-api-key': CLAUDE_API_KEY,
          'anthropic-version': '2023-06-01',
          'content-type': 'application/json'
        }
      }
    );
    res.json(response.data);
  } catch (error) {
    res.status(500).json({ error: error.message, details: error.response?.data });
  }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Serveri MCP + Claude u nis në portën ${PORT}`);
});
