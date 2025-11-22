-- Fshi tentativat e vjetra të login (më të vjetra se 1 ditë)
DELETE FROM admin_login_attempts WHERE attempt_time < (NOW() - INTERVAL 1 DAY);

-- Fshi kërkesat e vjetra të API (më të vjetra se 1 orë)
DELETE FROM api_rate_limits WHERE request_time < (NOW() - INTERVAL 1 HOUR);
