<?php
/**
 * spawner_worker.php
 * VOXBOUND: The Spoken Frontier
 * The Judge responsible for initializing a new character's isolated world state.
 */

/**
 * Spawns NPCs, items, and initial room states for a new character.
 * @param PDO $pdo The database connection.
 * @param int $charId The unique ID of the new character.
 */
function spawnInitialWorldState($pdo, $charId) {
    // 1. INITIALIZE NPC STATE
    // Copies library defaults into the character's specific NPC state table.
    $npcSpawn = $pdo->prepare("
        INSERT INTO Character_NPC_State (characterId, npcId, currentLocationId, currentHitPoints)
        SELECT :charId, npcId, homeNodeId, maxHitPoints FROM Npcs
    ");
    $npcSpawn->execute(['charId' => $charId]);
    error_log("[SPAWNER] Spawned NPCs for Char ID: $charId");

    // 2. INITIALIZE ROOM STATE
    // Ensures the starting room (101) is marked as discovered.
    $roomSpawn = $pdo->prepare("
        INSERT INTO Character_Room_State (characterId, nodeId, isDiscovered)
        VALUES (:charId, 101, 1)
    ");
    $roomSpawn->execute(['charId' => $charId]);

    // 3. INITIALIZE ITEM INSTANCES
    // Populates items based on WorldTemplates. 
    // COALESCE ensures starting gear (ownerId NULL in template) is assigned to the player.
    // Only items assigned to 'Player' should start as equipped.
    $itemSpawn = $pdo->prepare("
        INSERT INTO ItemInstances (characterId, itemId, ownerType, ownerId, isEquipped)
        SELECT :charId, itemId, ownerType, COALESCE(ownerId, :charId_alt), (CASE WHEN ownerType = 'Player' THEN 1 ELSE 0 END)
        FROM WorldTemplates
    ");
    $itemSpawn->execute([
        'charId'     => $charId,
        'charId_alt' => $charId
    ]);
    
    error_log("[SPAWNER] Initialized world items for Char ID: $charId");
    return true;
}