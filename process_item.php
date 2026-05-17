<?php
/**
 * process_item.php
 * VOXBOUND: The Spoken Frontier
 * Adjudicates item interactions (Take, Drop, Use).
 */

session_start();
require_once __DIR__ . '/db_config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$debug_logs = [];
function debug_log($msg) {
    global $debug_logs;
    $debug_logs[] = "[ITEM_JUDGE] " . $msg;
}

// 1. AUTHENTICATION SHIELD
if (!isset($_SESSION['user_id']) || !isset($_SESSION['character_id'])) {
    echo json_encode(['success' => false, 'error' => 'Session expired.']);
    exit;
}

$charId = $_SESSION['character_id'];
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$action = $data['action'] ?? '';
$instanceId = (int)($data['instanceId'] ?? 0);

try {
    // 2. FETCH CHARACTER STATS
    $charStmt = $pdo->prepare("SELECT strength, currentLocationID FROM Characters WHERE id = :id");
$charStmt->execute(['id' => $charId]);
    $character = $charStmt->fetch(PDO::FETCH_ASSOC);
    
    $maxCapacity = $character['strength'] * 5;

    if ($action === 'take') {
        // 3. FETCH ITEM TRUTH
        $itemStmt = $pdo->prepare("
            SELECT i.instanceId, i.ownerId, l.weight, l.nameFrench 
            FROM ItemInstances i 
            JOIN ItemLibrary l ON i.itemId = l.itemId 
            WHERE i.instanceId = :instId AND i.characterId = :charId AND i.ownerType = 'Room'
        ");
        $itemStmt->execute(['instId' => $instanceId, 'charId' => $charId]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item || $item['ownerId'] != $character['currentLocationID']) {
            throw new Exception("L'objet n'est plus ici.");
        }

        // 4. CALCULATE CURRENT INVENTORY WEIGHT
        $weightStmt = $pdo->prepare("
            SELECT SUM(l.weight) 
            FROM ItemInstances i 
            JOIN ItemLibrary l ON i.itemId = l.itemId 
            WHERE i.characterId = :charId AND i.ownerType = 'Player'
        ");
        $weightStmt->execute(['charId' => $charId]);
        $currentWeight = (float)$weightStmt->fetchColumn();

        // 5. ADJUDICATE CAPACITY
        if (($currentWeight + $item['weight']) > $maxCapacity) {
            debug_log("Weight Limit Exceeded: " . ($currentWeight + $item['weight']) . " / $maxCapacity");
            echo json_encode([
                'success' => false, 
                'error' => "C'est trop lourd ! Vous n'êtes pas assez fort.",
                'debug' => $debug_logs
            ]);
            exit;
        }

        // 6. EXECUTE THE TRANSFER
        $updateStmt = $pdo->prepare("
            UPDATE ItemInstances 
            SET ownerType = 'Player', ownerId = :charId 
            WHERE instanceId = :instId
        ");
        $updateStmt->execute(['charId' => $charId, 'instId' => $instanceId]);

        echo json_encode([
            'success' => true,
            'message' => "Vous avez pris : " . $item['nameFrench'],
            'debug' => $debug_logs
        ]);
    } elseif ($action === 'drop') {
        // 3. FETCH ITEM TRUTH (Ensure the player actually has it)
        $itemStmt = $pdo->prepare("
            SELECT i.instanceId, l.nameFrench 
            FROM ItemInstances i 
            JOIN ItemLibrary l ON i.itemId = l.itemId 
            WHERE i.instanceId = :instId AND i.characterId = :charId AND i.ownerType = 'Player'
        ");
        $itemStmt->execute(['instId' => $instanceId, 'charId' => $charId]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception("Vous n'avez pas cet objet dans votre inventaire.");
        }

        // 4. EXECUTE THE TRANSFER (Move instance to the current Room)
        $updateStmt = $pdo->prepare("
            UPDATE ItemInstances 
            SET ownerType = 'Room', ownerId = :locId 
            WHERE instanceId = :instId
        ");
        $updateStmt->execute([
            'locId' => $character['currentLocationID'], 
            'instId' => $instanceId
        ]);

        echo json_encode([
            'success' => true,
            'message' => "Vous avez posé : " . $item['nameFrench'],
            'debug' => $debug_logs
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug' => $debug_logs
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Database error during interaction.',
        'debug' => $debug_logs
    ]);
}