<?php
/**
 * move_player.php
 * VOXBOUND: The Spoken Frontier
 * Player Movement Processing
 */

session_start();
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

// 1. DEBUGGING UTILITY
$debug_logs = [];
function debug_log($msg) {
    global $debug_logs;
    $debug_logs[] = "[MOVE_JUDGE] " . $msg;
}

// 2. AUTHENTICATION SHIELD
if (!isset($_SESSION['user_id']) || !isset($_SESSION['character_id'])) {
    debug_log("Unauthorized access attempt.");
    echo json_encode(['success' => false, 'error' => 'Session expired.', 'debug' => $debug_logs]);
    exit;
}

// 3. CAPTURE INPUT (Direction from Manager)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$directionInput = strtolower(trim($data['direction'] ?? ''));

debug_log("Movement request received: " . ($directionInput ?: 'NULL'));

// Map French/Command inputs to database column keys
$directionMap = [
    'nord'      => 'northTarget',
    'sud'       => 'southTarget',
    'est'       => 'eastTarget',
    'ouest'     => 'westTarget',
    'remonter'  => 'upTarget',
    'montez'    => 'upTarget',
    'descendre' => 'downTarget',
    'descendez' => 'downTarget',
    'pénétrer'  => 'inTarget',
    'entrer'    => 'inTarget',
    'sortir'    => 'outTarget'
];

if (!array_key_exists($directionInput, $directionMap)) {
    debug_log("Invalid direction key: $directionInput");
    echo json_encode(['success' => false, 'error' => 'Unknown direction.', 'debug' => $debug_logs]);
    exit;
}

$targetColumn = $directionMap[$directionInput];

try {
    // 4. FETCH CURRENT LOCATION "TRUTH"
    $charStmt = $pdo->prepare("SELECT currentLocationID FROM Characters WHERE id = :charId");
    $charStmt->execute(['charId' => $_SESSION['character_id']]);
    $currentChar = $charStmt->fetch(PDO::FETCH_ASSOC);
    $currentNodeId = $currentChar['currentLocationID'];

    debug_log("Character currently at Node: $currentNodeId. Checking path: $targetColumn");

    // 5. QUERY WORLD DATA using the new Locations table
    $nodeStmt = $pdo->prepare("SELECT $targetColumn FROM Locations WHERE nodeId = :currNode LIMIT 1");
    $nodeStmt->execute(['currNode' => $currentNodeId]);
    $nextNodeId = $nodeStmt->fetchColumn();

    // 6. ADJUDICATE MOVEMENT
    if ($nextNodeId && $nextNodeId > 0) {
        debug_log("Path found. Transitioning to Node: $nextNodeId");

        // Update Character Truth
        $updateStmt = $pdo->prepare("UPDATE Characters SET currentLocationID = :nextId WHERE id = :charId");
        $updateStmt->execute([
            'nextId' => $nextNodeId,
            'charId' => $_SESSION['character_id']
        ]);

        // Mark room as discovered in State table
        $stateStmt = $pdo->prepare("
            INSERT INTO Character_Room_State (characterId, nodeId, isDiscovered)
            VALUES (:charId, :nodeId, 1)
            ON DUPLICATE KEY UPDATE isDiscovered = 1
        ");
        $stateStmt->execute(['charId' => $_SESSION['character_id'], 'nodeId' => $nextNodeId]);

        echo json_encode([
            'success' => true,
            'newNodeId' => (int)$nextNodeId,
            'message' => "Successfully moved $directionInput.",
            'debug' => $debug_logs
        ]);
    } else {
        debug_log("Movement blocked. No exit defined for $targetColumn at Node $currentNodeId.");
        echo json_encode(['success' => false, 'error' => "Vous ne pouvez pas aller par là.", 'debug' => $debug_logs]);
    }

} catch (PDOException $e) {
    debug_log("Database Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal Server Error', 'debug' => $debug_logs]);
}
exit;