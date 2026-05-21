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

    // Fetch Monster Armor
    $mArmorStmt = $pdo->prepare("SELECT l.extraData FROM ItemInstances i JOIN ItemLibrary l ON i.itemId = l.itemId WHERE i.characterId = ? AND i.ownerType = 'NPC' AND i.ownerId = ? AND l.itemType = 'Armor' LIMIT 1");
    $mArmorStmt->execute([$charId, $targetNpcId]);
    $mArmorJson = $mArmorStmt->fetchColumn();
    $monsterArmor = $mArmorJson ? (int)(json_decode($mArmorJson, true)['armorValue'] ?? 0) : 0;
    debug_log("Monster Armor: $monsterArmor");

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

    // Fetch Player Armor
    $pArmorStmt = $pdo->prepare("SELECT l.extraData FROM ItemInstances i JOIN ItemLibrary l ON i.itemId = l.itemId WHERE i.characterId = ? AND i.ownerType = 'Player' AND l.itemType = 'Armor' LIMIT 1");
    $pArmorStmt->execute([$charId]);
    $pArmorJson = $pArmorStmt->fetchColumn();
    $playerArmor = $pArmorJson ? (int)(json_decode($pArmorJson, true)['armorValue'] ?? 0) : 0;
    debug_log("Player Armor: $playerArmor");

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

        // 2.5 AVOIDANCE CHECK (Monster dodging Player)
        $isDodged = checkAvoidance((int)$player['agility'], (int)$monster['agility']);

        if ($isDodged) {
            $netDamage = 0;
            debug_log("Avoidance Triggered: Monster dodged Player attack.");
            combat_log("Le {$monster['npcNameFrench']} a esquivé votre attaque !");
        } else {
            // Critical Hit Check (Player vs Monster)
            $isCrit = checkCriticalHit((int)$player['agility'], (int)$monster['agility']);
            $effectiveArmor = $isCrit ? 0 : $monsterArmor;
            
            if ($isCrit) debug_log("CRITICAL HIT! Sigmoid Probability triggered. Armor bypassed.");

            // Apply Armor Absorption
            $netDamage = max(0, $totalDamage - $effectiveArmor);
            debug_log("Damage vs Monster: $totalDamage - $effectiveArmor armor = $netDamage final");
        }

        $threatMap['player'] = $netDamage;
        $monster['currentHitPoints'] -= $netDamage;
        $msg = $isCrit ? "COUP CRITIQUE ! Vous frappez le " : "Vous frappez le ";
        combat_log($msg . "{$monster['npcNameFrench']} pour {$netDamage} dégâts ! ({$tier})");
    }

    // 3. ALLY TURNS (Automatic support)
    foreach ($allies as $ally) {
        if ($monster['currentHitPoints'] <= 0) break;
        $allyDamage = rand(1, 4) + floor($ally['strength'] / 2);
        
        // AVOIDANCE CHECK (Monster dodging Ally)
        $isDodged = checkAvoidance((int)$ally['agility'], (int)$monster['agility']);

        if ($isDodged) {
            $netAllyDmg = 0;
            debug_log("Avoidance Triggered: Monster dodged Ally {$ally['npcNameFrench']}.");
        } else {
            // Critical Hit Check (Ally vs Monster)
            $isCrit = checkCriticalHit((int)$ally['agility'], (int)$monster['agility']);
            $effectiveArmor = $isCrit ? 0 : $monsterArmor;
            $netAllyDmg = max(0, $allyDamage - $effectiveArmor);
            if ($isCrit) debug_log("Ally {$ally['npcNameFrench']} LANDED A CRIT!");
        }

        $threatMap['ally_'.$ally['npcId']] = $netAllyDmg;
        $monster['currentHitPoints'] -= $netAllyDmg;
        
        if ($isDodged) {
            combat_log("{$ally['npcNameFrench']} a attaqué, mais le {$monster['npcNameFrench']} a esquivé !");
        } else {
            $msg = $isCrit ? "COUP CRITIQUE ! {$ally['npcNameFrench']} attaque : " : "{$ally['npcNameFrench']} attaque : ";
            combat_log($msg . "{$netAllyDmg} dégâts !");
        }
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
        // AVOIDANCE CHECK (Player dodging Monster)
        $isDodged = checkAvoidance((int)$monster['agility'], (int)$player['agility']);

        if ($isDodged) {
            $netPlayerDmg = 0;
            debug_log("Avoidance Triggered: Player dodged Monster attack.");
            combat_log("Vous avez esquivé l'attaque du {$monster['npcNameFrench']} !");
        } else {
            // Critical Hit Check (Monster vs Player)
            $isCrit = checkCriticalHit((int)$monster['agility'], (int)$player['agility']);
            $effectiveArmor = $isCrit ? 0 : $playerArmor;
            $netPlayerDmg = max(0, $mDamage - $effectiveArmor);
            debug_log("Monster vs Player: $mDamage - $effectiveArmor armor = $netPlayerDmg final");
        }
        
        $pdo->prepare("UPDATE Characters SET hitPoints = hitPoints - ? WHERE id = ?")->execute([$netPlayerDmg, $charId]);
        if (!$isDodged) {
            $msg = $isCrit ? "COUP CRITIQUE ! Le {$monster['npcNameFrench']} " : "Le {$monster['npcNameFrench']} ";
            combat_log($msg . "contre-attaque ! Vous perdez {$netPlayerDmg} HP.");
        }
    } else {
        $threatId = (int)str_replace('ally_', '', $primaryThreat);
        
        // Find targeted ally object to check courage
        $targetedAlly = null;
        foreach ($allies as $a) { if ($a['npcId'] == $threatId) { $targetedAlly = $a; break; } }
        
        // Fetch Ally Armor
        $aArmorStmt = $pdo->prepare("SELECT l.extraData FROM ItemInstances i JOIN ItemLibrary l ON i.itemId = l.itemId WHERE i.characterId = ? AND i.ownerType = 'NPC' AND i.ownerId = ? AND l.itemType = 'Armor' LIMIT 1");
        $aArmorStmt->execute([$charId, $threatId]);
        $aArmorJson = $aArmorStmt->fetchColumn();
        $allyArmor = $aArmorJson ? (int)(json_decode($aArmorJson, true)['armorValue'] ?? 0) : 0;

        // AVOIDANCE CHECK (Ally dodging Monster)
        $isDodged = checkAvoidance((int)$monster['agility'], (int)$targetedAlly['agility']);
        $allyName = $targetedAlly['npcNameFrench'] ?? "votre allié";

        if ($isDodged) {
            $netAllyDmg = 0;
            debug_log("Avoidance Triggered: Ally $allyName dodged Monster attack.");
            combat_log("$allyName a esquivé l'attaque du {$monster['npcNameFrench']} !");
        } else {
            // Critical Hit Check (Monster vs Ally)
            $isCrit = checkCriticalHit((int)$monster['agility'], (int)$targetedAlly['agility']);
            $effectiveArmor = $isCrit ? 0 : $allyArmor;
            $netAllyDmg = max(0, $mDamage - $effectiveArmor);
            debug_log("Monster vs Ally: $mDamage - $effectiveArmor armor = $netAllyDmg final");
        }

        $newAllyHp = $targetedAlly['currentHitPoints'] - $netAllyDmg;
        $pdo->prepare("UPDATE Character_NPC_State SET currentHitPoints = ? WHERE characterId = ? AND npcId = ?")->execute([$newAllyHp, $charId, $threatId]);
        
        if (!$isDodged) {
            $msg = $isCrit ? "COUP CRITIQUE ! Le {$monster['npcNameFrench']} " : "Le {$monster['npcNameFrench']} ";
            combat_log($msg . "s'en prend à {$allyName} et lui inflige {$netAllyDmg} dégâts.");
        }

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