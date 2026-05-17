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

/**
 * COMMAND REGISTRY
 * Defines the relationship between linguistic patterns and system intents.
 */
$commandRegistry = [
    'navigation'  => ['pattern' => '/^(nord|sud|est|ouest|remonter|descendre|montez|descendez|sortir|pénétrer|entrer|allez)/', 'action' => 'move_player.php'],
    'observation' => ['pattern' => '/^(regarder|regardez|examiner|examinez|chercher|cherchez|inventaire)/', 'action' => ($cmdLower === 'inventaire') ? 'get_inventory.php' : 'get_room.php'],
    'interaction' => ['pattern' => '/^(prendre|prenez|poser|posez|utiliser|utilisez|ouvrir|ouvrez)/', 'action' => 'process_item.php'],
    'social'      => ['pattern' => '/^(parlez|demandez|saluez)/', 'action' => 'trigger_dialogue_ui'],
    'combat'      => ['pattern' => '/^(attaquez|fuyez|défendez|lancez)/', 'action' => 'process_combat.php']
];

$matched = false;
foreach ($commandRegistry as $category => $rules) {
    if (preg_match($rules['pattern'], $cmdLower, $matches)) {
        $response['category'] = $category;
        $response['action']   = $rules['action'];
        debug_log("Intent matched: " . ucfirst($category) . " via keyword '{$matches[1]}'");
        $matched = true;
        break;
    }
}

if (!$matched) {
    debug_log("Unknown command pattern: '$cmdLower'");
    $response['success'] = false;
}

echo json_encode($response);