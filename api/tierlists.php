<?php
/**
 * tierlists.php — CRUD for tier lists.
 *
 * GET              → list (supports ?userId=, ?_sort=, ?_order=)
 * GET  ?id=...     → single tierlist (includes tiers object)
 * POST             → create (auth required)
 * PATCH ?id=...    → update (owner or admin)
 * DELETE ?id=...   → delete (owner or admin)
 *
 * Tiers are stored in a separate `tierlist_tiers` table but are assembled
 * into a JSON object { S: [gameId, ...], A: [...], ... } to match the
 * original json-server format the frontend expects.
 */
require_once __DIR__ . '/db_connect.php';

$method = getMethod();
$body   = getJsonBody();

/**
 * Assemble the tiers object for a tierlist row.
 * Returns { "S": [1, 13], "A": [7], "B": [], ... }
 */
function getTiers(PDO $pdo, $tierlistId) {
    $stmt = $pdo->prepare(
        "SELECT tierName, gameId FROM tierlist_tiers WHERE tierlistId = :id ORDER BY sortOrder ASC"
    );
    $stmt->execute([':id' => $tierlistId]);
    $rows = $stmt->fetchAll();

    $tiers = ['S' => [], 'A' => [], 'B' => [], 'C' => [], 'D' => [], 'F' => []];
    foreach ($rows as $row) {
        $tiers[$row['tierName']][] = (int) $row['gameId'];
    }
    return $tiers;
}

/**
 * Save the tiers object for a tierlist.
 * Deletes old rows then inserts new ones.
 */
function saveTiers(PDO $pdo, $tierlistId, array $tiersObj) {
    $pdo->prepare("DELETE FROM tierlist_tiers WHERE tierlistId = :id")->execute([':id' => $tierlistId]);

    $stmt = $pdo->prepare(
        "INSERT INTO tierlist_tiers (tierlistId, tierName, gameId, sortOrder) VALUES (:tl, :tn, :gid, :so)"
    );
    foreach ($tiersObj as $tierName => $gameIds) {
        if (!is_array($gameIds)) continue;
        foreach ($gameIds as $order => $gameId) {
            $stmt->execute([
                ':tl'  => $tierlistId,
                ':tn'  => $tierName,
                ':gid' => (int) $gameId,
                ':so'  => (int) $order
            ]);
        }
    }
}

/**
 * Format a tierlist row into the shape the frontend expects.
 */
function formatTierlist(PDO $pdo, array $row) {
    return [
        'id'          => (int) $row['id'],
        'userId'      => (int) $row['userId'],
        'title'       => $row['title'],
        'description' => $row['description'],
        'tiers'       => getTiers($pdo, (int) $row['id']),
        'createdAt'   => $row['createdAt']
    ];
}

// ── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Single tierlist
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM tierlists WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $tl = $stmt->fetch();
        if (!$tl) jsonResponse(['message' => 'Tier list not found.'], 404);
        jsonResponse(formatTierlist($pdo, $tl));
    }

    // List
    $where  = [];
    $params = [];

    if (isset($_GET['userId'])) {
        $where[]           = "userId = :userId";
        $params[':userId'] = (int) $_GET['userId'];
    }

    $sql = "SELECT * FROM tierlists";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);

    $allowedSort = ['id', 'createdAt', 'title'];
    $sort  = in_array(isset($_GET['_sort']) ? $_GET['_sort'] : '', $allowedSort) ? $_GET['_sort'] : 'createdAt';
    $order = (strtolower(isset($_GET['_order']) ? $_GET['_order'] : '') === 'asc') ? 'ASC' : 'DESC';
    $sql  .= " ORDER BY $sort $order";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $result[] = formatTierlist($pdo, $row);
    }
    jsonResponse($result);
}

// ── POST ────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $user = requireAuth($pdo);

    $title       = trim(isset($body['title']) ? $body['title'] : '');
    $description = trim(isset($body['description']) ? $body['description'] : '');
    $tiersObj    = isset($body['tiers']) ? $body['tiers'] : array();
    $userId      = (int) (isset($body['userId']) ? $body['userId'] : $user['id']);

    if (!$title) jsonResponse(['message' => 'Title is required.'], 400);

    $stmt = $pdo->prepare("INSERT INTO tierlists (userId, title, description) VALUES (:u, :t, :d)");
    $stmt->execute([':u' => $userId, ':t' => $title, ':d' => $description]);
    $newId = (int) $pdo->lastInsertId();

    if (!empty($tiersObj) && is_array($tiersObj)) {
        saveTiers($pdo, $newId, $tiersObj);
    }

    $stmt = $pdo->prepare("SELECT * FROM tierlists WHERE id = :id");
    $stmt->execute([':id' => $newId]);
    jsonResponse(formatTierlist($pdo, $stmt->fetch()), 201);
}

// ── PATCH ───────────────────────────────────────────────────────────────────
if ($method === 'PATCH' || $method === 'PUT') {
    $user = requireAuth($pdo);
    $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
    if (!$id) jsonResponse(['message' => 'Tier list id required.'], 400);

    $stmt = $pdo->prepare("SELECT * FROM tierlists WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $tl = $stmt->fetch();
    if (!$tl) jsonResponse(['message' => 'Tier list not found.'], 404);

    if ((int)$tl['userId'] !== (int)$user['id'] && $user['role'] !== 'admin') {
        jsonResponse(['message' => 'Forbidden.'], 403);
    }

    $title       = trim(isset($body['title'])       ? $body['title']       : $tl['title']);
    $description = trim(isset($body['description']) ? $body['description'] : $tl['description']);

    $stmt = $pdo->prepare("UPDATE tierlists SET title = :t, description = :d WHERE id = :id");
    $stmt->execute([':t' => $title, ':d' => $description, ':id' => $id]);

    if (isset($body['tiers']) && is_array($body['tiers'])) {
        saveTiers($pdo, $id, $body['tiers']);
    }

    $stmt = $pdo->prepare("SELECT * FROM tierlists WHERE id = :id");
    $stmt->execute([':id' => $id]);
    jsonResponse(formatTierlist($pdo, $stmt->fetch()));
}

// ── DELETE ──────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = requireAuth($pdo);
    $id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
    if (!$id) jsonResponse(['message' => 'Tier list id required.'], 400);

    $stmt = $pdo->prepare("SELECT * FROM tierlists WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $tl = $stmt->fetch();
    if (!$tl) jsonResponse(['message' => 'Tier list not found.'], 404);

    if ((int)$tl['userId'] !== (int)$user['id'] && $user['role'] !== 'admin') {
        jsonResponse(['message' => 'Forbidden.'], 403);
    }

    // FK cascades handle tierlist_tiers and tierlist_reactions
    $pdo->prepare("DELETE FROM tierlists WHERE id = :id")->execute([':id' => $id]);
    http_response_code(204);
    exit;
}

jsonResponse(['message' => 'Method not allowed.'], 405);
