-- Create table to track service acceptances and declines
CREATE TABLE IF NOT EXISTS service_acceptances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    service_id INT NOT NULL,
    customer_id INT NOT NULL,
    provider_id INT NOT NULL,
    status ENUM('accepted', 'declined', 'pending') DEFAULT 'pending',
    accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_service_response (chat_id, service_id),
    KEY idx_status (status),
    KEY idx_customer (customer_id),
    KEY idx_provider (provider_id)
);
