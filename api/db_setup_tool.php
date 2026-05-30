<?php
require_once __DIR__ . '/db_connect.php';

echo "<h1>Database Setup Tool</h1>";

$sqlFile = __DIR__ . '/schema.sql';
if (!file_exists($sqlFile)) {
    die("Error: schema.sql not found in the api folder.");
}

$sql = file_get_contents($sqlFile);

// Split schema.sql into individual statements. PDO::exec() does not reliably
// run multi-statement SQL when emulated prepares are off, so we split on `;`
// at end-of-line and run each statement separately.
function splitSqlStatements($sql) {
    // Strip line comments (-- ...) and blank lines
    $clean = preg_replace('/^\s*--.*$/m', '', $sql);
    // Split on semicolons that terminate a line
    $parts = preg_split('/;\s*[\r\n]+/', $clean);
    $statements = [];
    foreach ($parts as $p) {
        $t = trim($p);
        if ($t !== '') $statements[] = $t;
    }
    return $statements;
}

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $statements = splitSqlStatements($sql);
    $ran = 0;
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
        $ran++;
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "<h3 style='color:green;'>Success: The database schema has been imported!</h3>";
    echo "<p>Executed {$ran} SQL statements. All tables (games, users, reviews, tierlists, etc.) have been created and seeded.</p>";
} catch (PDOException $e) {
    echo "<h3 style='color:red;'>SQL Error:</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
