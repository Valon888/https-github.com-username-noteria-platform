# API Reference

Kjo është dokumentacioni për API-në e Noteria.

## Autentikimi
Të gjitha endpoint-et kthejnë përgjigje në formatin JSON.

---

## POST /api/login.php
**Përshkrimi:** Autentifikon përdoruesin dhe kthen një token.

**Body (JSON):**
```
{
  "email": "user@example.com",
  "password": "password123"
}
```
**Response (JSON):**
- Sukses:
```
{
  "success": true,
  "message": "Login successful",
  "token": "example-jwt-token"
}
```
- Dështim:
```
{
  "success": false,
  "message": "Invalid credentials"
}
```

---

## POST /api/register.php
**Përshkrimi:** Regjistron një përdorues të ri.

**Body (JSON):**
```
{
  "email": "user@example.com",
  "password": "password123"
}
```
**Response (JSON):**
- Sukses:
```
{
  "success": true,
  "message": "User registered",
  "user": {
    "email": "user@example.com",
    "password": "password123"
  }
}
```
- Dështim:
```
{
  "success": false,
  "message": "Email and password required"
}
```

---

## GET /api/users.php
**Përshkrimi:** Kthen listën e përdoruesve (kërkon autentikim me token).

**Headers:**
```
Authorization: Bearer example-jwt-token
```
**Response (JSON):**
- Sukses:
```
{
  "success": true,
  "users": [
    { "id": 1, "email": "user@example.com" },
    { "id": 2, "email": "admin@example.com" }
  ]
}
```
- Dështim:
```
{
  "success": false,
  "message": "Unauthorized"
}
```
