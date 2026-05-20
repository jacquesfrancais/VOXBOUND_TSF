<?php
/**
 * process_combat.php
 * VOXBOUND: The Spoken Frontier
 * The Judge responsible for turn-based tactical combat math and AI targeting.
 */

session_start();
require_once __DIR__ . '/db_config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$charId = $_SESSION['character_id'];
$targetNpcId = (int)($data['npcId'] ?? 0);
$tier = $data['tier'] ?? 'Pas compris';

$debug_logs = [];
function debug_log($msg) { global $debug_logs; $debug_logs[] = "[COMBAT_JUDGE] " . $msg; }

debug_log("Turn initiated. Target: $targetNpcId | Linguistic Tier: $tier");

$log = [];
function combat_log($msg) { global $log; $log[] = $msg; }

/**
 * Parses dice strings like "2d6" and returns damage.
 */
function rollDice($diceStr, $isParfait = false) {
    if (!preg_match('/(\d+)d(\d+)/', $diceStr, $m)) return 0;
    if ($isParfait) return (int)$m[1] * (int)$m[2]; // Max Damage
    $total = 0;
    for ($i = 0; $i < (int)$m[1]; $i++) $total += rand(1, (int)$m[2]);
    return $total;
}

try {
    // 1. FETCH ACTORS
    // Player Stats
    $pStmt = $pdo->prepare("SELECT * FROM Characters WHERE id = ?");
    $pStmt->execute([$charId]);
    $player = $pStmt->fetch(PDO::FETCH_ASSOC);
    debug_log("Player Data: HP={$player['hitPoints']}, STR={$player['strength']}");

    // Target Monster State
    $mStmt = $pdo->prepare("SELECT s.*, n.npcNameFrench, n.strength, n.agility, n.maxHitPoints FROM Character_NPC_State s JOIN Npcs n ON s.npcId = n.npcId WHERE s.characterId = ? AND s.npcId = ?");
    $mStmt->execute([$charId, $targetNpcId]);
    $monster = $mStmt->fetch(PDO::FETCH_ASSOC);
    debug_log("Monster Data: HP={$monster['currentHitPoints']}, STR={$monster['strength']}");

    // 1.5 ALIVE CHECK (Guard against attacking corpses)
    if (!$monster || $monster['isDead'] == 1) {
        debug_log("Abort: Target is already dead.");
        combat_log("Cette cible est déjà sans vie.");
        echo json_encode(['success' => true, 'victory' => true, 'log' => $log, 'debug' => $debug_logs]);
        exit;
    }

    // Allies State
    $aStmt = $pdo->prepare("SELECT s.*, n.npcNameFrench, n.strength, n.maxHitPoints FROM Character_NPC_State s JOIN Npcs n ON s.npcId = n.npcId WHERE s.characterId = ? AND s.isFollowing = 1 AND s.isDead = 0");
    $aStmt->execute([$charId]);
    $allies = $aStmt->fetchAll(PDO::FETCH_ASSOC);
    debug_log("Allies Found: " . count($allies));

    $threatMap = []; // "on-the-fly" targeting pool

    // 2. PLAYER TURN
    if ($tier === 'Pas compris') {
        combat_log("Votre attaque a échoué ! (Prononciation incorrecte)");
        $threatMap['player'] = 0;
    } else {
        // Find equipped weapon
        $wStmt = $pdo->prepare("SELECT l.extraData FROM ItemInstances i JOIN ItemLibrary l ON i.itemId = l.itemId WHERE i.characterId = ? AND i.ownerType = 'Player' AND i.itemId IN (SELECT itemId FROM ItemLibrary WHERE itemType = 'Weapon') LIMIT 1");
        $wStmt->execute([$charId]);
        $weapon = json_decode($wStmt->fetchColumn() ?: '{"dice":"1d4","strBonus":0}', true);
        debug_log("Equipped Weapon: Dice={$weapon['dice']}, Bonus=" . ($weapon['strBonus'] ?? 0));
        
        $baseDamage = rollDice($weapon['dice'], ($tier === 'Parfait'));
        $totalDamage = $baseDamage + (int)$player['strength'] + (int)($weapon['strBonus'] ?? 0);
        if ($tier === 'Bien') $totalDamage = floor($totalDamage / 2);

        debug_log("Player Strike Math: ($baseDamage + {$player['strength']} + " . ($weapon['strBonus'] ?? 0) . ") Mod: $tier = $totalDamage");
        $threatMap['player'] = $totalDamage;
        $monster['currentHitPoints'] -= $totalDamage;
        combat_log("Vous frappez le {$monster['npcNameFrench']} pour {$totalDamage} dégâts ! ({$tier})");
    }

    // 3. ALLY TURNS (Automatic support)
    foreach ($allies as $ally) {
        if ($monster['currentHitPoints'] <= 0) break;
        $allyDamage = rand(1, 4) + floor($ally['strength'] / 2);
        $threatMap['ally_'.$ally['npcId']] = $allyDamage;
        debug_log("Ally {$ally['npcNameFrench']} Strike: $allyDamage");
        $monster['currentHitPoints'] -= $allyDamage;
        combat_log("{$ally['npcNameFrench']} attaque et inflige {$allyDamage} dégâts !");
    }

    // 4. CHECK MONSTER DEATH
    if ($monster['currentHitPoints'] <= 0) {
        $monster['currentHitPoints'] = 0;
        $pdo->prepare("UPDATE Character_NPC_State SET currentHitPoints = 0, isDead = 1 WHERE characterId = ? AND npcId = ?")->execute([$charId, $targetNpcId]);

        // LOOT SYSTEM: Transfer lootable items from NPC to the Room
        $lootStmt = $pdo->prepare("
            SELECT i.instanceId, l.extraData 
            FROM ItemInstances i 
            JOIN ItemLibrary l ON i.itemId = l.itemId 
            WHERE i.characterId = :charId AND i.ownerType = 'NPC' AND i.ownerId = :npcId
        ");
        $lootStmt->execute(['charId' => $charId, 'npcId' => $targetNpcId]);
        $items = $lootStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $extra = json_decode($item['extraData'], true);
            // Drop item if it isn't explicitly flagged as non-lootable (e.g. natural weapons)
            if (!isset($extra['lootable']) || $extra['lootable'] !== false) {
                $pdo->prepare("UPDATE ItemInstances SET ownerType = 'Room', ownerId = :locId WHERE instanceId = :instId")
                    ->execute([
                        'locId' => $monster['currentLocationId'],
                        'instId' => $item['instanceId']
                    ]);
                debug_log("Loot instance {$item['instanceId']} dropped into Room {$monster['currentLocationId']}");
            }
        }

        // 4.5 ATTRIBUTE BOOST (Linguistic Growth)
        // The player gains 10% of the monster's base stats (minimum 1)
        $strGain = (int)ceil($monster['strength'] * 0.10);
        $agiGain = (int)ceil($monster['agility'] * 0.10);
        $hpGain  = (int)ceil($monster['maxHitPoints'] * 0.10);

        $pdo->prepare("UPDATE Characters SET strength = strength + ?, agility = agility + ?, hitPoints = hitPoints + ?, maxHitPoints = maxHitPoints + ? WHERE id = ?")
            ->execute([$strGain, $agiGain, $hpGain, $hpGain, $charId]);

        combat_log("Votre expérience augmente ! +$strGain Force, +$agiGain Agilité, +$hpGain Points de Vie.");
        debug_log("Victory Boost applied: STR +$strGain, AGI +$agiGain, HP +$hpGain");

        combat_log("Le {$monster['npcNameFrench']} a été vaincu !");
        echo json_encode(['success' => true, 'victory' => true, 'log' => $log, 'debug' => $debug_logs]);
        exit;
    }

    // 3.5 MONSTER COURAGE CHECK
    // If the monster is wounded but alive, check if they flee (25% threshold)
    if ($monster['currentHitPoints'] > 0 && $monster['currentHitPoints'] <= ($monster['maxHitPoints'] * 0.25)) {
        if (rand(1, 100) <= 50) {
            $exitStmt = $pdo->prepare("SELECT northTarget, southTarget, eastTarget, westTarget, upTarget, downTarget, inTarget, outTarget FROM Locations WHERE nodeId = ?");
            $exitStmt->execute([$monster['currentLocationId']]);
            $exits = $exitStmt->fetch(PDO::FETCH_ASSOC);
            $validExits = array_filter($exits, fn($v) => $v > 0);

            if (!empty($validExits)) {
                $fleeNode = $validExits[array_rand($validExits)];
                // Fix: Persist currentHitPoints along with the new location
                $pdo->prepare("UPDATE Character_NPC_State SET currentLocationId = ?, currentHitPoints = ? WHERE characterId = ? AND npcId = ?")->execute([$fleeNode, $monster['currentHitPoints'], $charId, $targetNpcId]);
                
                combat_log("Le {$monster['npcNameFrench']} a perdu courage et s'est enfui !");
                echo json_encode(['success' => true, 'victory' => true, 'log' => $log, 'debug' => $debug_logs]);
                exit;
            }
        }
    }

    // Update monster health
    $pdo->prepare("UPDATE Character_NPC_State SET currentHitPoints = ? WHERE characterId = ? AND npcId = ?")->execute([$monster['currentHitPoints'], $charId, $targetNpcId]);

    // 5. MONSTER RETALIATION (Targeting logic)
    // Determine who dealt the most damage this round
    arsort($threatMap);
    debug_log("Threat Analysis (Sorted): " . json_encode($threatMap));
    $primaryThreat = key($threatMap);

    $mDamage = rand(1, 6) + floor($monster['strength'] / 2);
    
    if ($primaryThreat === 'player') {
        $pdo->prepare("UPDATE Characters SET hitPoints = hitPoints - ? WHERE id = ?")->execute([$mDamage, $charId]);
        combat_log("Le {$monster['npcNameFrench']} contre-attaque ! Vous perdez {$mDamage} HP.");
    } else {
        $threatId = (int)str_replace('ally_', '', $primaryThreat);
        
        // Find targeted ally object to check courage
        $targetedAlly = null;
        foreach ($allies as $a) { if ($a['npcId'] == $threatId) { $targetedAlly = $a; break; } }
        
        $newAllyHp = $targetedAlly['currentHitPoints'] - $mDamage;
        $pdo->prepare("UPDATE Character_NPC_State SET currentHitPoints = ? WHERE characterId = ? AND npcId = ?")->execute([$newAllyHp, $charId, $threatId]);
        
        $allyName = $targetedAlly['npcNameFrench'] ?? "votre allié";
        combat_log("Le {$monster['npcNameFrench']} s'en prend à {$allyName} et lui inflige {$mDamage} dégâts.");

        // 5.5 ALLY COURAGE CHECK
        if ($newAllyHp > 0 && $newAllyHp <= ($targetedAlly['maxHitPoints'] * 0.25)) {
            if (rand(1, 100) <= 50) {
                $exitStmt = $pdo->prepare("SELECT northTarget, southTarget, eastTarget, westTarget, upTarget, downTarget, inTarget, outTarget FROM Locations WHERE nodeId = ?");
                $exitStmt->execute([$targetedAlly['currentLocationId']]);
                $exits = $exitStmt->fetch(PDO::FETCH_ASSOC);
                $validExits = array_filter($exits, fn($v) => $v > 0);

                if (!empty($validExits)) {
                    $fleeNode = $validExits[array_rand($validExits)];
                    // Fix: Persist currentHitPoints along with the new location
                    $pdo->prepare("UPDATE Character_NPC_State SET currentLocationId = ?, currentHitPoints = ? WHERE characterId = ? AND npcId = ?")->execute([$fleeNode, $newAllyHp, $charId, $threatId]);
                    combat_log("Touché grièvement, {$allyName} a pris la fuite vers une autre pièce !");
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'victory' => false,
        'log' => $log,
        'monsterHp' => $monster['currentHitPoints'],
        'debug' => $debug_logs
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'debug' => $debug_logs]);
}