<?php
/**
 * process_command.php
 * VOXBOUND: The Spoken Frontier
 * Command Processing (Stateless Judge)
 */

session_start();
require_once __DIR__ . '/db_config.php';

// ENABLE DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$debug_logs = [];
function debug_log($msg) {
    global $debug_logs;
    $debug_logs[] = "[COMMAND_JUDGE] " . $msg;
}

debug_log("Request Initialized. Method: " . $_SERVER['REQUEST_METHOD']);

// 1. AUTHENTICATION SHIELD
if (!isset($_SESSION['user_id']) || !isset($_SESSION['character_id'])) {
    debug_log("Unauthorized Access Attempt. Session variables missing.");
    echo json_encode(['error' => 'Security Error: Session Expired', 'debug' => $debug_logs]);
    exit;
}
debug_log("Auth Verified. User: {$_SESSION['user_id']} | Char: {$_SESSION['character_id']}");

// 2. CAPTURE INPUT FROM MANAGER (engine.js or speech.js)
$rawInput = file_get_contents('php://input');
$debug_logs[] = "[COMMAND_JUDGE] Raw Input Received: " . strlen($rawInput) . " bytes.";
$data = json_decode($rawInput, true);

$command = trim($data['command'] ?? '');
$score   = floatval($data['score'] ?? 0);
$tier    = $data['tier'] ?? 'Pas compris';

debug_log("Processing Command: '$command' | Tier: $tier");

if (!$command) {
    echo json_encode(['error' => 'Null Command', 'debug' => $debug_logs]);
    exit;
}

$response = [
    'success' => true,
    'command' => $command,
    'category' => 'unknown',
    'action' => 'none',
    'reward_granted' => 0,
    'debug' => &$debug_logs
];

// 3. REWARD ECONOMY (Project Bible v2.5 Section 4)
// Reward logic: 0.10 for "Bien", 0.20 for "Parfait"
if ($tier === 'Parfait') $response['reward_granted'] = 0.20;
elseif ($tier === 'Bien') $response['reward_granted'] = 0.10;

if ($response['reward_granted'] > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE Characters SET gold = gold + :reward, speechSuccessCount = speechSuccessCount + 1 WHERE id = :charId");
        $result = $stmt->execute(['reward' => $response['reward_granted'], 'charId' => $_SESSION['character_id']]);
        
        if ($result) {
            debug_log("Linguistic Reward Applied: +{$response['reward_granted']} Gold to DB.");
        } else {
            debug_log("DB Warning: Gold update failed to execute.");
        }
    } catch (PDOException $e) {
        debug_log("DB Error during reward: " . $e->getMessage());
    }
}

// 4. CATEGORY ANALYSIS (The Judge identifies the intent)
$cmdLower = mb_strtolower($command);

// Navigation: Nord, Sud, Est, Ouest, Montez, Descendez, Allez...
if (preg_match('/^(nord|sud|est|ouest|remonter|descendre|montez|descendez|sortir|pénétrer|entrer|allez)/', $cmdLower, $matches)) {
    $response['category'] = 'navigation';
    $response['action'] = 'move_player.php';
    debug_log("Intent matched: Navigation via keyword '{$matches[1]}'");
}
// Observation: Regardez, Examinez, Cherchez, Inventaire
elseif (preg_match('/^(regardez|examinez|cherchez|inventaire)/', $cmdLower, $matches)) {
    $response['category'] = 'observation';
    $response['action'] = ($cmdLower === 'inventaire') ? 'get_inventory.php' : 'get_room.php';
    debug_log("Intent matched: Observation via keyword '{$matches[1]}'");
}
// Interaction: Prenez, Posez, Utilisez, Ouvrez
elseif (preg_match('/^(prenez|posez|utilisez|ouvrez)/', $cmdLower, $matches)) {
    $response['category'] = 'interaction';
    $response['action'] = 'process_item.php';
    debug_log("Intent matched: Interaction via keyword '{$matches[1]}'");
}
// Social: Parlez, Demandez, Saluez
elseif (preg_match('/^(parlez|demandez|saluez)/', $cmdLower, $matches)) {
    $response['category'] = 'social';
    $response['action'] = 'trigger_dialogue_ui'; 
    debug_log("Intent matched: Social via keyword '{$matches[1]}'");
}
// Combat: Attaquez, Fuyez, Défendez, Lancez
elseif (preg_match('/^(attaquez|fuyez|défendez|lancez)/', $cmdLower, $matches)) {
    $response['category'] = 'combat';
    $response['action'] = 'process_combat.php';
    debug_log("Intent matched: Combat via keyword '{$matches[1]}'");
}
else {
    debug_log("Unknown command pattern.");
    $response['success'] = false;
}

echo json_encode($response);