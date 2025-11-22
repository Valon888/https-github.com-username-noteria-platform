-- Drop tables if they exist (optional cleanup)
IF OBJECT_ID('dbo.zyrat', 'U') IS NOT NULL DROP TABLE dbo.zyrat;
IF OBJECT_ID('dbo.payment_logs', 'U') IS NOT NULL DROP TABLE dbo.payment_logs;
IF OBJECT_ID('dbo.api_tokens', 'U') IS NOT NULL DROP TABLE dbo.api_tokens;

-- Create table: zyrat
CREATE TABLE zyrat (
    id INT PRIMARY KEY IDENTITY(1,1),
    emri_noterit VARCHAR(255) NOT NULL,
    vitet_pervoje INT CHECK (vitet_pervoje >= 0),
    numri_punetoreve INT CHECK (numri_punetoreve >= 0),
    gjuhet VARCHAR(255),
    staff_data DATE,
    status VARCHAR(20) CHECK (status IN ('active', 'inactive'))
);

-- Create table: payment_logs
CREATE TABLE payment_logs (
    id INT PRIMARY KEY IDENTITY(1,1),
    payer_name VARCHAR(255) NOT NULL,
    receiver_name VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) CHECK (amount >= 0),
    currency VARCHAR(20),
    method VARCHAR(50),
    status VARCHAR(50),
    retry_count INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    transaction_id VARCHAR(100),
    created_at DATETIME DEFAULT GETDATE()
);

-- Create table: api_tokens
CREATE TABLE api_tokens (
    id INT PRIMARY KEY IDENTITY(1,1),
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME,
    is_active BIT DEFAULT 1
);
