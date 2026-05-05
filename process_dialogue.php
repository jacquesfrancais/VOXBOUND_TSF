<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$debug_logs = [];
$debug_logs[] = "[DIALOGUE_JUDGE] Input received for NPC " . $data['npcId'];

$charId = $_SESSION['character_id'];
$tier = $data['tier'];

if ($tier !== 'Pas compris') {
    // 1. Reward Economy
    $reward = ($tier === 'Parfait') ? 0.20 : 0.10;
    $pdo->prepare("UPDATE Characters SET gold = gold + :r WHERE id = :id")->execute(['r' => $reward, 'id' => $charId]);

    // 1.5 CHECK FOR TRIGGERS
    // We need to look at the JSON to see if the chosen option has a trigger
    $stmt = $pdo->prepare("
        SELECT s.currentDialogueNode, n.dialogueTreeId 
        FROM Character_NPC_State s
        JOIN Npcs n ON s.npcId = n.npcId
        WHERE s.npcId = :n AND s.characterId = :c
    ");
    $stmt->execute(['n' => $data['npcId'], 'c' => $charId]);
    $state = $stmt->fetch();

    if ($state) {
        $json = json_decode(file_get_contents("dialogues/" . $state['dialogueTreeId']), true);
        $currentNode = ($state['currentDialogueNode'] === 'START') ? $json['startNode'] : $state['currentDialogueNode'];
        
        $debug_logs[] = "[DIALOGUE_JUDGE] Current Node in DB: $currentNode";

        foreach ($json['nodes'][$currentNode]['options'] as $opt) {
            if ($opt['fr'] === $data['expected'] && $opt['next'] === $data['next']) {
                $debug_logs[] = "[DIALOGUE_JUDGE] Option match found. Checking triggers...";
                if (isset($opt['trigger']) && $opt['trigger'] === 'recruit_npc') {
                    $debug_logs[] = "[DIALOGUE_JUDGE] Executing trigger: recruit_npc";
                    $pdo->prepare("UPDATE Character_NPC_State SET isFollowing = 1 WHERE npcId = :n AND characterId = :c")
                        ->execute(['n' => $data['npcId'], 'c' => $charId]);
                }
                break;
            }
        }
    }

    // 2. Update State
    $next = $data['next'];
    if ($next === 'END') {
        $pdo->prepare("UPDATE Character_NPC_State SET currentDialogueNode = 'START' WHERE npcId = :n AND characterId = :c")
            ->execute(['n' => $data['npcId'], 'c' => $charId]);
        echo json_encode(['finished' => true, 'debug' => $debug_logs]);
    } else {
        $pdo->prepare("UPDATE Character_NPC_State SET currentDialogueNode = :next WHERE npcId = :n AND characterId = :c")
            ->execute(['next' => $next, 'n' => $data['npcId'], 'c' => $charId]);
        
        // Fetch next node content
        $stmt = $pdo->prepare("SELECT dialogueTreeId FROM Npcs WHERE npcId = ?");
        $stmt->execute([$data['npcId']]);
        $jsonFile = $stmt->fetchColumn();
        $json = json_decode(file_get_contents("dialogues/" . $jsonFile), true);
        $response = $json['nodes'][$next];
        $response['debug'] = $debug_logs;
        echo json_encode($response);
    }
} else {
    echo json_encode(['error' => 'Try again', 'debug' => $debug_logs]);
}