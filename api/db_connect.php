<?php
/**
 * db_connect.php — shared DB connection and utility helpers for TierGG API.
 * Included at the top of every endpoint file.
 */

// Buffer all output so PHP notices/warnings don't contaminate the JSON response
// or flush headers prematurely.
ob_start();

// ── CORS & Headers ──────────────────────────────────────────────────────────
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-TG-Token");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Database credentials ────────────────────────────────────────────────────
$host    = 'feenix-mariadb.swin.edu.au';
$db_name = 's104799137_db';
$db_user = 's104799137';
$db_pass = '111004';

// ── PDO connection ──────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// ── Utility functions ───────────────────────────────────────────────────────

/** Send a JSON response and exit. */
function jsonResponse($data, $code = 200) {
    // Discard any PHP notices/warnings that leaked into the output buffer.
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    echo json_encode($data);
    exit;
}

/** Read the raw JSON request body. */
function getJsonBody() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Extract the HTTP method (supports _method override for PATCH/DELETE). */
function getMethod() {
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

/**
 * Extract the authenticated user from the Authorization: Bearer token.
 * Token format: "tg_token_<userId>"
 * Returns the user row or null.
 */
function getAuthUser(PDO $pdo) {
    $header = isset($_SERVER['HTTP_X_TG_TOKEN']) ? $_SERVER['HTTP_X_TG_TOKEN'] : '';
    $token  = preg_replace('/^Bearer\s+/i', '', $header);

    if (!preg_match('/(\d+)$/', $token, $m)) {
        return null;
    }

    $userId = (int) $m[1];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

/**
 * Require authentication — returns the user or sends 401.
 */
function requireAuth(PDO $pdo) {
    $user = getAuthUser($pdo);
    if (!$user) {
        jsonResponse(['message' => 'Login required.'], 401);
    }
    return $user;
}

/**
 * Require admin role — returns the user or sends 403.
 */
function requireAdmin(PDO $pdo) {
    $user = getAuthUser($pdo);
    if (!$user || $user['role'] !== 'admin') {
        jsonResponse(['message' => 'Admin access required.'], 403);
    }
    return $user;
}
