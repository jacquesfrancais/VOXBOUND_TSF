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

    // Target Monster State
    $mStmt = $pdo->prepare("SELECT s.*, n.npcNameFrench, n.strength, n.agility FROM Character_NPC_State s JOIN Npcs n ON s.npcId = n.npcId WHERE s.characterId = ? AND s.npcId = ?");
    $mStmt->execute([$charId, $targetNpcId]);
    $monster = $mStmt->fetch(PDO::FETCH_ASSOC);

    // Allies State
    $aStmt = $pdo->prepare("SELECT s.*, n.npcNameFrench, n.strength FROM Character_NPC_State s JOIN Npcs n ON s.npcId = n.npcId WHERE s.characterId = ? AND s.isFollowing = 1 AND s.isDead = 0");
    $aStmt->execute([$charId]);
    $allies = $aStmt->fetchAll(PDO::FETCH_ASSOC);

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
        
        $baseDamage = rollDice($weapon['dice'], ($tier === 'Parfait'));
        $totalDamage = $baseDamage + (int)$player['strength'] + (int)($weapon['strBonus'] ?? 0);
        if ($tier === 'Bien') $totalDamage = floor($totalDamage / 2);

        $threatMap['player'] = $totalDamage;
        $monster['currentHitPoints'] -= $totalDamage;
        combat_log("Vous frappez le {$monster['npcNameFrench']} pour {$totalDamage} dégâts ! ({$tier})");
    }

    // 3. ALLY TURNS (Automatic support)
    foreach ($allies as $ally) {
        if ($monster['currentHitPoints'] <= 0) break;
        $allyDamage = rand(1, 4) + floor($ally['strength'] / 2);
        $threatMap['ally_'.$ally['npcId']] = $allyDamage;
        $monster['currentHitPoints'] -= $allyDamage;
        combat_log("{$ally['npcNameFrench']} attaque et inflige {$allyDamage} dégâts !");
    }

    // 4. CHECK MONSTER DEATH
    if ($monster['currentHitPoints'] <= 0) {
        $monster['currentHitPoints'] = 0;
        $pdo->prepare("UPDATE Character_NPC_State SET currentHitPoints = 0, isDead = 1 WHERE characterId = ? AND npcId = ?")->execute([$charId, $targetNpcId]);
        combat_log("Le {$monster['npcNameFrench']} a été vaincu !");
        echo json_encode(['success' => true, 'victory' => true, 'log' => $log]);
        exit;
    }

    // Update monster health for now
    $pdo->prepare("UPDATE Character_NPC_State SET currentHitPoints = ? WHERE characterId = ? AND npcId = ?")->execute([$monster['currentHitPoints'], $charId, $targetNpcId]);

    // 5. MONSTER RETALIATION (Targeting logic)
    // Determine who dealt the most damage this round
    arsort($threatMap);
    $primaryThreat = key($threatMap);

    $mDamage = rand(1, 6) + floor($monster['strength'] / 2);
    
    if ($primaryThreat === 'player') {
        $pdo->prepare("UPDATE Characters SET hitPoints = hitPoints - ? WHERE id = ?")->execute([$mDamage, $charId]);
        combat_log("Le {$monster['npcNameFrench']} contre-attaque ! Vous perdez {$mDamage} HP.");
    } else {
        $threatId = (int)str_replace('ally_', '', $primaryThreat);
        $pdo->prepare("UPDATE Character_NPC_State SET currentHitPoints = currentHitPoints - ? WHERE characterId = ? AND npcId = ?")->execute([$mDamage, $charId, $threatId]);
        
        // Find ally name for log
        $targetAlly = array_filter($allies, fn($a) => $a['npcId'] == $threatId);
        $allyName = reset($targetAlly)['npcNameFrench'] ?? "votre allié";
        combat_log("Le {$monster['npcNameFrench']} s'en prend à {$allyName} et lui inflige {$mDamage} dégâts.");
    }

    echo json_encode([
        'success' => true,
        'victory' => false,
        'log' => $log,
        'monsterHp' => $monster['currentHitPoints']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}