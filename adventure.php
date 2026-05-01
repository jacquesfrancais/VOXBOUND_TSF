<?php
session_start();
require_once __DIR__ . '/db_config.php';

// Security Check: Redirect if not authenticated or character not selected
if (!isset($_SESSION['user_id']) || !isset($_SESSION['character_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch Character Data
$stmt = $pdo->prepare("SELECT * FROM Characters WHERE id = :charId");
$stmt->execute(['charId' => $_SESSION['character_id']]);
$character = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$character) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VOXBOUND – Adventure</title>
    <link rel="stylesheet" href="adventure-base.css">
</head>
<body>

    <!-- TOP NAVIGATION BAR -->
    <nav style="display:flex; justify-content: space-between; padding: 20px 40px; background: #161b22; border-bottom: 1px solid #333;">
        <div style="color: var(--primary-cyan); font-weight: bold; letter-spacing: 1px;">VOXBOUND: THE SPOKEN FRONTIER</div>
        <div style="display:flex; gap: 30px;">
            <a href="index.php" style="color:var(--primary-cyan); text-decoration:none;">HOME</a>
            <a href="adventure.php" style="color:white; text-decoration:none;">PLAY GAME</a>
            <a href="admin.php" style="color:white; text-decoration:none;">ADMIN DASHBOARD</a>
        </div>
    </nav>

    <!-- MAIN GAME AREA -->
    <main class="layout-grid">
        
        <!-- LEFT: STORY & TEXT -->
        <section class="console-box">
            <div class="title-label">Location ID: <?= (int)$character['currentLocationID'] ?></div>
            <p style="color: var(--accent-gold); line-height: 1.6;">
                &gt; Initializing local environment data...<br>
                The path winds through the dark woods. The terminal awaits your command.
            </p>
        </section>

        <!-- RIGHT: STATS & CONTROLS -->
        <aside style="display:flex; flex-direction:column; gap:20px;">
            <div class="console-box" style="background: var(--primary-cyan); color: black;">
                <strong style="text-transform: uppercase;"><?= htmlspecialchars($character['characterName']) ?></strong>
                <div style="font-size: 0.85rem; margin-top: 5px;">
                    HP: <?= (int)$character['hitPoints'] ?> / <?= (int)$character['maxHitPoints'] ?><br>
                    STR: <?= (int)$character['strength'] ?> | AGI: <?= (int)$character['agility'] ?><br>
                    GOLD: <?= number_format($character['gold'], 2) ?>
                </div>
            </div>

            <div class="console-box" style="text-align:center;">
                <div class="title-label">Movement</div>
                
                <!-- PRIMARY COMPASS: N, S, E, W -->
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 5px; margin-bottom: 20px; align-items: center;">
                    <div></div><button class="btn-neon" style="padding: 5px; font-size: 0.75rem;">NORD</button><div></div>
                    <button class="btn-neon" style="padding: 5px; font-size: 0.75rem;">OUEST</button>
                    <div style="color:var(--primary-cyan); font-size: 1.2rem;">◈</div>
                    <button class="btn-neon" style="padding: 5px; font-size: 0.75rem;">EST</button>
                    <div></div><button class="btn-neon" style="padding: 5px; font-size: 0.75rem;">SUD</button><div></div>
                </div>

                <!-- UTILITY AXIS: HAUT/BAS & ENTRER/SORTIR -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div style="display:flex; flex-direction:column; gap:5px; border: 1px solid #222; padding: 5px;">
                        <button class="btn-neon" style="font-size: 0.7rem; padding: 5px;">HAUT</button>
                        <div style="color: var(--accent-gold); font-size: 0.6rem;">↕</div>
                        <button class="btn-neon" style="font-size: 0.7rem; padding: 5px;">BAS</button>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:5px; border: 1px solid #222; padding: 5px;">
                        <button class="btn-neon" style="font-size: 0.7rem; padding: 5px;">ENTRER</button>
                        <div style="color: var(--accent-gold); font-size: 0.6rem;">↔</div>
                        <button class="btn-neon" style="font-size: 0.7rem; padding: 5px;">SORTIR</button>
                    </div>
                </div>
            </div>
        </aside>

    </main>
</body>
</html>
