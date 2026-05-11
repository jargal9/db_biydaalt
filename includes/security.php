<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ROLES = ['Admin', 'Staff', 'Customer'];
const ORDER_STATUSES = ['Pending', 'Processing', 'Completed', 'Cancelled'];
const DELIVERY_STATUSES = ['Pending', 'In Progress', 'Delivered', 'Cancelled'];
const PAYMENT_METHODS = ['Cash', 'Card', 'Mobile', 'Bank'];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function cleanText($value, $maxLength = 100, $allowEmpty = false) {
    $value = trim((string)$value);
    if ($value === '') {
        return $allowEmpty ? '' : null;
    }
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length > $maxLength) {
        return null;
    }
    if (hasSqlInjectionPattern($value)) {
        return null;
    }
    return $value;
}

function cleanInt($value, $min = 1, $max = PHP_INT_MAX) {
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        return null;
    }
    $value = (int)$value;
    if ($value < $min || $value > $max) {
        return null;
    }
    return $value;
}

function cleanEnum($value, array $allowed) {
    $value = trim((string)$value);
    return in_array($value, $allowed, true) ? $value : null;
}

function hasSqlInjectionPattern($value) {
    $value = (string)$value;
    if (strlen($value) > 255) {
        return true;
    }

    $patterns = [
        "/'\\s*or\\s*'?\\d+'?\\s*=\\s*'?\\d+'?/i",
        "/\\bor\\s+1\\s*=\\s*1\\b/i",
        "/\\bunion\\s+select\\b/i",
        "/\\b(select|insert|update|delete|drop|alter|truncate)\\b.*(--|#|\\/\\*)/i",
        "/(;|--|#|\\/\\*)/",
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value)) {
            return true;
        }
    }
    return false;
}

function passwordNeedsHash($password) {
    return !preg_match('/^\\$2y\\$|^\\$argon2/i', (string)$password);
}

function verifyUserPassword($plainPassword, $storedPassword) {
    if (passwordNeedsHash($storedPassword)) {
        return hash_equals((string)$storedPassword, (string)$plainPassword);
    }
    return password_verify($plainPassword, $storedPassword);
}

function hashUserPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function clientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? 'cli';
}

function logSecurityEvent($pdo, $eventType, $username = null, $success = false, $details = null) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO Security_Log (event_type, username, user_ID, ip_address, success, details, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            cleanText($eventType, 50) ?? 'security_event',
            $username ? substr((string)$username, 0, 100) : null,
            $_SESSION['user_ID'] ?? null,
            clientIp(),
            $success ? 1 : 0,
            $details ? substr((string)$details, 0, 255) : null,
            date('Y-m-d H:i:s')
        ]);
    } catch (Throwable $e) {
        error_log('Security log failed: ' . $e->getMessage());
    }
}

function validatePostedFields($pdo, array $values, $username = null) {
    foreach ($values as $key => $value) {
        if (stripos((string)$key, 'password') !== false) {
            continue;
        }
        if (is_array($value)) {
            if (!validatePostedFields($pdo, $value, $username)) {
                return false;
            }
            continue;
        }
        if (hasSqlInjectionPattern($value)) {
            logSecurityEvent($pdo, 'sql_injection_pattern', $username, false, (string)$value);
            return false;
        }
    }
    return true;
}
?>
