<?php
/**
 * admin.php — admin-only endpoints.
 *
 * Routing via ?action= parameter:
 *   GET    ?action=stats                        → dashboard statistics
 *   GET    ?action=games                        → list all games
 *   POST   ?action=games   { ...game }          → create game
 *   PATCH  ?action=games&id=...  { ...game }    → update game
 *   DELETE ?action=games&id=...                 → delete game (cascade)
 *   GET    ?action=reviews                      → list all reviews
 *   DELETE ?action=reviews&id=...               → delete review (cascade)
 *   GET    ?action=reports  (?status=open)       → list reports
 *   PATCH  ?action=reports&id=...  { status }   → update report status
 *   POST   ?action=dismiss  { reviewId }        → dismiss all open reports for a review
 *   DELETE ?action=reports&id=...               → delete report
 */
require_once __DIR__ . '/db_connect.php';

$method = getMethod();
$body   = getJsonBody();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// All admin endpoints require admin role
$user = requireAdmin($pdo);

// ── STATS ───────────────────────────────────────────────────────────────────
if ($action === 'stats' && $method === 'GET') {
    $games       = (int) $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
    $reviews     = (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    $votes       = (int) $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
    $users       = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $featured    = (int) $pdo->query("SELECT COUNT(*) FROM games WHERE featured = 1")->fetchColumn();
    $openReports = (int) $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn();

    jsonResponse([
        'games'        => $games,
        'reviews'      => $reviews,
        'votes'        => $votes,
        'users'        => $users,
        'featuredGames'=> $featured,
        'openReports'  => $openReports
    ]);
}

// ── GAMES ───────────────────────────────────────────────────────────────────
if ($action === 'games') {

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM games ORDER BY title ASC");
        $games = $stmt->fetchAll();
        foreach ($games as &$g) {
            $g['platforms'] = explode(',', $g['platforms']);
            $g['featured']  = (bool) $g['featured'];
            $g['id']        = (int) $g['id'];
            $g['year']      = (int) $g['year'];
        }
        jsonResponse($games);
    }

    if ($method === 'POST') {
        $platforms = is_array(isset($body['platforms']) ? $body['platforms'] : null)
            ? implode(',', $body['platforms'])
            : trim(isset($body['platforms']) ? $body['platforms'] : '');

        $title     = trim(isset($body['title']) ? $body['title'] : '');
        $genre     = trim(isset($body['genre']) ? $body['genre'] : '');
        $developer = trim(isset($body['developer']) ? $body['developer'] : '');
        $year      = (int) (isset($body['year']) ? $body['year'] : date('Y'));
        $cover     = trim(isset($body['cover']) ? $body['cover'] : '');
        $summary   = trim(isset($body['summary']) ? $body['summary'] : '');
        $featured  = !empty($body['featured']) ? 1 : 0;

        if (!$title || !$genre || !$developer || !$summary || !$platforms) {
            jsonResponse(['message' => 'Title, genre, developer, summary, and platforms are required.'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO games (title, genre, developer, year, platforms, cover, summary, featured)
                               VALUES (:title, :genre, :developer, :year, :platforms, :cover, :summary, :featured)");
        $stmt->execute([
            ':title' => $title, ':genre' => $genre, ':developer' => $developer,
            ':year' => $year, ':platforms' => $platforms, ':cover' => $cover,
            ':summary' => $summary, ':featured' => $featured
        ]);
        $newId = (int) $pdo->lastInsertId();

        jsonResponse([
            'id' => $newId, 'title' => $title, 'genre' => $genre,
            'developer' => $developer, 'year' => $year,
            'platforms' => explode(',', $platforms),
            'cover' => $cover, 'summary' => $summary, 'featured' => (bool) $featured
        ], 201);
    }

    if ($method === 'PATCH' || $method === 'PUT') {
        $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
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
        $year      = (int) (isset($body['year'])    ? $body['year']    : $existing['year']);
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
            'cover' => $cover, 'summary' => $summary, 'featured' => (bool) $featured
        ]);
    }

    if ($method === 'DELETE') {
        $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
        if (!$id) jsonResponse(['message' => 'Game id required.'], 400);

        $stmt = $pdo->prepare("SELECT id FROM games WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) jsonResponse(['message' => 'Game not found.'], 404);

        // Clean up tierlist references
        $pdo->prepare("DELETE FROM tierlist_tiers WHERE gameId = :id")->execute([':id' => $id]);
        // FK cascades handle reviews → votes → reports
        $pdo->prepare("DELETE FROM games WHERE id = :id")->execute([':id' => $id]);

        http_response_code(204);
        exit;
    }
}

// ── REVIEWS ─────────────────────────────────────────────────────────────────
if ($action === 'reviews') {

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM reviews ORDER BY createdAt DESC");
        $reviews = $stmt->fetchAll();
        foreach ($reviews as &$r) {
            $r['id']     = (int) $r['id'];
            $r['gameId'] = (int) $r['gameId'];
            $r['userId'] = (int) $r['userId'];
            $r['rating'] = (int) $r['rating'];
        }
        jsonResponse($reviews);
    }

    if ($method === 'DELETE') {
        $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
        if (!$id) jsonResponse(['message' => 'Review id required.'], 400);

        $stmt = $pdo->prepare("SELECT id FROM reviews WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) jsonResponse(['message' => 'Review not found.'], 404);

        // FK cascades handle votes and reports
        $pdo->prepare("DELETE FROM reviews WHERE id = :id")->execute([':id' => $id]);
        http_response_code(204);
        exit;
    }
}

// ── REPORTS ─────────────────────────────────────────────────────────────────
if ($action === 'reports') {

    if ($method === 'GET') {
        $where  = [];
        $params = [];
        if (isset($_GET['status'])) {
            $where[]           = "status = :status";
            $params[':status'] = $_GET['status'];
        }

        $sql = "SELECT * FROM reports";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY createdAt DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reports = $stmt->fetchAll();

        foreach ($reports as &$r) {
            $r['id']       = (int) $r['id'];
            $r['reviewId'] = (int) $r['reviewId'];
            $r['userId']   = (int) $r['userId'];
        }
        jsonResponse($reports);
    }

    if ($method === 'PATCH' || $method === 'PUT') {
        $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
        if (!$id) jsonResponse(['message' => 'Report id required.'], 400);

        $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $report = $stmt->fetch();
        if (!$report) jsonResponse(['message' => 'Report not found.'], 404);

        $status = trim(isset($body['status']) ? $body['status'] : 'resolved');
        if (!in_array($status, ['open', 'resolved'])) {
            jsonResponse(['message' => 'Status must be "open" or "resolved".'], 400);
        }

        $pdo->prepare("UPDATE reports SET status = :s WHERE id = :id")->execute([':s' => $status, ':id' => $id]);
        $report['status'] = $status;
        $report['id']     = (int) $report['id'];
        $report['reviewId'] = (int) $report['reviewId'];
        $report['userId']   = (int) $report['userId'];
        jsonResponse($report);
    }

    if ($method === 'DELETE') {
        $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
        if (!$id) jsonResponse(['message' => 'Report id required.'], 400);

        $stmt = $pdo->prepare("SELECT id FROM reports WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) jsonResponse(['message' => 'Report not found.'], 404);

        $pdo->prepare("DELETE FROM reports WHERE id = :id")->execute([':id' => $id]);
        http_response_code(204);
        exit;
    }
}

// ── DISMISS REPORTS ─────────────────────────────────────────────────────────
if ($action === 'dismiss' && $method === 'POST') {
    $reviewId = (int) (isset($body['reviewId']) ? $body['reviewId'] : 0);
    if (!$reviewId) jsonResponse(['message' => 'A review id is required.'], 400);

    $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE reviewId = :r AND status = 'open'")
        ->execute([':r' => $reviewId]);

    jsonResponse(['reviewId' => $reviewId, 'dismissed' => true]);
}

jsonResponse(['message' => 'Unknown admin action.'], 404);
