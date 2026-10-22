# 🇽🇰 Noteria Elektronike - Professional Kosovo Government Portal

## 📋 Overview
A professional, secure, and user-friendly notary reservation system for the Republic of Kosovo, featuring authentic government portal design and advanced functionality.

## ✨ Professional Features

### 🎨 **Modern Government Design**
- **Authentic Kosovo Government Styling**: Official color scheme (#003366 blue, #c49a6c gold)
- **Kosovo Flag Integration**: Professional flag design with CSS styling
- **Responsive Design**: Mobile-first approach with government standards
- **Professional Typography**: Inter & Merriweather fonts for optimal readability
- **Accessibility Compliant**: WCAG 2.1 AA standards with proper ARIA labels

### 🔒 **Security & Performance**
- **CSRF Protection**: Advanced token-based protection
- **Input Validation**: Real-time client and server-side validation
- **IBAN Validation**: Kosovo-specific IBAN format validation
- **Holiday Validation**: Automatic Kosovo public holiday detection
- **Business Hours**: 08:00-16:00 validation with weekend blocking
- **Local Assets**: CDN resources hosted locally to avoid tracking prevention

### 💳 **Payment Integration**
- **Multi-Bank Support**: Integration with major Kosovo banks
- **Tinky Payment**: Fast payment system with real-time validation
- **Payment Status Tracking**: Real-time payment status updates
- **Secure Transactions**: 256-bit SSL encryption

### 🌐 **Multi-Language Support**
- **Albanian (Shqip)**: Primary language
- **Serbian (Српски)**: Full translation support
- **English**: International accessibility

### 📱 **Advanced User Experience**
- **Progressive Web App Ready**: Service worker support
- **Form Persistence**: Auto-save form data in sessionStorage
- **Real-time Validation**: Instant feedback on form inputs
- **Loading States**: Professional loading indicators
- **Error Handling**: Comprehensive error messages with contact info

## 🏗️ **Code Architecture**

### **File Structure**
```
noteria/
├── assets/
│   ├── css/
│   │   └── government-portal.css    # Professional CSS framework
│   ├── js/
│   │   └── government-portal.js     # Interactive functionality
│   ├── bootstrap/                   # Local Bootstrap 5.3.2
│   └── fontawesome/                 # Local Font Awesome 6.4.0
├── reservation.php                  # Main application file
├── config.php                       # Database configuration
└── README.md                        # This documentation
```

### **CSS Framework Features**
- **CSS Custom Properties**: Comprehensive design system
- **Component Library**: Reusable UI components
- **Responsive Grid**: Professional layout system
- **Animation System**: Smooth transitions and micro-interactions
- **Print Styles**: Government document printing support

### **JavaScript Architecture**
- **Modular Design**: Organized into logical modules
- **Event Delegation**: Efficient event handling
- **Form Validation**: Real-time validation with feedback
- **Payment Processing**: Secure payment handling
- **Accessibility**: Keyboard navigation and screen reader support

## 🚀 **Getting Started**

### **Prerequisites**
- PHP 8.0+
- MySQL 5.7+
- Apache/Nginx web server
- SSL certificate for production

### **Installation**
1. **Clone Repository**
   ```bash
   git clone https://github.com/kosovo-government/noteria-portal.git
   cd noteria-portal
   ```

2. **Database Setup**
   ```sql
   CREATE DATABASE noteria_ks;
   -- Import database schema from sql/ directory
   ```

3. **Configuration**
   ```php
   // config.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'noteria_ks');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

4. **File Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 logs/
   ```

### **Development Server**
```bash
php -S localhost:8000
# Visit: http://localhost:8000/reservation.php
```

## 📊 **Key Components**

### **Government Header**
```html
<header class="gov-header">
    <div class="kosovo-flag"></div>
    <h1>NOTERIA ELEKTRONIKE</h1>
    <p>Republika e Kosovës - Ministria e Drejtësisë</p>
</header>
```

### **Professional Forms**
```html
<div class="gov-form-group">
    <label class="gov-form-label">Service Type</label>
    <select class="gov-form-select" required>
        <option>Notary Services</option>
    </select>
</div>
```

### **Payment Integration**
```html
<div class="gov-tinky-form">
    <input type="text" name="payer_iban" class="gov-form-control">
    <button class="gov-btn gov-btn-tinky">PAY NOW</button>
</div>
```

## 🔧 **Configuration Options**

### **Environment Variables**
```php
// Production settings
define('ENVIRONMENT', 'production');
define('DEBUG_MODE', false);
define('LOG_ERRORS', true);
```

### **Payment Configuration**
```php
// Bank API endpoints
define('BANK_API_URL', 'https://api.banka-ks.com');
define('TINKY_API_KEY', 'your_tinky_api_key');
```

## 📈 **Performance Optimizations**

- **Asset Minification**: CSS and JS minified for production
- **Lazy Loading**: Images and components loaded on demand
- **Caching**: Browser caching with proper headers
- **CDN Integration**: Local CDN for static assets
- **Database Indexing**: Optimized queries with proper indexing

## 🛡️ **Security Features**

- **CSRF Protection**: Token-based protection on all forms
- **XSS Prevention**: Input sanitization and output escaping
- **SQL Injection Protection**: Prepared statements
- **Session Security**: Secure session handling
- **File Upload Security**: Type and size validation
- **Rate Limiting**: API rate limiting implementation

## 📱 **Mobile Responsiveness**

- **Breakpoint System**: Professional responsive breakpoints
- **Touch Optimization**: Mobile-first touch interactions
- **Form Adaptation**: Mobile-optimized form layouts
- **Navigation**: Collapsible mobile navigation

## 🌍 **Internationalization**

### **Supported Languages**
- 🇽🇰 **Albanian (sq)**: Primary interface language
- 🇷🇸 **Serbian (sr)**: Full Serbian translation
- 🇬🇧 **English (en)**: International accessibility

### **Translation System**
```php
$labels = [
    'sq' => ['title' => 'Rezervo Terminin'],
    'sr' => ['title' => 'Rezervišite svoj termin'],
    'en' => ['title' => 'Book Your Appointment']
];
```

## 🎯 **Quality Assurance**

### **Testing**
- **Unit Tests**: PHP unit testing with PHPUnit
- **Integration Tests**: API endpoint testing
- **Browser Testing**: Cross-browser compatibility
- **Accessibility Testing**: WCAG compliance validation

### **Code Quality**
- **PSR Standards**: PHP Standards Recommendations
- **Code Documentation**: Comprehensive inline documentation
- **Version Control**: Git with semantic versioning
- **Code Reviews**: Mandatory peer code reviews

## 📞 **Support & Contact**

### **Technical Support**
- **Email**: support@noteria-rks.gov.net
- **Phone**: +383 38 200 100
- **Website**: https://noteria-ks.org

### **Government Contact**
- **Ministry**: Ministria e Drejtësisë
- **Address**: Prishtinë, Kosovë
- **Phone**: +383 38 200 000

## 📄 **License**

This project is developed for the Government of Kosovo and is subject to government licensing terms.

## 🙏 **Credits**

- **Design**: Kosovo Government Design System
- **Development**: Professional PHP Development Team
- **Icons**: Font Awesome 6.4.0
- **Framework**: Bootstrap 5.3.2
- **Fonts**: Google Fonts (Inter, Merriweather)

---

**🇽🇰 Built with ❤️ for the Republic of Kosovo**
