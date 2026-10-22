import express from 'express';
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
dotenv.config();

const app = express();
app.use(express.json());

const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'noteria',
});

app.post('/login', async (req, res) => {
  const { email, password } = req.body;
  if (!email || !password) {
    return res.status(400).json({ error: 'Email dhe password janë të detyrueshme.' });
  }
  try {
    const [rows] = await pool.query('SELECT id, emri, mbiemri, roli, password FROM users WHERE email = ?', [email]);
    if (rows.length === 0) {
      return res.status(401).json({ error: 'Email ose password i pasaktë.' });
    }
    const user = rows[0];
    // Kontrollo password-in (nëse është i hash-uar përdor bcrypt.compare)
    if (user.password !== password) {
      return res.status(401).json({ error: 'Email ose password i pasaktë.' });
    }
    res.json({ success: true, user: { id: user.id, emri: user.emri, mbiemri: user.mbiemri, roli: user.roli } });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.listen(3000, () => {
  console.log('MCP serveri është aktiv në portin 3000');
});
