<?php
/**
 * users.php — handles login, register, and user profile.
 *
 * GET    ?action=login&username=...&password=...  → authenticate
 * POST   { action: "login",    username, password }
 * POST   { action: "register", username, password }
 * GET    ?id=...                                   → single user
 * GET    ?username=...                              → find by username
 * PATCH  ?id=...   { username, password }           → update profile
 */
require_once __DIR__ . '/db_connect.php';

$method = getMethod();
$body   = getJsonBody();

// ── Route by method ─────────────────────────────────────────────────────────
if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    // GET login (some frontends do GET-based login)
    if ($action === 'login') {
        $username = trim(isset($_GET['username']) ? $_GET['username'] : '');
        $password = trim(isset($_GET['password']) ? $_GET['password'] : '');
        handleLogin($pdo, $username, $password);
    }

    // GET single user by id
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $pdo->prepare("SELECT id, username, role, createdAt FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) jsonResponse(['message' => 'User not found.'], 404);
        jsonResponse($user);
    }

    // GET by username
    if (isset($_GET['username'])) {
        $stmt = $pdo->prepare("SELECT id, username, role, createdAt FROM users WHERE username = :u");
        $stmt->execute([':u' => trim($_GET['username'])]);
        $users = $stmt->fetchAll();
        jsonResponse($users);
    }

    // GET all users (admin use)
    $stmt = $pdo->query("SELECT id, username, role, createdAt FROM users ORDER BY id");
    jsonResponse($stmt->fetchAll());
}

if ($method === 'POST') {
    $action = isset($body['action']) ? $body['action'] : '';

    if ($action === 'login') {
        handleLogin($pdo, trim(isset($body['username']) ? $body['username'] : ''), trim(isset($body['password']) ? $body['password'] : ''));
    }

    if ($action === 'register') {
        handleRegister($pdo, $body);
    }

    // Fallback: treat as register (old frontend compat)
    handleRegister($pdo, $body);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
    if (!$id) jsonResponse(['message' => 'User id required.'], 400);

    $currentUser = requireAuth($pdo);
    if ($currentUser['id'] !== $id && $currentUser['role'] !== 'admin') {
        jsonResponse(['message' => 'Forbidden.'], 403);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['message' => 'User not found.'], 404);

    $newUsername = trim(isset($body['username']) ? $body['username'] : $user['username']);
    $newPassword = trim(isset($body['password']) ? $body['password'] : $user['password']);

    $stmt = $pdo->prepare("UPDATE users SET username = :u, password = :p WHERE id = :id");
    $stmt->execute([':u' => $newUsername, ':p' => $newPassword, ':id' => $id]);

    jsonResponse(['id' => $id, 'username' => $newUsername, 'role' => $user['role'], 'createdAt' => $user['createdAt']]);
}

jsonResponse(['message' => 'Method not allowed.'], 405);

// ── Helper functions ────────────────────────────────────────────────────────

function handleLogin(PDO $pdo, $username, $password) {
    if (!$username || !$password) {
        jsonResponse(['message' => 'Username and password are required.'], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user || $user['password'] !== $password) {
        jsonResponse(['message' => 'Invalid username or password.'], 401);
    }

    $token = 'tg_token_' . $user['id'];
    jsonResponse([
        'user'  => [
            'id'        => (int)$user['id'],
            'username'  => $user['username'],
            'role'      => $user['role'],
            'createdAt' => $user['createdAt']
        ],
        'token' => $token
    ]);
}

function handleRegister(PDO $pdo, array $body) {
    $username = trim(isset($body['username']) ? $body['username'] : '');
    $password = trim(isset($body['password']) ? $body['password'] : '');

    if (strlen($username) < 3) {
        jsonResponse(['message' => 'Username must be at least 3 characters.'], 400);
    }
    if (strlen($password) < 6) {
        jsonResponse(['message' => 'Password must be at least 6 characters.'], 400);
    }

    // Check uniqueness
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    if ($stmt->fetch()) {
        jsonResponse(['message' => 'Username already taken.'], 409);
    }

    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:u, :p, 'user')");
    $stmt->execute([':u' => $username, ':p' => $password]);
    $newId = (int) $pdo->lastInsertId();

    $token = 'tg_token_' . $newId;
    jsonResponse([
        'user'  => [
            'id'        => $newId,
            'username'  => $username,
            'role'      => 'user',
            'createdAt' => date('Y-m-d H:i:s')
        ],
        'token' => $token
    ], 201);
}
