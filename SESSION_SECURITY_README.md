# Session & Security Configuration

## Session Timeout

Sistemi tani ka implementuar automatic session timeout për sigurinë.

### Konfigurimi

**Session Timeout Default:** 30 minuta (1800 sekonda)

Për të ndryshuarm timeout-in, përditëso `.env` file:

```env
SESSION_TIMEOUT=1800
```

### Sesi Funksionon

1. Pas çdo request, `last_activity` timestamp përditësohet
2. Nëse përdoruesi qëndron inaktiv më shumë se 30 minuta, sesioni shkatërrohet
3. Përdoruesi redirectohet në login page me mesazh `session_expired`
4. Logging: çdo timeout loggitet në error.log

### Implementation në Pages

Çdo protected page duhet të ketë:

```php
require_once 'config.php';

// Automatic session check (në config.php)
// Nuk duhet të thirrni session_start() - config.php e bën
```

## Security Headers

Sistemi tani vendos following security headers:

### Content-Security-Policy (CSP)

```
Kufizon burimet e JS, CSS, fonts, images
- default-src 'self'
- script-src 'self' trusted domains
- style-src 'self' trusted domains
```

### X-Frame-Options

Kufizon burimet e JS, CSS, fonts, images
- default-src 'self'
- script-src 'self' trusted domains
- style-src 'self' trusted domains
```
DENY - Parandaloj embedding në iframes
```

### X-Content-Type-Options

```
nosniff - Parandaloj MIME type sniffing
```

### X-XSS-Protection

```
1; mode=block - Browser XSS protection
```

### Strict-Transport-Security (HSTS)

```
max-age=31536000 - Force HTTPS për 1 vit
```

### Referrer-Policy

```
strict-origin-when-cross-origin
```

### Permissions-Policy

```
Disable: geolocation, microphone, camera, payment, usb, etc.
```

## Logout

Logout handler është në `logout.php`:

```php
// User logout
header("Location: logout.php");

// Admin logout
header("Location: logout.php?type=admin");
```

Logout process:

1. Logs out activity në error.log
2. Clears session data
3. Destroys session
4. Clears session cookie
5. Redirects to login

## Testing

### Test Session Timeout

1. Login në dashboard
2. Mbilli browser tab nuk bëj asnjë aktivitet
3. Pret 30+ minuta
4. Refresh page
5. Should redirected to login page

### Test Security Headers

```bash
curl -I https://noteria.al/dashboard.php
```

Should show:

```
Content-Security-Policy: ...
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: ...
```

## Functions Available

### Session Helpers

```php
// Initialize secure session (automatic in config.php)
initializeSecureSession();

// Check timeout (automatic in protected pages)
checkSessionTimeout($timeout, $redirect_url);

// Regenerate session ID (call after login)
regenerateSessionId();

// Logout user
logoutUser();

// Get remaining session time
getRemainingSessionTime();      // returns seconds
getRemainingSessionTimeMinutes(); // returns minutes

// Check if session active
isSessionActive();

// Get current user ID
getCurrentUserId();

// Get current admin ID
getCurrentAdminId();
```

## Environment Variables

```env
# Session configuration
SESSION_TIMEOUT=1800                    # 30 minutes
SESSION_COOKIE_SECURE=true              # HTTPS only
SESSION_COOKIE_HTTPONLY=true            # JS cannot access

# App environment
APP_ENV=development                     # or production
APP_DEBUG=false

# Security
DEVELOPER_IP_WHITELIST=127.0.0.1,::1   # IP whitelisting
DEVELOPER_MFA_ENABLED=true              # Force MFA
```

## Troubleshooting

### Session expires too quickly

Check `.env` file:

```env
SESSION_TIMEOUT=1800  # Increase this value (in seconds)
```

### Security headers not showing

Check if running in development:

- CSP headers are NOT sent in development mode (to ease debugging)
- Set `APP_ENV=production` to enable CSP headers

### Users getting logged out

- Check `SESSION_TIMEOUT` value in `.env`
- Check browser cookies are enabled
- Check if HTTPS is properly configured

## Migration Checklist

- [x] Session helper functions created
- [x] Security headers implemented
- [x] Session timeout on protected pages
- [x] Logout handler updated
- [x] .env configuration added
- [x] Logging for timeout/logout events
- [ ] Test in production
- [ ] Update admin documentation

---

**Last Updated:** 17 Nëntor 2025
**Status:** ✅ Priority 2 - MEDIUM (Complete)
