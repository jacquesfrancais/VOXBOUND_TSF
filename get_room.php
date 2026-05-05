<?php
/**
 * get_room.php
 * VOXBOUND: The Spoken Frontier
 * Fetches environmental "Truth" for the current location.
 */

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');

// 1. DEBUGGING UTILITY
$debug_logs = [];
function debug_log($msg) {
    global $debug_logs;
    $debug_logs[] = "[ROOM_JUDGE] " . $msg;
}

// 2. AUTHENTICATION SHIELD
if (!isset($_SESSION['user_id']) || !isset($_SESSION['character_id'])) {
    debug_log("Unauthorized access attempt or session expired.");
    echo json_encode(['success' => false, 'error' => 'Session expired.', 'debug' => $debug_logs]);
    exit;
}

try {
    // 3. FETCH CHARACTER LOCATION & STATS
    // We fetch stats as well to keep the sidebar in sync during world updates.
    $charStmt = $pdo->prepare("SELECT currentLocationID, hitPoints, maxHitPoints, strength, agility, gold FROM Characters WHERE id = :charId");
    $charStmt->execute(['charId' => $_SESSION['character_id']]);
    $character = $charStmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        throw new Exception("Character record not found.");
    }

    $nodeId = $character['currentLocationID'];
    debug_log("Fetching data for Node: $nodeId");

    // 3.5 SELF-HEALING: Ensure current room is marked as discovered
    $discoveryStmt = $pdo->prepare("
        INSERT INTO Character_Room_State (characterId, nodeId, isDiscovered)
        VALUES (:charId, :nodeId, 1)
        ON DUPLICATE KEY UPDATE isDiscovered = 1
    ");
    $discoveryStmt->execute(['charId' => $_SESSION['character_id'], 'nodeId' => $nodeId]);

    // 4. FETCH ROOM DESCRIPTION
    // Following the Bible: Bilingual content is stored; we fetch the French version for the primary display.
    $nodeStmt = $pdo->prepare("SELECT title, textFrench, textEnglish, northTarget, southTarget, eastTarget, westTarget, upTarget, downTarget, inTarget, outTarget FROM Locations WHERE nodeId = :nodeId LIMIT 1");
    $nodeStmt->execute(['nodeId' => $nodeId]);
    $nodeData = $nodeStmt->fetch(PDO::FETCH_ASSOC);
    
    $title = $nodeData['title'] ?? "Unknown Location";
    $descriptionFr = $nodeData['textFrench'] ?? "L'obscurité remplit la pièce. Le terminal ne renvoie aucune donnée.";
    $descriptionEn = $nodeData['textEnglish'] ?? "Darkness fills the room. The terminal returns no data.";

    // 5. FETCH NPCs IN ROOM
    // Join the state table with the library to get names for the specific character
    $npcStmt = $pdo->prepare("
        SELECT n.npcId, n.npcNameFrench, n.npcNameEnglish, n.greetingFrench
        FROM Character_NPC_State s
        JOIN Npcs n ON s.npcId = n.npcId
        WHERE s.characterId = :charId AND s.currentLocationId = :nodeId AND s.isDead = 0
    ");
    $npcStmt->execute(['charId' => $_SESSION['character_id'], 'nodeId' => $nodeId]);
    $npcs = $npcStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5.5 FETCH PARTY (Allies following the player)
    $partyStmt = $pdo->prepare("
        SELECT n.npcId, n.npcNameFrench, n.npcNameEnglish, s.currentHitPoints, n.strength, n.agility
        FROM Character_NPC_State s
        JOIN Npcs n ON s.npcId = n.npcId
        WHERE s.characterId = :charId AND s.isFollowing = 1 AND s.isDead = 0
    ");
    $partyStmt->execute(['charId' => $_SESSION['character_id']]);
    $party = $partyStmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. FETCH ITEMS IN ROOM
    // In the new schema, items on the floor have ownerType = 'Room'
    $itemStmt = $pdo->prepare("
        SELECT l.nameFrench, l.nameEnglish
        FROM ItemInstances i
        JOIN ItemLibrary l ON i.itemId = l.itemId
        WHERE i.ownerType = 'Room' AND i.ownerId = :nodeId
    ");
    $itemStmt->execute(['nodeId' => $nodeId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // 6.5 FETCH DISCOVERED NODES FOR MINI-MAP
    $mapStmt = $pdo->prepare("
        SELECT l.nodeId, l.title, l.mapX, l.mapY, l.mapZ
        FROM Character_Room_State s
        JOIN Locations l ON s.nodeId = l.nodeId
        WHERE s.characterId = :charId AND s.isDiscovered = 1
    ");
    $mapStmt->execute(['charId' => $_SESSION['character_id']]);
    $mapNodes = $mapStmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. CONSTRUCT RESPONSE
    // The structure matches exactly what engine.js's updateUI(data) expects.
    echo json_encode([
        'success'     => true,
        'nodeId'      => (int)$nodeId,
        'title'       => $title,
        'descriptionFr' => $descriptionFr,
        'descriptionEn' => $descriptionEn,
        'npcs'        => $npcs,
        'party'       => $party,
        'items'       => $items,
        'mapNodes'    => $mapNodes,
        'exits'       => [
            'nord'   => (int)($nodeData['northTarget'] ?? 0),
            'sud'    => (int)($nodeData['southTarget'] ?? 0),
            'est'    => (int)($nodeData['eastTarget'] ?? 0),
            'ouest'  => (int)($nodeData['westTarget'] ?? 0),
            'remonter' => (int)($nodeData['upTarget'] ?? 0),
            'descendre' => (int)($nodeData['downTarget'] ?? 0),
            'pénétrer' => (int)($nodeData['inTarget'] ?? 0),
            'sortir' => (int)($nodeData['outTarget'] ?? 0)
        ],
        'stats'       => [
            'hitPoints'    => (int)$character['hitPoints'],
            'maxHitPoints' => (int)$character['maxHitPoints'],
            'strength'     => (int)$character['strength'],
            'agility'      => (int)$character['agility'],
            'gold'         => $character['gold']
        ],
        'debug'       => $debug_logs
    ]);

} catch (Exception $e) {
    debug_log("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => 'World Sync Error: ' . $e->getMessage(),
        'debug'   => $debug_logs
    ]);
} catch (PDOException $e) {
    debug_log("DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database connection lost.', 'debug' => $debug_logs]);
}

exit;