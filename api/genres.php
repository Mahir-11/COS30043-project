<?php
/**
 * genres.php — read-only list of game genres.
 * GET → list all genres
 */
require_once __DIR__ . '/db_connect.php';

$method = getMethod();

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM genres ORDER BY name ASC");
    $genres = $stmt->fetchAll();
    foreach ($genres as &$g) {
        $g['id'] = (int) $g['id'];
    }
    jsonResponse($genres);
}

jsonResponse(['message' => 'Method not allowed.'], 405);
