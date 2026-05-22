<?php
/**
 * reset_game.php
 * VOXBOUND: The Spoken Frontier
 * Adjudicates Player death recovery and total world resets.
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/spawner_worker.php';

header('Content-Type: application/json');

if (!isset($_SESSION['character_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$charId = $_SESSION['character_id'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    if ($action === 'resurrect') {
        // Bible Section 5: Resusciter (10% Gold loss, HP Restore, Move to Respawn)
        $pdo->prepare("
            UPDATE Characters 
            SET gold = gold * 0.9, 
                hitPoints = maxHitPoints, 
                currentLocationID = respawnNodeId 
            WHERE id = ?
        ")->execute([$charId]);

    } elseif ($action === 'hard_reset') {
        // Wipe State and reset attributes to base 10
        $pdo->prepare("DELETE FROM Character_NPC_State WHERE characterId = ?")->execute([$charId]);
        $pdo->prepare("DELETE FROM Character_Room_State WHERE characterId = ?")->execute([$charId]);
        $pdo->prepare("DELETE FROM ItemInstances WHERE characterId = ?")->execute([$charId]);
        
        $pdo->prepare("UPDATE Characters SET hitPoints = 100, maxHitPoints = 100, strength = 10, agility = 10, gold = 50.00, currentLocationID = 101, respawnNodeId = 101 WHERE id = ?")->execute([$charId]);
        
        spawnInitialWorldState($pdo, $charId);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}