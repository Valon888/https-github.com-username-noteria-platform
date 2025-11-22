# Sistemi i Administratorëve - Noteria

## Tabela 'admins'

Tabela e re `admins` në bazën e të dhënave Noteria përmban të gjithë të dhënat e administratorëve të sistemit.

### Struktura e Tabelës

| Kolona | Lloji | Përshkrimi |
|--------|-------|-----------|
| `id` | INT | ID unike i administratorit (Primary Key) |
| `email` | VARCHAR(255) | Email-i i administratorit (UNIQUE) |
| `password` | VARCHAR(255) | Fjalëkalimi i enkriptuar me bcrypt |
| `emri` | VARCHAR(100) | Emri i plotë i administratorit |
| `status` | ENUM | Statusi (active, inactive, suspended) |
| `role` | ENUM | Roli (super_admin, admin, moderator) |
| `phone` | VARCHAR(20) | Numri i telefonit (opsional) |
| `created_at` | TIMESTAMP | Data e krijimit (auto) |
| `updated_at` | TIMESTAMP | Data e përditësimit të fundit (auto) |
| `last_login` | TIMESTAMP | Koha e kyçjes së fundit (opsional) |
| `is_2fa_enabled` | BOOLEAN | A është aktivizuar 2FA? (default: FALSE) |

### Indekset

- `idx_email`: Indeks për kërkimin e shpejtë pas email-it
- `idx_status`: Indeks për filtrimin sipas statusit
- `idx_created_at`: Indeks për renditjen sipas datës

### Rolet e Administratorit

1. **super_admin** - Përdorues me qasje të plotë të sistemit
2. **admin** - Administrator me qasje të gjerë
3. **moderator** - Moderator me qasje të limituar

### Shembull i Insertimit

```php
<?php
require_once 'config.php';

$email = 'admin@noteria.al';
$password = password_hash('password123', PASSWORD_BCRYPT);
$emri = 'Admin Administratori';

$stmt = $pdo->prepare("INSERT INTO admins (email, password, emri, status, role) 
                      VALUES (?, ?, ?, 'active', 'super_admin')");
$stmt->execute([$email, $password, $emri]);

echo 'Administrator added with ID: ' . $pdo->lastInsertId();
?>
```

### Shembull i Kyçjes

```php
<?php
require_once 'config.php';

$email = 'admin@noteria.al';
$password = 'password123';

$stmt = $pdo->prepare("SELECT id, email, password, emri FROM admins 
                      WHERE email = ? AND status = 'active'");
$stmt->execute([$email]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password'])) {
    // Login i suksesshëm
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name'] = $admin['emri'];
    echo 'Welcome, ' . $admin['emri'];
} else {
    // Login i dështuar
    echo 'Invalid credentials!';
}
?>
```

### Shembull i Përditësimit të Statusit

```php
<?php
require_once 'config.php';

$admin_id = 1;
$new_status = 'inactive';

$stmt = $pdo->prepare("UPDATE admins SET status = ? WHERE id = ?");
$stmt->execute([$new_status, $admin_id]);

echo 'Administrator status updated!';
?>
```

### Shembull i Përditësimit të Fjalëkalimit

```php
<?php
require_once 'config.php';

$admin_id = 1;
$new_password = 'newPassword123!';
$hashed = password_hash($new_password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
$stmt->execute([$hashed, $admin_id]);

echo 'Password updated successfully!';
?>
```

### Shembull i Fshirjes

```php
<?php
require_once 'config.php';

$admin_id = 1;

$stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);

echo 'Administrator deleted!';
?>
```

### Aktivizimi i 2FA

```php
<?php
require_once 'config.php';

$admin_id = 1;

$stmt = $pdo->prepare("UPDATE admins SET is_2fa_enabled = TRUE WHERE id = ?");
$stmt->execute([$admin_id]);

echo '2FA enabled for administrator!';
?>
```

## Administratori i Parazgjedhur

Administratori i parazgjedhur i krijuar gjatë setup-it:

- **Email:** admin@noteria.al
- **Rol:** super_admin
- **Statusi:** active

---

*Dokumentacion i përditësuar: 22 Nëntor 2025*
