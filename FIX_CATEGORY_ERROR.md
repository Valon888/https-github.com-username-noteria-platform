# 🔧 Поправка на Грешка: Колона 'category' не е пронајдена

## 📋 Проблемот
```
❌ SQLSTATE[42S22]: Column not found: 1054 Unknown column 'category' in 'field list'
```

Ова значи дека табелата `advertisers` не ја има колона `category`.

---

## ✅ Решение - 3 Чекори

### 1️⃣ Проверете ја Структурата на Табелата
Отворете го овој URL во шфлетувачот:
```
http://localhost/noteria/check_table_structure.php
```

Ќе видите точна листа на сите колони што постојат во табелата.

**Пример на излез:**
```
Field              | Type                | Null | Key
company_name        | varchar(255)       | NO   | 
email              | varchar(255)       | NO   | UNI
phone              | varchar(20)        | YES  | 
website            | varchar(255)       | YES  | 
description        | longtext           | YES  | 
created_at         | timestamp          | YES  | 
```

### 2️⃣ Додајте ја Колона 'category' (ако е потребна)
Ако видите дека `category` не е во листата, кликнете овде:
```
http://localhost/noteria/fix_database.php
```

Ова ќе додаде ги сите недостасушки колони автоматски.

### 3️⃣ Тестирајте го Регистрирањето
Сега пробајте да регистрирате нов рекламуес:
```
http://localhost/noteria/test_advertiser_form.php
```

---

## 🔄 Што се промени во Кодот

### `become-advertiser.php` - Флексибилен INSERT
Кодот е сега направен да:
1. **Прво** пробува да вави со `category` колона
2. **Ако дава грешка**, автоматски се опитува **без** `category`
3. Ова го олеснува функционирањето независно од структурата на табелата

```php
try {
    // Pробај со category
    $stmt = $pdo->prepare("INSERT INTO advertisers 
                          (company_name, email, phone, website, category, ...) 
                          VALUES (...)");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'category') !== false) {
        // Ако грешка за category, пробај без нея
        $stmt = $pdo->prepare("INSERT INTO advertisers 
                              (company_name, email, phone, website, ...) 
                              VALUES (...)");
    }
}
```

---

## 📊 Препорачана Структура на Табелата

Ако сакате да ја поправите табелата правилно, ево препорачаната структура:

```sql
CREATE TABLE IF NOT EXISTS advertisers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    website VARCHAR(255),
    category VARCHAR(100),
    description LONGTEXT,
    business_registration VARCHAR(100),
    subscription_status VARCHAR(50) DEFAULT 'pending',
    subscription_plan VARCHAR(50),
    payment_status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (subscription_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔗 Корисни Скриптови

| URL | Функција |
|-----|----------|
| `/check_table_structure.php` | Проверка на структурата на табелата |
| `/fix_database.php` | Додавање на недостасушки колони |
| `/test_advertiser_form.php` | Тестирање на формата |
| `/diagnostic.php` | Целосна дијагностика |
| `/become-advertiser.php` | Актуелната форма за регистрирање |

---

## 🚀 Следни Чекори

1. ✅ Отворете `/check_table_structure.php`
2. ✅ Отворете `/fix_database.php` (ако е потребно)
3. ✅ Отворете `/test_advertiser_form.php` (за тестирање)
4. ✅ Користите го `/become-advertiser.php` (актуелната форма)

---

## 💡 Напомена

Коддва сада е написан така што **автоматски се справува** со случаи каде колони недостасуваат. 

- Ако табелата нема `category`, формата и даље ќе работи
- Се вива автоматски во `category` или се користи без нея
- За користење на целосната функционалност, користите `/fix_database.php`

---

## ❓ Вишеу Прашања?

Ако проблемите продолжуваат:
1. Проверете ги PHP error logs
2. Проверете ги MySQL error logs
3. Контактирајте го администраторот
