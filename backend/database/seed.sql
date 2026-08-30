-- Datos de prueba. Ejecutar despues de schema.sql.

INSERT INTO customers (name, email, document, phone) VALUES
    ('Ana Torres', 'ana.torres@example.com', '1001234567', '3001234567'),
    ('Carlos Ramirez', 'carlos.ramirez@example.com', '1007654321', '3007654321'),
    ('Bodytech Prueba', 'prueba@example.com', '900111222', '3009998888');

-- Suscripcion sin ningun cobro todavia: al correr el motor por primera vez
-- deberia generar su intento #1 de inmediato.
INSERT INTO subscriptions (customer_id, name, description, price, periodicity, status, last_charge_at) VALUES
    (1, 'Plan Fit Mensual', 'Acceso a sede unica, horario general', 89900.00, 'mensual', 'activa', NULL);

-- Suscripcion con un cobro exitoso reciente: NO deberia cobrarse de nuevo
-- hasta que se cumpla el mes (util para probar que el motor no duplica cobros).
INSERT INTO subscriptions (customer_id, name, description, price, periodicity, status, last_charge_at) VALUES
    (1, 'Plan Nutricion Anual', 'Seguimiento nutricional mensual', 720000.00, 'anual', 'activa', NOW());

-- Suscripcion pausada por reintentos agotados en el pasado, para ver el
-- historial de intentos fallidos en el detalle del frontend.
INSERT INTO subscriptions (customer_id, name, description, price, periodicity, status, last_charge_at) VALUES
    (2, 'Plan Fit Premium', 'Acceso multisede + clases grupales', 149900.00, 'mensual', 'pausada', NULL);

INSERT INTO charge_attempts (subscription_id, cycle_started_at, attempt_number, status, gateway_response, attempted_at, resolved_at) VALUES
    (3, DATE_SUB(NOW(), INTERVAL 3 DAY), 1, 'fallido', 'rechazado', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
    (3, DATE_SUB(NOW(), INTERVAL 2 DAY), 2, 'fallido', 'rechazado', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (3, DATE_SUB(NOW(), INTERVAL 1 DAY), 3, 'fallido', 'timeout', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Cuarto cliente/suscripcion cancelada, para probar el filtro por estado.
INSERT INTO customers (name, email, document, phone) VALUES
    ('Laura Gomez', 'laura.gomez@example.com', '1002223333', '3002223333');

INSERT INTO subscriptions (customer_id, name, description, price, periodicity, status, last_charge_at) VALUES
    (4, 'Plan Fit Mensual', 'Cancelado por el cliente', 89900.00, 'mensual', 'cancelada', NULL);
