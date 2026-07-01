-- Add bank details to user profiles for organisers
ALTER TABLE users
ADD COLUMN bank_name VARCHAR(100) NULL,
ADD COLUMN bank_account_no VARCHAR(50) NULL,
ADD COLUMN bank_account_holder VARCHAR(150) NULL,
ADD COLUMN stripe_connect_id VARCHAR(100) NULL;

-- Create payments logging table
CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'MYR',
    stripe_charge_id VARCHAR(100) NULL,
    status ENUM('pending', 'completed', 'refunded', 'failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
