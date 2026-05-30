<?php
/**
 * games.php — CRUD for games.
 *
 * GET              → list all (supports ?genre=, ?featured=, ?_sort=, ?_order=, ?_limit=, ?_page=, ?q=)
 * GET  ?id=...     → single game
 * POST             → create (admin only)
 * PATCH ?id=...    → update (admin only)
 * DELETE ?id=...   → delete (admin only, cascades reviews/votes/reports/tierlist refs)
 */
require_once __DIR__ . '/db_connect.php';

$method = getMethod();
$body   = getJsonBody();

// ── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Single game
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM games WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $game = $stmt->fetch();
        if (!$game) jsonResponse(['message' => 'Game not found.'], 404);
        $game['platforms'] = explode(',', $game['platforms']);
        $game['featured']  = (bool) $game['featured'];
        $game['id']        = (int) $game['id'];
        $game['year']      = (int) $game['year'];
        jsonResponse($game);
    }

    // List games with filters
    $where  = [];
    $params = [];

    if (isset($_GET['genre']) && $_GET['genre'] !== '') {
        $where[]          = "genre = :genre";
        $params[':genre'] = $_GET['genre'];
    }
    if (isset($_GET['featured'])) {
        $where[]             = "featured = :featured";
        $params[':featured'] = $_GET['featured'] === 'true' || $_GET['featured'] === '1' ? 1 : 0;
    }
    if (isset($_GET['q']) && $_GET['q'] !== '') {
        $where[]       = "(title LIKE :q OR developer LIKE :q2 OR summary LIKE :q3)";
        $params[':q']  = '%' . $_GET['q'] . '%';
        $params[':q2'] = '%' . $_GET['q'] . '%';
        $params[':q3'] = '%' . $_GET['q'] . '%';
    }

    $sql = "SELECT * FROM games";
    if ($where) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    // Sorting
    $allowedSort = ['id', 'title', 'year', 'genre', 'developer'];
    $sort  = in_array(isset($_GET['_sort']) ? $_GET['_sort'] : '', $allowedSort) ? $_GET['_sort'] : 'id';
    $order = (strtolower(isset($_GET['_order']) ? $_GET['_order'] : '') === 'desc') ? 'DESC' : 'ASC';
    $sql  .= " ORDER BY $sort $order";

    // Pagination
    $page  = max(1, (int)(isset($_GET['_page']) ? $_GET['_page'] : 1));
    $limit = max(1, min(100, (int)(isset($_GET['_limit']) ? $_GET['_limit'] : 100)));
    $offset = ($page - 1) * $limit;
    $sql .= " LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $games = $stmt->fetchAll();

    // Get total count for pagination header
    $countSql = "SELECT COUNT(*) FROM games";
    if ($where) {
        $countSql .= " WHERE " . implode(' AND ', $where);
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Format
    foreach ($games as &$g) {
        $g['platforms'] = explode(',', $g['platforms']);
        $g['featured']  = (bool) $g['featured'];
        $g['id']        = (int) $g['id'];
        $g['year']      = (int) $g['year'];
    }

    // Set pagination header (json-server compat)
    header("X-Total-Count: $total");
    header("Access-Control-Expose-Headers: X-Total-Count");
    jsonResponse($games);
}

// ── POST ────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    requireAdmin($pdo);

    $platforms = is_array(isset($body['platforms']) ? $body['platforms'] : null)
        ? implode(',', $body['platforms'])
        : trim(isset($body['platforms']) ? $body['platforms'] : '');

    $title     = trim(isset($body['title']) ? $body['title'] : '');
    $genre     = trim(isset($body['genre']) ? $body['genre'] : '');
    $developer = trim(isset($body['developer']) ? $body['developer'] : '');
    $year      = (int)(isset($body['year']) ? $body['year'] : date('Y'));
    $cover     = trim(isset($body['cover']) ? $body['cover'] : '');
    $summary   = trim(isset($body['summary']) ? $body['summary'] : '');
    $featured  = !empty($body['featured']) ? 1 : 0;

    if (!$title || !$genre || !$developer || !$summary || !$platforms) {
        jsonResponse(['message' => 'Title, genre, developer, summary, and platforms are required.'], 400);
    }

    $stmt = $pdo->prepare("INSERT INTO games (title, genre, developer, year, platforms, cover, summary, featured)
                           VALUES (:title, :genre, :developer, :year, :platforms, :cover, :summary, :featured)");
    $stmt->execute([
        ':title'     => $title,
        ':genre'     => $genre,
        ':developer' => $developer,
        ':year'      => $year,
        ':platforms' => $platforms,
        ':cover'     => $cover,
        ':summary'   => $summary,
        ':featured'  => $featured
    ]);

    $newId = (int) $pdo->lastInsertId();
    jsonResponse([
        'id' => $newId, 'title' => $title, 'genre' => $genre,
        'developer' => $developer, 'year' => $year,
        'platforms' => explode(',', $platforms),
        'cover' => $cover, 'summary' => $summary, 'featured' => (bool)$featured
    ], 201);
}

// ── PATCH ───────────────────────────────────────────────────────────────────
if ($method === 'PATCH' || $method === 'PUT') {
    requireAdmin($pdo);
    $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
    if (!$id) jsonResponse(['message' => 'Game id required.'], 400);

    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) jsonResponse(['message' => 'Game not found.'], 404);

    $platforms = isset($body['platforms'])
        ? (is_array($body['platforms']) ? implode(',', $body['platforms']) : trim($body['platforms']))
        : $existing['platforms'];

    $title     = trim(isset($body['title'])     ? $body['title']     : $existing['title']);
    $genre     = trim(isset($body['genre'])     ? $body['genre']     : $existing['genre']);
    $developer = trim(isset($body['developer']) ? $body['developer'] : $existing['developer']);
    $year      = (int)(isset($body['year'])     ? $body['year']     : $existing['year']);
    $cover     = trim(isset($body['cover'])     ? $body['cover']     : $existing['cover']);
    $summary   = trim(isset($body['summary'])   ? $body['summary']   : $existing['summary']);
    $featured  = isset($body['featured']) ? (!empty($body['featured']) ? 1 : 0) : $existing['featured'];

    $stmt = $pdo->prepare("UPDATE games SET title=:title, genre=:genre, developer=:developer,
                           year=:year, platforms=:platforms, cover=:cover, summary=:summary, featured=:featured
                           WHERE id=:id");
    $stmt->execute([
        ':title' => $title, ':genre' => $genre, ':developer' => $developer,
        ':year' => $year, ':platforms' => $platforms, ':cover' => $cover,
        ':summary' => $summary, ':featured' => $featured, ':id' => $id
    ]);

    jsonResponse([
        'id' => $id, 'title' => $title, 'genre' => $genre,
        'developer' => $developer, 'year' => $year,
        'platforms' => explode(',', $platforms),
        'cover' => $cover, 'summary' => $summary, 'featured' => (bool)$featured
    ]);
}

// ── DELETE ──────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAdmin($pdo);
    $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
    if (!$id) jsonResponse(['message' => 'Game id required.'], 400);

    $stmt = $pdo->prepare("SELECT id FROM games WHERE id = :id");
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) jsonResponse(['message' => 'Game not found.'], 404);

    // Cascade: remove tierlist_tiers refs to this game
    $pdo->prepare("DELETE FROM tierlist_tiers WHERE gameId = :id")->execute([':id' => $id]);

    // Reviews cascade automatically via FK, but let's be explicit
    $pdo->prepare("DELETE FROM games WHERE id = :id")->execute([':id' => $id]);

    http_response_code(204);
    exit;
}

jsonResponse(['message' => 'Method not allowed.'], 405);
