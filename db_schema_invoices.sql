-- Struktura e databazës për sistemin e faturimit automatik

-- Tabela për përdoruesit (user)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    surname VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30)
);

-- Tabela për zyrat noteriale
CREATE TABLE notary_offices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255),
    city VARCHAR(100),
    email VARCHAR(150),
    phone VARCHAR(30)
);

-- Tabela për rezervimet
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notary_office_id INT NOT NULL,
    service VARCHAR(150) NOT NULL,
    reservation_date DATETIME NOT NULL,
    payment_method VARCHAR(50), -- p.sh. 'online', 'cash', 'bank'
    payment_status VARCHAR(30), -- p.sh. 'paid', 'pending'
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (notary_office_id) REFERENCES notary_offices(id)
);

-- Tabela për faturat
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    issue_date DATETIME NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id)
);
