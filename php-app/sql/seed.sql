-- VoltSpace seed data
USE `voltSpace_db`;

-- Demo user (password stored as SHA-256 of 'Demo123!'; app migrates to password_hash on first login)
INSERT INTO users (name, email, password_hash, created_at)
VALUES ('Demo User', 'demo@voltspace.local', SHA2('Demo123!', 256), NOW());

SET @uid := (SELECT id FROM users WHERE email='demo@voltspace.local');

-- Home
INSERT INTO homes (user_id, name, address, created_at)
VALUES (@uid, 'Demo Home', '123 Energy St', NOW());

SET @home := LAST_INSERT_ID();

-- Rooms
INSERT INTO rooms (home_id, name, floor, created_at) VALUES
(@home, 'Living', 0, NOW()),
(@home, 'Kitchen', 0, NOW());

SET @living := (SELECT id FROM rooms WHERE home_id=@home AND name='Living');
SET @kitchen := (SELECT id FROM rooms WHERE home_id=@home AND name='Kitchen');

-- Devices
INSERT INTO devices (room_id, type, name, serial_number, bt_code, state_json, power_w, last_active, created_at) VALUES
(@living, 'light', 'Living Main Light', 'ENR-DEV-1A2B3C', NULL, '{"on": true, "brightness": 80, "base_w": 9}', 9, DATE_SUB(NOW(), INTERVAL 5 HOUR), NOW()),
(@living, 'light', 'Living Lamp', 'ENR-DEV-4D5E6F', NULL, '{"on": false, "brightness": 50, "base_w": 9}', 9, DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW()),
(@living, 'ac', 'Living AC', NULL, 'BT-1234-5678', '{"on": true, "setpoint": 22}', 900, DATE_SUB(NOW(), INTERVAL 7 HOUR), NOW()),
(@kitchen, 'plug', 'Coffee Machine Plug', NULL, 'BT-9999-0001', '{"on": true, "flexible": true}', 1200, DATE_SUB(NOW(), INTERVAL 10 HOUR), NOW());

-- Device logs
INSERT INTO device_logs (device_id, event, payload, created_at) VALUES
((SELECT id FROM devices WHERE name='Living Main Light'), 'toggle', '{"from": false, "to": true}', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
((SELECT id FROM devices WHERE name='Living AC'), 'toggle', '{"from": false, "to": true}', DATE_SUB(NOW(), INTERVAL 7 HOUR)),
((SELECT id FROM devices WHERE name='Coffee Machine Plug'), 'toggle', '{"from": false, "to": true}', DATE_SUB(NOW(), INTERVAL 10 HOUR));

