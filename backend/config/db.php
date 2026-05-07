<?php
// ============================================================
// Database Configuration
// Student Centre Food Ordering Application
// ============================================================

function configValue(string $key, string $default = ''): string {
    static $fileEnv = null;
    if ($fileEnv === null) {
        $envPath = dirname(__DIR__, 2) . '/.env';
        $fileEnv = is_readable($envPath) ? parse_ini_file($envPath, false, INI_SCANNER_RAW) : [];
    }

    $runtime = getenv($key);
    if ($runtime !== false && $runtime !== '') return $runtime;
    return isset($fileEnv[$key]) && $fileEnv[$key] !== '' ? (string)$fileEnv[$key] : $default;
}

define('APP_ENV', configValue('APP_ENV', 'development'));

// ------ Timezone (audit: fix server-time vs Africa/Gaborone mismatch) -----
define('TIMEZONE', configValue('TIMEZONE', 'Africa/Gaborone'));
date_default_timezone_set(TIMEZONE);

define('DB_HOST',    configValue('DB_HOST',    'localhost'));
define('DB_USER',    configValue('DB_USER',    'student_food_app'));
define('DB_PASS',    configValue('DB_PASS',    ''));
define('DB_NAME',    configValue('DB_NAME',    'student_food_app'));
define('DB_CHARSET', configValue('DB_CHARSET', 'utf8mb4'));

// ------ JWT (audit: fail-fast if secret is weak/missing in production) ----
define('JWT_SECRET', configValue('JWT_SECRET', 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING'));
define('JWT_EXPIRY', (int)configValue('JWT_EXPIRY', '3600'));

if (APP_ENV !== 'development' && strlen(JWT_SECRET) < 32) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server misconfiguration: JWT_SECRET is missing or too short.']);
    exit;
}

// ------ CORS & payments ----------------------------------------------------
define('CORS_ALLOWED_ORIGINS',      configValue('CORS_ALLOWED_ORIGINS', 'http://localhost,http://127.0.0.1'));
define('PAYMENT_PROVIDER_ENABLED',  configValue('PAYMENT_PROVIDER_ENABLED', 'false'));
define('DEFAULT_DELIVERY_FEE',      configValue('DEFAULT_DELIVERY_FEE', '5.00'));

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
            exit;
        }
    }
    return $pdo;
}
