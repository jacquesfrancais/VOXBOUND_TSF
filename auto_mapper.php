<?php
/**
 * auto_mapper.php
 * VOXBOUND: The Spoken Frontier
 * Administrative Utility: Automatically calculates X, Y coordinates for the grid map.
 */

require_once __DIR__ . '/db_config.php';
session_start();

// 1. ADMIN SHIELD
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$adminCheck = $pdo->prepare("SELECT isAdmin FROM Users WHERE userId = :id");
$adminCheck->execute(['id' => $_SESSION['user_id']]);
$isAdmin = $adminCheck->fetchColumn();

if (!$isAdmin) {
    die("ACCESS DENIED: Administrative privileges required.");
}

$seedNode = 101; // The starting point (Origin 0,0)
$visited = [];
$queue = [ ['id' => $seedNode, 'x' => 0, 'y' => 0, 'z' => 0] ];
$logs = [];
$logs[] = "Initializing Coordinate Crawl...";

try {
    $pdo->query("UPDATE Locations SET mapX = NULL, mapY = NULL, mapZ = NULL");

    // 2. BREADTH-FIRST SEARCH (BFS) CRAWL
    while (!empty($queue)) {
        $current = array_shift($queue);
        $nodeId = $current['id'];
        $x = $current['x'];
        $y = $current['y'];
        $z = $current['z'];

        if (isset($visited[$nodeId])) continue;
        $visited[$nodeId] = true;

        // Update the database with calculated coordinates
        $stmt = $pdo->prepare("UPDATE Locations SET mapX = :x, mapY = :y, mapZ = :z WHERE nodeId = :id");
        $stmt->execute(['x' => $x, 'y' => $y, 'z' => $z, 'id' => $nodeId]);
        $logs[] = "Mapped Node $nodeId at ($x, $y, $z)";

        // Fetch connections
        $nodeStmt = $pdo->prepare("SELECT northTarget, southTarget, eastTarget, westTarget, upTarget, downTarget, inTarget, outTarget FROM Locations WHERE nodeId = :id");
        $nodeStmt->execute(['id' => $nodeId]);
        $links = $nodeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$links) continue;

        // Add adjacent nodes to queue with 3D relative offsets
        $directions = [
            'northTarget' => [0, -1, 0],
            'southTarget' => [0, 1, 0],
            'eastTarget'  => [1, 0, 0],
            'westTarget'  => [-1, 0, 0],
            'upTarget'    => [0, 0, 1],
            'downTarget'  => [0, 0, -1],
            'inTarget'    => [0, 0, 0], // Portals often occupy the same coordinate space
            'outTarget'   => [0, 0, 0]
        ];

        foreach ($directions as $col => $offset) {
            $targetId = $links[$col];
            if ($targetId > 0 && !isset($visited[$targetId])) {
                $queue[] = [
                    'id' => $targetId,
                    'x'  => $x + $offset[0],
                    'y'  => $y + $offset[1],
                    'z'  => $z + $offset[2]
                ];
            }
        }
    }

    $logs[] = "SUCCESS: Crawl complete. " . count($visited) . " nodes mapped.";

} catch (Exception $e) {
    $logs[] = "CRITICAL ERROR: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VOXBOUND – Auto-Mapper</title>
    <link rel="stylesheet" href="adventure-base.css">
    <link rel="stylesheet" href="admin-base.css">
</head>
<body>

    <!-- TOP NAVIGATION BAR -->
    <nav style="display:flex; justify-content: space-between; padding: 20px 40px; background: #161b22; border-bottom: 1px solid #333;">
        <div style="color: var(--primary-cyan); font-weight: bold; letter-spacing: 1px;">VOXBOUND: SYSTEM DIAGNOSTICS</div>
        <div style="display:flex; gap: 30px;">
            <a href="index.php" style="color:var(--primary-cyan); text-decoration:none;">HOME</a>
            <a href="adventure.php" style="color:white; text-decoration:none;">ADVENTURE</a>
            <a href="admin.php" style="color:white; text-decoration:none;">ADMIN</a>
            <a href="editor.php" style="color:white; text-decoration:none;">EDITOR</a>
        </div>
    </nav>

    <div class="admin-view-wrapper">
        
        <!-- CENTER: SYSTEM DIAGNOSTICS OUTPUT -->
        <main class="admin-console">
            <h2 style="color: var(--primary-cyan); margin-top: 0;">AUTO-MAPPER: COORDINATE CRAWL</h2>
            <p style="font-size: 0.8rem; color: #888; margin-bottom: 20px;">&gt; Adjudicating world geometry based on directional links...</p>
            
            <div style="font-family: var(--font-mono); font-size: 0.85rem; line-height: 1.6;">
                <?php foreach ($logs as $log): ?>
                    <div style="margin-bottom: 4px;">
                        <span style="color: #444;">&gt;</span> <?= htmlspecialchars($log) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>

        <!-- RIGHT: ACTION SIDEBAR -->
        <aside class="admin-sidebar">
            <div class="admin-btn-group">
                <a href="admin.php" class="btn-admin-solid" style="text-decoration: none; text-align: center;">[ RETURN TO ADMIN ]</a>
                <a href="editor.php" class="btn-admin-solid" style="text-decoration: none; text-align: center;">[ RETURN TO EDITOR ]</a>
            </div>
        </aside>

    </div>
</body>
</html>