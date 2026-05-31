<?php
/**
 * reports.php — flag reviews.
 *
 * GET              → list (supports ?reviewId=, ?userId=, ?status=)
 * POST             → create report (auth required, no duplicates)
 */
require_once __DIR__ . '/db_connect.php';

$method = getMethod();
$body   = getJsonBody();

$REPORT_REASONS = ['Spam or advertising', 'Harassment or hate', 'Off-topic', 'Spoilers', 'Other'];

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

// ── POST ────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $user = requireAuth($pdo);

    $reviewId = (int) (isset($body['reviewId']) ? $body['reviewId'] : 0);
    $reason   = trim(isset($body['reason']) ? $body['reason'] : '');
    $note     = substr(trim(isset($body['note']) ? $body['note'] : ''), 0, 280);

    if (!$reviewId) jsonResponse(['message' => 'A review id is required.'], 400);
    if (!in_array($reason, $REPORT_REASONS)) {
        jsonResponse(['message' => 'Please choose a valid report reason.'], 400);
    }

    // Verify review exists
    $check = $pdo->prepare("SELECT id FROM reviews WHERE id = :id");
    $check->execute([':id' => $reviewId]);
    if (!$check->fetch()) jsonResponse(['message' => 'Review not found.'], 404);

    // Check duplicate
    $dup = $pdo->prepare("SELECT id FROM reports WHERE reviewId = :r AND userId = :u AND status = 'open'");
    $dup->execute([':r' => $reviewId, ':u' => (int)$user['id']]);
    if ($dup->fetch()) {
        jsonResponse(['message' => 'You have already reported this review.'], 409);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO reports (reviewId, userId, reason, note) VALUES (:r, :u, :reason, :note)");
        $stmt->execute([
            ':r'      => $reviewId,
            ':u'      => (int) $user['id'],
            ':reason' => $reason,
            ':note'   => $note
        ]);
        $newId = (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        jsonResponse(['message' => 'Could not save report: ' . $e->getMessage()], 500);
    }

    jsonResponse([
        'id' => $newId, 'reviewId' => $reviewId, 'userId' => (int)$user['id'],
        'reason' => $reason, 'note' => $note, 'status' => 'open',
        'createdAt' => date('Y-m-d\TH:i:s.000\Z')
    ], 201);
}

jsonResponse(['message' => 'Method not allowed.'], 405);
