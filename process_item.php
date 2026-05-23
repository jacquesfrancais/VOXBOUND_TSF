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
            SET ownerType = 'Player', ownerId = :charId, isEquipped = 0 
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
            SET ownerType = 'Room', ownerId = :locId, isEquipped = 0 
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
    } elseif ($action === 'equip') {
        // 1. Fetch Item Details
        $stmt = $pdo->prepare("SELECT i.isEquipped, l.itemType, l.nameFrench FROM ItemInstances i JOIN ItemLibrary l ON i.itemId = l.itemId WHERE i.instanceId = ? AND i.characterId = ?");
        $stmt->execute([$instanceId, $charId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) throw new Exception("Objet non trouvé.");

        if ($item['isEquipped'] == 1) {
            // Simply unequip
            $pdo->prepare("UPDATE ItemInstances SET isEquipped = 0 WHERE instanceId = ?")->execute([$instanceId]);
        } else {
            // Equip logic with constraints
            if ($item['itemType'] === 'Weapon') {
                // Unequip any existing weapons
                $pdo->prepare("
                    UPDATE ItemInstances i 
                    JOIN ItemLibrary l ON i.itemId = l.itemId 
                    SET i.isEquipped = 0 
                    WHERE i.characterId = ? AND l.itemType = 'Weapon'
                ")->execute([$charId]);
            } elseif ($item['itemType'] === 'Armor') {
                // Count currently equipped armors
                $countStmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM ItemInstances i 
                    JOIN ItemLibrary l ON i.itemId = l.itemId 
                    WHERE i.characterId = ? AND l.itemType = 'Armor' AND i.isEquipped = 1
                ");
                $countStmt->execute([$charId]);
                if ($countStmt->fetchColumn() >= 2) {
                    echo json_encode([
                        'success' => false, 
                        'error' => "Limite d'armure atteinte. Déséquipez un objet d'abord.",
                        'debug' => $debug_logs
                    ]);
                    exit;
                }
            }

            // Apply equipment
            $pdo->prepare("UPDATE ItemInstances SET isEquipped = 1 WHERE instanceId = ?")->execute([$instanceId]);
        }

        echo json_encode(['success' => true, 'debug' => $debug_logs]);
    } elseif ($action === 'use') {
        // 1. Fetch Item and Player Stats
        $stmt = $pdo->prepare("
            SELECT i.instanceId, l.nameFrench, l.extraData, l.itemType, c.hitPoints, c.maxHitPoints, c.strength, c.agility
            FROM ItemInstances i 
            JOIN ItemLibrary l ON i.itemId = l.itemId 
            JOIN Characters c ON i.characterId = c.id
            WHERE i.instanceId = ? AND i.characterId = ? AND i.ownerType = 'Player'
        ");
        $stmt->execute([$instanceId, $charId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data || $data['itemType'] !== 'Consumable') {
            throw new Exception("Cet objet ne peut pas être utilisé.");
        }

        // Prioritize Instance-specific data (charges) over Library defaults
        $instanceStmt = $pdo->prepare("SELECT extraData FROM ItemInstances WHERE instanceId = ?");
        $instanceStmt->execute([$instanceId]);
        $instanceExtra = $instanceStmt->fetchColumn();
        
        $extra = json_decode($instanceExtra ?: $data['extraData'], true) ?: [];

        $feedback = "Vous avez utilisé : " . $data['nameFrench'] . ". ";
        
        // 2. Adjudicate Effects (Scalable for future buffs)
        $updates = [];
        $params = [':id' => $charId];

        if (isset($extra['hpRestore'])) {
            $newHp = min($data['maxHitPoints'], $data['hitPoints'] + $extra['hpRestore']);
            $updates[] = "hitPoints = :hp";
            $params[':hp'] = $newHp;
            $feedback .= "+" . ($newHp - $data['hitPoints']) . " HP. ";
        }
        
        if (isset($extra['strBoost'])) {
            $updates[] = "strength = strength + :sb";
            $params[':sb'] = (int)$extra['strBoost'];
            $feedback .= "+" . $extra['strBoost'] . " STR. ";
        }

        if (isset($extra['agiBoost'])) {
            $updates[] = "agility = agility + :ab";
            $params[':ab'] = (int)$extra['agiBoost'];
            $feedback .= "+" . $extra['agiBoost'] . " AGI. ";
        }

        if (!empty($updates)) {
            $sql = "UPDATE Characters SET " . implode(', ', $updates) . " WHERE id = :id";
            $pdo->prepare($sql)->execute($params);
        }

        // 3. Adjudicate Charges
        $charges = isset($extra['charges']) ? (int)$extra['charges'] : 1;
        $charges--;

        if ($charges <= 0) {
            // Item spent
            $pdo->prepare("DELETE FROM ItemInstances WHERE instanceId = ?")->execute([$instanceId]);
            debug_log("Consumable instance $instanceId depleted and removed.");
        } else {
            // Update charges in JSON
            $extra['charges'] = $charges;
            $newJson = json_encode($extra);
            $pdo->prepare("UPDATE ItemInstances SET extraData = ? WHERE instanceId = ?")->execute([$newJson, $instanceId]);
            $feedback .= "($charges utilisations restantes)";
        }

        echo json_encode([
            'success' => true,
            'message' => $feedback,
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