-- Esquema del motor de suscripciones
-- Ejecutar contra una base de datos MySQL vacia (ver README para el comando).

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    document VARCHAR(50) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customers_email (email),
    UNIQUE KEY uq_customers_document (document)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    price DECIMAL(12,2) NOT NULL,
    periodicity ENUM('mensual','anual') NOT NULL,
    status ENUM('activa','pausada','cancelada') NOT NULL DEFAULT 'activa',
    -- Momento del ultimo cobro EXITOSO. Se usa junto con la periodicidad
    -- para calcular cuando corresponde el siguiente cobro.
    last_charge_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subscriptions_customer FOREIGN KEY (customer_id)
        REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_subscriptions_customer (customer_id),
    INDEX idx_subscriptions_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS charge_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT UNSIGNED NOT NULL,
    -- Agrupa los hasta 3 intentos de un mismo ciclo de cobro.
    cycle_started_at DATETIME NOT NULL,
    attempt_number TINYINT UNSIGNED NOT NULL,
    status ENUM('pendiente','exitoso','fallido') NOT NULL DEFAULT 'pendiente',
    gateway_response ENUM('aprobado','rechazado','timeout') NULL,
    attempted_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attempts_subscription FOREIGN KEY (subscription_id)
        REFERENCES subscriptions(id) ON DELETE CASCADE,
    INDEX idx_attempts_subscription (subscription_id),
    INDEX idx_attempts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Guarda el reloj simulado (ver App\Support\Clock) y cualquier otra
-- configuracion simple de la app como pares clave/valor.
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (`key`, `value`) VALUES ('clock_offset_seconds', '0')
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
