<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$charId = $_SESSION['character_id'];
$tier = $data['tier'];

if ($tier !== 'Pas compris') {
    // 1. Reward Economy
    $reward = ($tier === 'Parfait') ? 0.20 : 0.10;
    $pdo->prepare("UPDATE Characters SET gold = gold + :r WHERE id = :id")->execute(['r' => $reward, 'id' => $charId]);

    // 2. Update State
    $next = $data['next'];
    if ($next === 'END') {
        $pdo->prepare("UPDATE Character_NPC_State SET currentDialogueNode = 'START' WHERE npcId = :n AND characterId = :c")
            ->execute(['n' => $data['npcId'], 'c' => $charId]);
        echo json_encode(['finished' => true]);
    } else {
        $pdo->prepare("UPDATE Character_NPC_State SET currentDialogueNode = :next WHERE npcId = :n AND characterId = :c")
            ->execute(['next' => $next, 'n' => $data['npcId'], 'c' => $charId]);
        
        // Fetch next node content
        $stmt = $pdo->prepare("SELECT dialogueTreeId FROM Npcs WHERE npcId = ?");
        $stmt->execute([$data['npcId']]);
        $jsonFile = $stmt->fetchColumn();
        $json = json_decode(file_get_contents("dialogues/" . $jsonFile), true);
        echo json_encode($json['nodes'][$next]);
    }
} else {
    // Failed attempt - keep them on same node
    echo json_encode(['error' => 'Try again']);
}