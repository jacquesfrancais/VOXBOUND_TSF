<?php
session_start();
require_once 'db_config.php';

$npcId = (int)$_GET['npcId'];
$charId = $_SESSION['character_id'];

try {
    // 1. Get NPC State and JSON file
    $stmt = $pdo->prepare("
        SELECT s.currentDialogueNode, n.dialogueTreeId 
        FROM Character_NPC_State s
        JOIN Npcs n ON s.npcId = n.npcId
        WHERE s.npcId = :npcId AND s.characterId = :charId
    ");
    $stmt->execute(['npcId' => $npcId, 'charId' => $charId]);
    $state = $stmt->fetch();

    if ($state && $state['dialogueTreeId']) {
        $json = json_decode(file_get_contents("dialogues/" . $state['dialogueTreeId']), true);
        $currentNode = $state['currentDialogueNode'] === 'START' ? $json['startNode'] : $state['currentDialogueNode'];
        
        $nodeData = $json['nodes'][$currentNode];
        echo json_encode($nodeData);
    } else {
        echo json_encode(["error" => "No dialogue found for this NPC."]);
    }
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}