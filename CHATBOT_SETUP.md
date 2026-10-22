# ⚙️ Noteria Chatbot - Setup & Configuration Guide

## 🚀 Getting Started

Your chatbot now uses a **secure PHP backend** that handles all API calls safely.

## 🔑 Step 1: Get Your Claude API Key

1. Go to [Anthropic Console](https://console.anthropic.com/)
2. Sign up or log in
3. Navigate to **API Keys** section
4. Create a new API key
5. Copy the key (starts with `sk-ant-v1-...`)

## 🛠️ Step 2: Configure API Key (Choose One Method)

### **Method A: Environment Variables (Recommended)**

#### On Windows (Laragon):

1. Open **Start Menu** → Search for "Environment Variables"
2. Click **"Edit the system environment variables"**
3. Click **"Environment Variables"** button
4. Click **"New..."** under "System variables"
5. Add:
   - **Variable name**: `CLAUDE_API_KEY`
   - **Variable value**: `sk-ant-v1-xxxxxxxxx` (your real key)
6. Click **OK** and restart Apache/PHP

#### Or in `.env` file:

Create file: `d:\Laragon\www\noteria\.env`

```
CLAUDE_API_KEY=sk-ant-v1-YOUR-ACTUAL-API-KEY-HERE
```

Then update `api/chatbot.php` line 18:

```php
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: require_once('.env'));
```

### **Method B: Direct Configuration (Development Only)**

Edit `api/chatbot.php` line 18:

```php
// Change this:
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: 'sk-ant-v1-YOUR-API-KEY-HERE');

// To this (with your real key):
define('CLAUDE_API_KEY', 'sk-ant-v1-YOUR-ACTUAL-API-KEY-HERE');
```

⚠️ **NEVER commit API keys to version control!**

## 📁 File Structure

```
www/noteria/
├── noteria_chatbot.php       ← Main chatbot interface
├── api/
│   └── chatbot.php           ← Secure API backend (NEW)
└── .env                       ← API Key (optional, if using env file)
```

## ✅ Testing

1. Open `noteria_chatbot.php` in your browser
2. Type a message: "Përshëndetje! Çfarë shërbimesh keni?"
3. You should get a response from Claude

If you get an error, check:
- ✅ API key is correct
- ✅ API key is properly set in environment or config
- ✅ Internet connection is active
- ✅ Claude API account has credits

## 🔒 Security Features

✅ **API key hidden** from frontend code
✅ **CORS headers** to prevent unauthorized access
✅ **Input validation** on all requests
✅ **Error messages** without exposing sensitive data
✅ **Session management** for conversation history
✅ **SSL verification** for API calls
✅ **Timeout protection** against hanging requests

## 🐛 Debugging

If you get errors, check the **PHP error log**:

```
Laragon → Menu → Log → PHP Errors
```

Or in VS Code terminal:

```powershell
tail -f d:\Laragon\storage\logs\php\php_errors.log
```

## 📊 API Specifications

- **Endpoint**: `/api/chatbot.php`
- **Method**: `POST`
- **Model**: `claude-3-5-sonnet-20241022`
- **Max Tokens**: `1200`
- **Timeout**: `30 seconds`
- **Rate Limit**: As per your Anthropic account

## 💰 Costs

Claude API usage is measured in **tokens**:
- Each message counts toward your monthly usage
- Pricing starts from ~$3 per 1M input tokens
- Monitor your usage in [Anthropic Console](https://console.anthropic.com/)

Set up **usage limits** to control costs:
1. Go to Anthropic Console
2. Navigate to **Settings** → **Usage Limits**
3. Set a monthly budget

## 🚨 Common Issues & Fixes

### **Error: "API key not configured"**
- ✅ Check if `CLAUDE_API_KEY` is set in environment
- ✅ Verify the key is not the placeholder

### **Error: "cURL error"**
- ✅ Ensure `curl` PHP extension is enabled
- ✅ Check firewall is not blocking API calls
- ✅ Verify SSL certificates are valid

### **Error: "timeout"**
- ✅ Claude API might be slow
- ✅ Try sending a shorter message
- ✅ Check your internet speed

### **Permission Denied Error**
- ✅ Ensure `api/` directory has read/write permissions
- ✅ Check file ownership in Laragon

## 📱 Production Deployment

When deploying to production:

1. **Set environment variables** on your host
2. **Enable HTTPS** (SSL/TLS)
3. **Add rate limiting** to prevent abuse
4. **Set up logging** for support tickets
5. **Monitor costs** with budget alerts
6. **Add authentication** if needed for sensitive users

## 📞 Support

- **Noteria Support**: +383 44 000 000
- **Anthropic Support**: https://support.anthropic.com
- **GitHub Issues**: (if your project is on GitHub)

## ✨ Next Steps

- ✅ Add appointment booking backend
- ✅ Store conversations in database
- ✅ Add user authentication
- ✅ Create admin dashboard
- ✅ Add analytics & reporting

---

**Status**: ✅ Ready for Configuration
**Date**: March 2026
**Version**: 2.1 (Secure Backend Edition)
