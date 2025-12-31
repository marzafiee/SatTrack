<?php
// main config file - handles database connection, sessions, and CSRF stuff
// this gets included in pretty much every page

// load environment variables from .env file
// learned about this recently, pretty useful for keeping secrets out of code
$envCandidates = [
    __DIR__ . '/../.env',
];
foreach ($envCandidates as $envPath) {
    if (!file_exists($envPath)) continue;

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue; // skip comments
        if (!str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // remove quotes if they're there
        $value = trim($value, "\"'");

        if ($key === '') continue;
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }

    break; // found it, stop looking
}

// set up secure session cookies
// learned about CSRF attacks and this helps prevent them
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Strict');
session_start();

// database connection settings from .env
define('DB_HOST', $_ENV['DB_HOST'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// API keys
define('N2YO_API_KEY', $_ENV['N2YO_API_KEY'] ?? '');

// connect to the database
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("aww snap! database connection failed :( Please check your DB credentials and that the DB server is running.");
}
$conn->set_charset('utf8mb4');

// CSRF token functions
// CSRF = Cross-Site Request Forgery, basically stops people from tricking users into doing stuff
// each form gets a unique token that expires after an hour
function generateCSRFToken() {
    global $conn;
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $user_id = $_SESSION['user_id'] ?? null;

    // try to save it in the database, but if that fails use session storage as backup
    if ($user_id === null) {
        $stmt = $conn->prepare("INSERT INTO csrf_tokens (token, expires_at) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param('ss', $token, $expires);
            if (!$stmt->execute()) {
                $_SESSION['csrf_tokens_fallback'] = $_SESSION['csrf_tokens_fallback'] ?? [];
                $_SESSION['csrf_tokens_fallback'][] = ['token' => $token, 'expires' => $expires];
            }
            $stmt->close();
        }
    } else {
        $uid = (int)$user_id;
        $stmt = $conn->prepare("INSERT INTO csrf_tokens (token, user_id, expires_at) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sis', $token, $uid, $expires);
            if (!$stmt->execute()) {
                $_SESSION['csrf_tokens_fallback'] = $_SESSION['csrf_tokens_fallback'] ?? [];
                $_SESSION['csrf_tokens_fallback'][] = ['token' => $token, 'expires' => $expires];
            }
            $stmt->close();
        }
    }

    return $token;
}

function verifyCSRFToken($token) {
    global $conn;

    if (empty($token)) return false;

    // clean up old expired tokens
    $conn->query("DELETE FROM csrf_tokens WHERE expires_at < NOW()");

    // check if the token exists and hasn't expired
    $stmt = $conn->prepare(
        "SELECT id FROM csrf_tokens
         WHERE token = ? AND expires_at > NOW()"
    );
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $found = $result && $result->fetch_assoc();
        $stmt->close();

        if ($found) {
            // tokens are one-time use, delete it after checking
            $del = $conn->prepare("DELETE FROM csrf_tokens WHERE token = ?");
            if ($del) {
                $del->bind_param('s', $token);
                $del->execute();
                $del->close();
            }
            return true;
        }
    }

    // if database check failed, try the session fallback
    if (!empty($_SESSION['csrf_tokens_fallback'])) {
        $now = time();
        foreach ($_SESSION['csrf_tokens_fallback'] as $k => $item) {
            if ($item['token'] === $token) {
                $expiresTs = strtotime($item['expires']);
                if ($expiresTs > $now) {
                    // found it and it's still valid
                    array_splice($_SESSION['csrf_tokens_fallback'], $k, 1);
                    return true;
                } else {
                    // expired, remove it
                    array_splice($_SESSION['csrf_tokens_fallback'], $k, 1);
                }
            }
        }
    }

    return false;
}

// simple helper functions for checking if someone is logged in
function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// security & validation functions
   function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validateCoordinates($lat, $lng) {
    return is_numeric($lat) && is_numeric($lng)
        && $lat >= -90 && $lat <= 90
        && $lng >= -180 && $lng <= 180;
}

// rate limiting 
function checkRateLimit($identifier, $max_attempts = 5, $window_minutes = 15) {
    global $conn;

    $window_start = date(
        'Y-m-d H:i:s',
        strtotime("-$window_minutes minutes")
    );

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM users
         WHERE email = ?
         AND last_failed_login > ?"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $identifier, $window_start);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        $count = $row['cnt'] ?? 0;
        return $count < $max_attempts;
    }

    // if query fails, be conservative
    return false;
}
