-- Создание базы данных SQLite для системы учета продаж по промокодам

-- Таблица администраторов
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Таблица промокодов
CREATE TABLE IF NOT EXISTS promo_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(8) UNIQUE NOT NULL,
    status VARCHAR(20) DEFAULT 'unregistered',
    total_sales INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    promo_code_id INTEGER NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    city VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id) ON DELETE CASCADE
);

-- Таблица продаж
CREATE TABLE IF NOT EXISTS sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    promo_code_id INTEGER NOT NULL,
    product_name VARCHAR(500) NOT NULL,
    sale_date DATE NOT NULL,
    quantity INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id) ON DELETE CASCADE
);

-- Таблица истории выдачи промо
CREATE TABLE IF NOT EXISTS promo_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    admin_id INTEGER NOT NULL,
    action_type VARCHAR(20) NOT NULL,
    previous_count INTEGER DEFAULT 0,
    new_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);

-- Таблица для rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    request_count INTEGER DEFAULT 1,
    window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Таблица для восстановления паролей
CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Таблица журнала аудита
CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_type VARCHAR(20) NOT NULL, -- 'admin' или 'doctor'
    user_id INTEGER,
    user_email VARCHAR(255),
    action VARCHAR(100) NOT NULL, -- 'login', 'logout', 'create_promo', 'delete_promo', etc.
    resource_type VARCHAR(50), -- 'promo_code', 'doctor', 'sale', etc.
    resource_id INTEGER,
    details TEXT, -- JSON с дополнительными данными
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Создание первого администратора (пароль: admin123)
INSERT OR IGNORE INTO admins (email, password_hash) VALUES 
('admin@reprosystem.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Создание тестовых промокодов
INSERT OR IGNORE INTO promo_codes (code, status) VALUES 
('TEST001', 'unregistered'),
('TEST002', 'unregistered'),
('TEST003', 'unregistered'),
('DOCTOR1', 'unregistered'),
('DOCTOR2', 'unregistered');

-- Создание тестового врача
INSERT OR IGNORE INTO doctors (promo_code_id, full_name, email, city, password_hash) VALUES 
(1, 'Иванов Иван Иванович', 'doctor@test.ru', 'Москва', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Обновление статуса промокода
UPDATE promo_codes SET status = 'registered' WHERE id = 1;

-- Таблица списаний
CREATE TABLE IF NOT EXISTS deductions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    amount INTEGER NOT NULL,
    deduction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Создание тестовых продаж
INSERT OR IGNORE INTO sales (promo_code_id, product_name, sale_date, quantity) VALUES 
(1, 'Препарат А', '2024-01-15', 5),
(1, 'Препарат Б', '2024-01-20', 3),
(1, 'Препарат В', '2024-02-01', 2);
