<?php
/**
 * tierlist_reactions.php — like / dislike tier lists.
 *
 * GET              → list (supports ?tierlistId=, ?userId=)
 * POST             → create reaction (auth required, replaces existing)
 * DELETE ?id=...   → remove reaction (owner or admin)
 */
require_once __DIR__ . '/db_connect.php';

$method = getMethod();
$body   = getJsonBody();

// ── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $where  = [];
    $params = [];

    if (isset($_GET['tierlistId'])) {
        $where[]               = "tierlistId = :tierlistId";
        $params[':tierlistId'] = (int) $_GET['tierlistId'];
    }
    if (isset($_GET['userId'])) {
        $where[]           = "userId = :userId";
        $params[':userId'] = (int) $_GET['userId'];
    }

    $sql = "SELECT * FROM tierlist_reactions";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reactions = $stmt->fetchAll();

    foreach ($reactions as &$r) {
        $r['id']         = (int) $r['id'];
        $r['tierlistId'] = (int) $r['tierlistId'];
        $r['userId']     = (int) $r['userId'];
    }
    jsonResponse($reactions);
}

// ── POST ────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $user = requireAuth($pdo);

    $tierlistId = (int) (isset($body['tierlistId']) ? $body['tierlistId'] : 0);
    $userId     = (int) (isset($body['userId'])     ? $body['userId']     : $user['id']);
    $type       = trim(isset($body['type']) ? $body['type'] : 'like');

    if (!$tierlistId) {
        jsonResponse(['message' => 'tierlistId is required.'], 400);
    }
    if (!in_array($type, ['like', 'dislike'])) {
        jsonResponse(['message' => 'Type must be "like" or "dislike".'], 400);
    }

    // Verify tierlist exists
    $check = $pdo->prepare("SELECT id FROM tierlists WHERE id = :id");
    $check->execute([':id' => $tierlistId]);
    if (!$check->fetch()) jsonResponse(['message' => 'Tier list not found.'], 404);

    // Upsert: delete existing, then insert new
    $pdo->prepare("DELETE FROM tierlist_reactions WHERE tierlistId = :t AND userId = :u")
        ->execute([':t' => $tierlistId, ':u' => $userId]);

    $stmt = $pdo->prepare(
        "INSERT INTO tierlist_reactions (tierlistId, userId, type) VALUES (:t, :u, :type)"
    );
    $stmt->execute([':t' => $tierlistId, ':u' => $userId, ':type' => $type]);
    $newId = (int) $pdo->lastInsertId();

    jsonResponse([
        'id' => $newId, 'tierlistId' => $tierlistId, 'userId' => $userId,
        'type' => $type, 'createdAt' => date('Y-m-d\TH:i:s.000\Z')
    ], 201);
}

// ── DELETE ──────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = requireAuth($pdo);
    $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
    if (!$id) jsonResponse(['message' => 'Reaction id required.'], 400);

    $stmt = $pdo->prepare("SELECT * FROM tierlist_reactions WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $reaction = $stmt->fetch();
    if (!$reaction) jsonResponse(['message' => 'Reaction not found.'], 404);

    if ((int)$reaction['userId'] !== (int)$user['id'] && $user['role'] !== 'admin') {
        jsonResponse(['message' => 'Forbidden.'], 403);
    }

    $pdo->prepare("DELETE FROM tierlist_reactions WHERE id = :id")->execute([':id' => $id]);
    http_response_code(204);
    exit;
}

jsonResponse(['message' => 'Method not allowed.'], 405);
