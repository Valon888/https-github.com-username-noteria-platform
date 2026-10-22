import express from 'express';
import multer from 'multer';
import mysql from 'mysql2/promise';
import dotenv from 'dotenv';
dotenv.config();

const app = express();
app.use(express.json());

// Konfigurimi i ruajtjes së fotove
const storage = multer.diskStorage({
  destination: function (req, file, cb) {
    cb(null, './uploads/');
  },
  filename: function (req, file, cb) {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    cb(null, uniqueSuffix + '-' + file.originalname.replace(/[^a-zA-Z0-9.]/g, '_'));
  }
});
const upload = multer({ storage: storage });

// Lidhja me MySQL
const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'noteria',
});

app.post('/register', upload.single('photo'), async (req, res) => {
  const { emri, mbiemri, email, password, telefoni } = req.body;
  const photo = req.file ? req.file.filename : null;
  if (!emri || !mbiemri || !email || !password || !telefoni || !photo) {
    return res.status(400).json({ error: 'Të gjitha fushat janë të detyrueshme.' });
  }
  try {
    const [rows] = await pool.query('INSERT INTO users (emri, mbiemri, email, password, telefoni, photo_path) VALUES (?, ?, ?, ?, ?, ?)', [emri, mbiemri, email, password, telefoni, photo]);
    res.json({ success: true, user_id: rows.insertId });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.listen(3000, () => {
  console.log('MCP serveri është aktiv në portin 3000');
});
