<?php
/**
 * reviews.php — CRUD for reviews.
 *
 * GET              → list (supports ?gameId=, ?userId=, ?_sort=, ?_order=, ?_page=, ?_limit=)
 * GET  ?id=...     → single review
 * POST             → create (auth required)
 * PATCH ?id=...    → update (owner or admin)
 * DELETE ?id=...   → delete (owner or admin, cascades votes & reports)
 */
require_once __DIR__ . '/db_connect.php';

$method = getMethod();
$body   = getJsonBody();

// ── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Single review
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM reviews WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $review = $stmt->fetch();
        if (!$review) jsonResponse(['message' => 'Review not found.'], 404);
        $review['id']      = (int) $review['id'];
        $review['gameId']  = (int) $review['gameId'];
        $review['userId']  = (int) $review['userId'];
        $review['rating']  = (int) $review['rating'];
        jsonResponse($review);
    }

    // List with filters
    $where  = [];
    $params = [];

    if (isset($_GET['gameId'])) {
        $where[]           = "gameId = :gameId";
        $params[':gameId'] = (int) $_GET['gameId'];
    }
    if (isset($_GET['userId'])) {
        $where[]           = "userId = :userId";
        $params[':userId'] = (int) $_GET['userId'];
    }

    $sql = "SELECT * FROM reviews";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);

    $allowedSort = ['id', 'createdAt', 'rating'];
    $sort  = in_array(isset($_GET['_sort']) ? $_GET['_sort'] : '', $allowedSort) ? $_GET['_sort'] : 'createdAt';
    $order = (strtolower(isset($_GET['_order']) ? $_GET['_order'] : '') === 'asc') ? 'ASC' : 'DESC';
    $sql  .= " ORDER BY $sort $order";

    $page  = max(1, (int)(isset($_GET['_page']) ? $_GET['_page'] : 1));
    $limit = max(1, min(100, (int)(isset($_GET['_limit']) ? $_GET['_limit'] : 100)));
    $offset = ($page - 1) * $limit;
    $sql .= " LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();

    // Total count
    $countSql = "SELECT COUNT(*) FROM reviews";
    if ($where) $countSql .= " WHERE " . implode(' AND ', $where);
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    foreach ($reviews as &$r) {
        $r['id']     = (int) $r['id'];
        $r['gameId'] = (int) $r['gameId'];
        $r['userId'] = (int) $r['userId'];
        $r['rating'] = (int) $r['rating'];
    }

    header("X-Total-Count: $total");
    header("Access-Control-Expose-Headers: X-Total-Count");
    jsonResponse($reviews);
}

// ── POST ────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $user = requireAuth($pdo);

    $gameId = (int) (isset($body['gameId']) ? $body['gameId'] : 0);
    $rating = (int) (isset($body['rating']) ? $body['rating'] : 0);
    $title  = trim(isset($body['title'])  ? $body['title']  : '');
    $bodyTx = trim(isset($body['body'])   ? $body['body']   : '');

    if (!$gameId || !$rating || !$title || !$bodyTx) {
        jsonResponse(['message' => 'gameId, rating, title, and body are required.'], 400);
    }

    // Verify game exists
    $check = $pdo->prepare("SELECT id FROM games WHERE id = :id");
    $check->execute([':id' => $gameId]);
    if (!$check->fetch()) jsonResponse(['message' => 'Game not found.'], 404);

    $stmt = $pdo->prepare("INSERT INTO reviews (gameId, userId, rating, title, body) VALUES (:gameId, :userId, :rating, :title, :body)");
    $stmt->execute([
        ':gameId' => $gameId,
        ':userId' => (int) (isset($body['userId']) ? $body['userId'] : $user['id']),
        ':rating' => $rating,
        ':title'  => $title,
        ':body'   => $bodyTx
    ]);

    $newId = (int) $pdo->lastInsertId();
    jsonResponse([
        'id' => $newId, 'gameId' => $gameId,
        'userId' => (int) (isset($body['userId']) ? $body['userId'] : $user['id']),
        'rating' => $rating, 'title' => $title, 'body' => $bodyTx,
        'createdAt' => date('Y-m-d\TH:i:s.000\Z')
    ], 201);
}

// ── PATCH ───────────────────────────────────────────────────────────────────
if ($method === 'PATCH' || $method === 'PUT') {
    $user = requireAuth($pdo);
    $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
    if (!$id) jsonResponse(['message' => 'Review id required.'], 400);

    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $review = $stmt->fetch();
    if (!$review) jsonResponse(['message' => 'Review not found.'], 404);

    if ((int)$review['userId'] !== (int)$user['id'] && $user['role'] !== 'admin') {
        jsonResponse(['message' => 'Forbidden.'], 403);
    }

    $rating = (int) (isset($body['rating']) ? $body['rating'] : $review['rating']);
    $title  = trim(isset($body['title'])  ? $body['title']  : $review['title']);
    $bodyTx = trim(isset($body['body'])   ? $body['body']   : $review['body']);

    $stmt = $pdo->prepare("UPDATE reviews SET rating=:rating, title=:title, body=:body WHERE id=:id");
    $stmt->execute([':rating' => $rating, ':title' => $title, ':body' => $bodyTx, ':id' => $id]);

    $review['rating'] = $rating;
    $review['title']  = $title;
    $review['body']   = $bodyTx;
    $review['id']     = (int) $review['id'];
    $review['gameId'] = (int) $review['gameId'];
    $review['userId'] = (int) $review['userId'];
    jsonResponse($review);
}

// ── DELETE ──────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = requireAuth($pdo);
    $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
    if (!$id) jsonResponse(['message' => 'Review id required.'], 400);

    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $review = $stmt->fetch();
    if (!$review) jsonResponse(['message' => 'Review not found.'], 404);

    if ((int)$review['userId'] !== (int)$user['id'] && $user['role'] !== 'admin') {
        jsonResponse(['message' => 'Forbidden.'], 403);
    }

    // FK cascades handle votes and reports
    $pdo->prepare("DELETE FROM reviews WHERE id = :id")->execute([':id' => $id]);

    http_response_code(204);
    exit;
}

jsonResponse(['message' => 'Method not allowed.'], 405);
