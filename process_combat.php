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

/**
 * Determines if a hit is critical using a Sigmoid Curve (Soft Cap Method).
 * Compares Attacker Agility vs Target Agility.
 * @param int $attackerAgi
 * @param int $targetAgi
 * @return bool
 */
function checkCriticalHit($attackerAgi, $targetAgi) {
    $diff = $attackerAgi - $targetAgi;
    // Sigmoid Formula: MaxChance / (1 + e^(-k * (diff)))
    // MaxChance = 25%, k (steepness) = 0.15
    $k = 0.15;
    $maxChance = 25;
    $probability = $maxChance / (1 + exp(-$k * $diff));
    
    $roll = rand(1, 1000) / 10; // Precision roll 0.1 to 100.0
    return ($roll <= $probability);
}

/**
 * Determines if an attack is dodged using a Sigmoid Curve.
 * @param int $attackerAgi
 * @param int $defenderAgi
 * @return bool
 */
function checkAvoidance($attackerAgi, $defenderAgi) {
    $diff = $defenderAgi - $attackerAgi;
    // Max Avoidance = 20%, baseline at equal Agi = 10%
    $k = 0.15;
    $maxAvoidance = 20;
    $probability = $maxAvoidance / (1 + exp(-$k * $diff));

    debug_log("Avoidance Calc: AttackerAgi=$attackerAgi, DefenderAgi=$defenderAgi, Diff=$diff, Probability=" . round($probability, 2) . "%");
    $roll = rand(1, 1000) / 10;
    return ($roll <= $probability);
}

/**
 * Fetches all combat modifiers (Armor, STR, AGI, Dice) from an actor's equipped gear.
 */
function fetchActorGear($pdo, $charId, $ownerType, $ownerId) {
    $stmt = $pdo->prepare("
        SELECT l.extraData, l.itemType 
        FROM ItemInstances i 
        JOIN ItemLibrary l ON i.itemId = l.itemId 
        WHERE i.characterId = ? AND i.ownerType = ? AND i.ownerId = ? 
        AND l.itemType IN ('Weapon', 'Armor') AND i.isEquipped = 1
    ");
    $stmt->execute([$charId, $ownerType, $ownerId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $res = ['armor' => 0, 'str' => 0, 'agi' => 0, 'dice' => '1d4'];
    foreach ($rows as $row) {
        $d = json_decode($row['extraData'], true) ?: [];
        $res['str'] += (int)($d['strBonus'] ?? 0);
        $res['agi'] += (int)($d['agiBonus'] ?? 0);
        if ($row['itemType'] === 'Armor') $res['armor'] += (int)($d['armorValue'] ?? 0);
        if ($row['itemType'] === 'Weapon' && isset($d['dice'])) $res['dice'] = $d['dice'];
    }
    return $res;
}

/**
 * Adjudicates the flee protocol (Courage Check).
 * Returns true if the actor fled.
 */
function handleFleeCheck($pdo, $charId, $actor, $npcId) {
    if ($actor['currentHitPoints'] <= 0 || $actor['currentHitPoints'] > ($actor['maxHitPoints'] * 0.25)) return false;
    if (rand(1, 100) > 50) return false;

    $exitStmt = $pdo->prepare("SELECT northTarget, southTarget, eastTarget, westTarget, upTarget, downTarget, inTarget, outTarget FROM Locations WHERE nodeId = ?");
    $exitStmt->execute([$actor['currentLocationId']]);
    $exits = $exitStmt->fetch(PDO::FETCH_ASSOC);
    $validExits = array_filter($exits ?: [], fn($v) => $v > 0);

    if ($validExits && count($validExits) > 0) {
        $fleeNode = $validExits[array_rand($validExits)];
        $pdo->prepare("UPDATE Character_NPC_State SET currentLocationId = ?, currentHitPoints = ? WHERE characterId = ? AND npcId = ?")
            ->execute([$fleeNode, $actor['currentHitPoints'], $charId, $npcId]);
        return true;
    }
    return false;
}

/**
 * Transfers lootable items from an NPC instance to the room floor.
 */
function handleLootDrop($pdo, $charId, $npcId, $locationId) {
    global $debug_logs;
    $lootStmt = $pdo->prepare("SELECT i.instanceId, l.extraData FROM ItemInstances i JOIN ItemLibrary l ON i.itemId = l.itemId WHERE i.characterId = ? AND i.ownerType = 'NPC' AND i.ownerId = ?");
    $lootStmt->execute([$charId, $npcId]);
    $items = $lootStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $extra = json_decode($item['extraData'], true);
        if (!isset($extra['lootable']) || $extra['lootable'] !== false) {
            $pdo->prepare("UPDATE ItemInstances SET ownerType = 'Room', ownerId = ?, isEquipped = 0 WHERE instanceId = ?")->execute([$locationId, $item['instanceId']]);
            debug_log("Loot Dropped: Instance {$item['instanceId']} moved to Node $locationId.");
        }
    }
}

/**
 * Calculates final damage after Linguistic Tier and Armor modifiers.
 */
function calculateNetDamage($totalStr, $dice, $tier, $isCrit, $targetArmor) {
    global $debug_logs;
    $diceResult = rollDice($dice, ($tier === 'Parfait'));
    
    $rawDamage = $diceResult + (int)$totalStr;
    debug_log("Math: Roll({$diceResult}) + Total Strength({$totalStr}) = Raw({$rawDamage})");

    if ($tier === 'Bien') $rawDamage = floor($rawDamage / 2);
    if ($tier === 'Pas compris') return 0;

    $final = max(0, $rawDamage - ($isCrit ? 0 : $targetArmor));
    debug_log("Final: Raw({$rawDamage}) - Armor(" . ($isCrit ? "BYPASS" : $targetArmor) . ") = Net({$final})");
    return $final;
}

try {
    // 1. FETCH ACTORS
    $pStmt = $pdo->prepare("SELECT * FROM Characters WHERE id = ?");
    $pStmt->execute([$charId]);
    $player = $pStmt->fetch(PDO::FETCH_ASSOC);
    if (!$player) throw new Exception("Character synchronization failed.");

    $mStmt = $pdo->prepare("SELECT s.*, n.npcNameFrench, n.strength, n.agility, n.maxHitPoints FROM Character_NPC_State s JOIN Npcs n ON s.npcId = n.npcId WHERE s.characterId = ? AND s.npcId = ?");
    $mStmt->execute([$charId, $targetNpcId]);
    $monster = $mStmt->fetch(PDO::FETCH_ASSOC);

    // Aggregated Player Stats
    $pGear = fetchActorGear($pdo, $charId, 'Player', $charId);
    $pStr = (int)$player['strength'] + $pGear['str'];
    $pAgi = (int)$player['agility'] + $pGear['agi'];

    // Aggregated Monster Stats
    $mGear = fetchActorGear($pdo, $charId, 'NPC', $targetNpcId);
    $mStr = (int)$monster['strength'] + $mGear['str'];
    $mAgi = (int)$monster['agility'] + $mGear['agi'];
    $mArmor = $mGear['armor'];

    // 1.5 VALIDATION
    if (!$monster || (int)$monster['isDead'] === 1) {
        combat_log("Cette cible est déjà sans vie.");
        echo json_encode(['success' => true, 'victory' => true, 'log' => $log, 'debug' => $debug_logs, 'monsterHp' => 0]);
        exit;
    }

    $aStmt = $pdo->prepare("SELECT s.*, n.npcNameFrench, n.strength, n.agility, n.maxHitPoints FROM Character_NPC_State s JOIN Npcs n ON s.npcId = n.npcId WHERE s.characterId = ? AND s.isFollowing = 1 AND s.isDead = 0");
    $aStmt->execute([$charId]);
    $allies = $aStmt->fetchAll(PDO::FETCH_ASSOC);

    $threatMap = [];

    // 2. PLAYER TURN
    $isCrit = checkCriticalHit($pAgi, $mAgi);
    $isDodged = checkAvoidance($pAgi, $mAgi);

    if ($isDodged) {
        $netDamage = 0;
        combat_log("Le {$monster['npcNameFrench']} a esquivé votre attaque !");
        debug_log("Player Turn: Dodged by Monster.");
    } else {
        debug_log("Player Attack Init: Tier({$tier}) | Crit(" . ($isCrit ? "YES" : "NO") . ") | Str: $pStr");
        $netDamage = calculateNetDamage($pStr, $pGear['dice'], $tier, $isCrit, $mArmor);
        combat_log(($isCrit ? "COUP CRITIQUE ! " : "") . "Vous frappez le {$monster['npcNameFrench']} pour {$netDamage} dégâts ! ($tier)");
    }
    $threatMap['player'] = $netDamage;
    $monster['currentHitPoints'] -= $netDamage;

    // 3. ALLY TURNS
    foreach ($allies as $ally) {
        if ($monster['currentHitPoints'] <= 0) break;
        $aGear = fetchActorGear($pdo, $charId, 'NPC', $ally['npcId']);
        $aStr = (int)$ally['strength'] + $aGear['str'];
        $aAgi = (int)$ally['agility'] + $aGear['agi'];

        $isCrit = checkCriticalHit($aAgi, $mAgi);
        $isDodged = checkAvoidance($aAgi, $mAgi);

        if ($isDodged) {
            $netAllyDmg = 0;
            combat_log("{$ally['npcNameFrench']} a attaqué, mais le {$monster['npcNameFrench']} a esquivé !");
            debug_log("Ally {$ally['npcNameFrench']} Turn: Dodged.");
        } else {
            debug_log("Ally {$ally['npcNameFrench']} Attack Init: Crit(" . ($isCrit ? "YES" : "NO") . ") | Str: $aStr");
            $netAllyDmg = calculateNetDamage($aStr, $aGear['dice'], 'Bien', $isCrit, $mArmor);
            combat_log(($isCrit ? "COUP CRITIQUE ! " : "") . "{$ally['npcNameFrench']} attaque : {$netAllyDmg} dégâts !");
        }
        $threatMap['ally_'.$ally['npcId']] = $netAllyDmg;
        $monster['currentHitPoints'] -= $netAllyDmg;
    }

    // 4. CHECK MONSTER DEATH & REWARDS
    if ($monster['currentHitPoints'] <= 0) {
        $monster['currentHitPoints'] = 0;
        $pdo->prepare("UPDATE Character_NPC_State SET currentHitPoints = 0, isDead = 1 WHERE characterId = ? AND npcId = ?")->execute([$charId, $targetNpcId]);
        
        handleLootDrop($pdo, $charId, $targetNpcId, $monster['currentLocationId']);

        // Handle Stats
        $strG = (int)ceil($monster['strength'] * 0.1);
        $agiG = (int)ceil($monster['agility'] * 0.1);
        $hpG  = (int)ceil($monster['maxHitPoints'] * 0.1);
        $pdo->prepare("UPDATE Characters SET strength=strength+?, agility=agility+?, hitPoints=hitPoints+?, maxHitPoints=maxHitPoints+? WHERE id=?")->execute([$strG, $agiG, $hpG, $hpG, $charId]);
        
        combat_log("Le {$monster['npcNameFrench']} a été vaincu ! Victoire !");
        echo json_encode(['success' => true, 'victory' => true, 'log' => $log, 'debug' => $debug_logs]);
        exit;
    }

    // 5. MONSTER ACTION (Flee or Retaliate)
    if (handleFleeCheck($pdo, $charId, $monster, $targetNpcId)) {
        combat_log("Le {$monster['npcNameFrench']} a perdu courage et s'est enfui !");
        echo json_encode(['success' => true, 'victory' => true, 'log' => $log, 'debug' => $debug_logs]);
        exit;
    }

    $pdo->prepare("UPDATE Character_NPC_State SET currentHitPoints = ? WHERE characterId = ? AND npcId = ?")->execute([$monster['currentHitPoints'], $charId, $targetNpcId]);

    // Targeting
    arsort($threatMap);
    debug_log("Current Round Threat Rankings: " . json_encode($threatMap));
    $primaryThreat = key($threatMap) ?: 'player';

    if ($primaryThreat === 'player') {
        $isCrit = checkCriticalHit($mAgi, $pAgi);
        $isDodged = checkAvoidance($mAgi, $pAgi);
        
        debug_log("Monster Retaliation vs Player: Crit(" . ($isCrit ? "YES" : "NO") . ") | Dodge(" . ($isDodged ? "YES" : "NO") . ") | Str: $mStr");
        $dmg = $isDodged ? 0 : calculateNetDamage($mStr, $mGear['dice'], 'Bien', $isCrit, $pGear['armor']);
        
        $player['hitPoints'] -= $dmg;
        $pdo->prepare("UPDATE Characters SET hitPoints = ? WHERE id = ?")->execute([$player['hitPoints'], $charId]);
        
        if ($isDodged) combat_log("Vous avez esquivé l'attaque du {$monster['npcNameFrench']} !");
        else combat_log(($isCrit ? "COUP CRITIQUE ! " : "") . "Le {$monster['npcNameFrench']} contre-attaque ! Vous perdez {$dmg} HP.");

        if ($player['hitPoints'] <= 0) {
            echo json_encode(['success' => true, 'dead' => true, 'goldLost' => $player['gold'] * 0.1, 'log' => $log, 'debug' => $debug_logs]);
            exit;
        }
    } else {
        $tId = (int)str_replace('ally_', '', $primaryThreat);
        $tAlly = null;
        foreach ($allies as $a) { if ($a['npcId'] == $tId) { $tAlly = $a; break; } }
        if ($tAlly) {
            $taGear = fetchActorGear($pdo, $charId, 'NPC', $tId);
            $taAgi = (int)$tAlly['agility'] + $taGear['agi'];
            
            $isCrit = checkCriticalHit($mAgi, $taAgi);
            $isDodged = checkAvoidance($mAgi, $taAgi);
            
            debug_log("Monster Retaliation vs Ally {$tAlly['npcNameFrench']}: Crit(" . ($isCrit ? "YES" : "NO") . ") | Dodge(" . ($isDodged ? "YES" : "NO") . ")");
            $dmg = $isDodged ? 0 : calculateNetDamage($mStr, $mGear['dice'], 'Bien', $isCrit, $taGear['armor']);
            
            $newAllyHp = max(0, $tAlly['currentHitPoints'] - $dmg);
            $isAllyDead = ($newAllyHp <= 0) ? 1 : 0;
            
            $pdo->prepare("UPDATE Character_NPC_State SET currentHitPoints = ?, isDead = ? WHERE characterId = ? AND npcId = ?")->execute([$newAllyHp, $isAllyDead, $charId, $tId]);
            $tAlly['currentHitPoints'] = $newAllyHp; // Sync memory for log and flee check
            
            if ($isDodged) combat_log("{$tAlly['npcNameFrench']} a esquivé l'attaque !");
            else combat_log(($isCrit ? "COUP CRITIQUE ! " : "") . "Le {$monster['npcNameFrench']} frappe {$tAlly['npcNameFrench']} : {$dmg} dégâts.");
            
            if ($isAllyDead) {
                combat_log("{$tAlly['npcNameFrench']} est tombé au combat !");
                handleLootDrop($pdo, $charId, $tId, $tAlly['currentLocationId']);
            } elseif (handleFleeCheck($pdo, $charId, $tAlly, $tId)) {
                combat_log("Touché grièvement, {$tAlly['npcNameFrench']} a pris la fuite !");
            }
        }
    }

    echo json_encode(['success' => true, 'victory' => false, 'log' => $log, 'monsterHp' => $monster['currentHitPoints'], 'debug' => $debug_logs]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'debug' => $debug_logs]);
}