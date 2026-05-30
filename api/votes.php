<?php
/**
 * votes.php — like / unlike a review.
 *
 * GET              → list (supports ?reviewId=, ?userId=)
 * POST             → create vote (auth required, no duplicates)
 * DELETE ?id=...   → remove vote (owner or admin)
 */
require_once __DIR__ . '/db_connect.php';

$method = getMethod();
$body   = getJsonBody();

// ── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $where  = [];
    $params = [];

    if (isset($_GET['reviewId'])) {
        $where[]             = "reviewId = :reviewId";
        $params[':reviewId'] = (int) $_GET['reviewId'];
    }
    if (isset($_GET['userId'])) {
        $where[]           = "userId = :userId";
        $params[':userId'] = (int) $_GET['userId'];
    }

    $sql = "SELECT * FROM votes";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $votes = $stmt->fetchAll();

    foreach ($votes as &$v) {
        $v['id']       = (int) $v['id'];
        $v['reviewId'] = (int) $v['reviewId'];
        $v['userId']   = (int) $v['userId'];
    }
    jsonResponse($votes);
}

// ── POST ────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $user = requireAuth($pdo);

    $reviewId = (int) (isset($body['reviewId']) ? $body['reviewId'] : 0);
    $userId   = (int) (isset($body['userId'])   ? $body['userId']   : 0);

    if (!$reviewId || !$userId || $userId !== (int)$user['id']) {
        jsonResponse(['message' => 'Invalid vote request.'], 400);
    }

    // Verify review exists
    $check = $pdo->prepare("SELECT id FROM reviews WHERE id = :id");
    $check->execute([':id' => $reviewId]);
    if (!$check->fetch()) jsonResponse(['message' => 'Review not found.'], 404);

    // Check duplicate
    $dup = $pdo->prepare("SELECT id FROM votes WHERE reviewId = :r AND userId = :u");
    $dup->execute([':r' => $reviewId, ':u' => $userId]);
    if ($dup->fetch()) {
        jsonResponse(['message' => 'You have already liked this review.'], 409);
    }

    $stmt = $pdo->prepare("INSERT INTO votes (reviewId, userId) VALUES (:r, :u)");
    $stmt->execute([':r' => $reviewId, ':u' => $userId]);
    $newId = (int) $pdo->lastInsertId();

    jsonResponse([
        'id' => $newId, 'reviewId' => $reviewId, 'userId' => $userId,
        'createdAt' => date('Y-m-d\TH:i:s.000\Z')
    ], 201);
}

// ── DELETE ──────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = requireAuth($pdo);
    $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
    if (!$id) jsonResponse(['message' => 'Vote id required.'], 400);

    $stmt = $pdo->prepare("SELECT * FROM votes WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $vote = $stmt->fetch();
    if (!$vote) jsonResponse(['message' => 'Vote not found.'], 404);

    if ((int)$vote['userId'] !== (int)$user['id'] && $user['role'] !== 'admin') {
        jsonResponse(['message' => 'You can only remove your own vote.'], 403);
    }

    $pdo->prepare("DELETE FROM votes WHERE id = :id")->execute([':id' => $id]);
    http_response_code(204);
    exit;
}

jsonResponse(['message' => 'Method not allowed.'], 405);
