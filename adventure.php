<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Admin check for restricted navbar links
$adminCheck = $pdo->prepare("SELECT isAdmin FROM Users WHERE userId = :id");
$adminCheck->execute(['id' => $_SESSION['user_id']]);
$isAdmin = (bool)$adminCheck->fetchColumn();

// Fetch Initial Location Title for the display
$locStmt = $pdo->prepare("SELECT title FROM Locations WHERE nodeId = :nodeId");
$locStmt->execute(['nodeId' => $character['currentLocationID']]);
$locTitle = $locStmt->fetchColumn() ?: "Unknown Location";
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
            <a href="adventure.php" style="color:white; text-decoration:none;">ADVENTURE</a>
            <?php if ($isAdmin): ?>
                <a href="admin.php" style="color:white; text-decoration:none;">ADMIN</a>
                <a href="editor.php" style="color:white; text-decoration:none;">EDITOR</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- MAIN GAME AREA -->
    <main class="layout-grid">
        
        <!-- LEFT: STORY & TEXT -->
        <section class="console-box" id="game-console">
            <div class="title-label" id="location-label">
                Location: <span id="location-id-display"><?= htmlspecialchars($locTitle) ?></span>
                <button class="btn-outline" onclick="replayRoomDescription()" style="font-size: 0.6rem; margin-left: 10px; padding: 2px 5px; vertical-align: middle;">READ ALOUD</button>
                <button class="btn-outline" onclick="toggleLanguage()" style="font-size: 0.6rem; margin-left: 5px; padding: 2px 5px; vertical-align: middle;">TRANSLATE</button>
                <button id="btn-voice-command" class="btn-neon" onclick="startVoiceCommand()" style="font-size: 0.6rem; margin-left: 5px; padding: 2px 5px; vertical-align: middle;">ÉNONCER LES COMMANDES</button>
            </div>
            
            <!-- PERSISTENT FEEDBACK AREA -->
            <div id="command-feedback" style="min-height: 25px; font-size: 0.8rem; margin-bottom: 15px; font-family: var(--font-mono); border-bottom: 1px solid #1a1a1a; padding-bottom: 5px;"></div>

            <div id="room-description" style="color: var(--accent-gold); line-height: 1.6; margin-bottom: 20px;">
                &gt; INITIALIZING ENVIRONMENT...<br>
                The terminal flickers to life. The path winds through the dark woods.
            </div>

            <div id="room-entities" style="border-top: 1px solid #222; padding-top: 15px; font-size: 0.85rem;">
                <div class="title-label" style="font-size: 0.7rem;">Detected Entities:</div>
                <ul id="npc-list" style="list-style: none; padding: 0; color: var(--primary-cyan);">
                    <!-- NPCs will be injected here -->
                </ul>
                <ul id="object-list" style="list-style: none; padding: 0; color: #888;">
                    <!-- Objects will be injected here -->
                </ul>
            </div>
        </section>

        <!-- RIGHT: STATS & CONTROLS -->
        <aside style="display:flex; flex-direction:column; gap:20px;">
            <div class="console-box" style="background: var(--primary-cyan); color: black;">
                <strong id="char-name" style="text-transform: uppercase;"><?= htmlspecialchars($character['characterName']) ?></strong>
                <div style="font-size: 0.85rem; margin-top: 5px;">
                    HP: <span id="stat-hp"><?= (int)$character['hitPoints'] ?> / <?= (int)$character['maxHitPoints'] ?></span><br>
                    STR: <span id="stat-str"><?= (int)$character['strength'] ?></span> | AGI: <span id="stat-agi"><?= (int)$character['agility'] ?></span><br>
                    GOLD: <span id="stat-gold"><?= number_format($character['gold'], 2) ?></span>
                </div>
            </div>

            <!-- PARTY SECTION -->
            <div id="party-section" class="console-box" style="background: rgba(0, 242, 255, 0.1); display: none;">
                <div class="title-label">Active Party</div>
                <ul id="party-list" style="list-style: none; padding: 0; margin: 0; font-size: 0.85rem;"></ul>
            </div>

            <div class="console-box" style="text-align:center;">
                <div class="title-label">Movement</div>
                
                <!-- PRIMARY COMPASS: N, S, E, W -->
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 5px; margin-bottom: 20px; align-items: center;">
                    <div></div><button id="btn-nord" class="btn-neon" onclick="handleMove('nord')" style="padding: 5px; font-size: 0.75rem;">NORD</button><div></div>
                    <button id="btn-ouest" class="btn-neon" onclick="handleMove('ouest')" style="padding: 5px; font-size: 0.75rem;">OUEST</button>
                    <div style="color:var(--primary-cyan); font-size: 1.2rem;">◈</div>
                    <button id="btn-est" class="btn-neon" onclick="handleMove('est')" style="padding: 5px; font-size: 0.75rem;">EST</button>
                    <div></div><button id="btn-sud" class="btn-neon" onclick="handleMove('sud')" style="padding: 5px; font-size: 0.75rem;">SUD</button><div></div>
                </div>

                <!-- UTILITY AXIS: HAUT/BAS & ENTRER/SORTIR -->
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div style="display:flex; flex-direction:column; gap:5px; border: 1px solid #222; padding: 5px;">
                        <button id="btn-remonter" class="btn-neon" onclick="handleMove('remonter')" style="font-size: 0.7rem; padding: 5px;">REMONTER</button>
                        <div style="color: var(--accent-gold); font-size: 0.6rem;">↕</div>
                        <button id="btn-descendre" class="btn-neon" onclick="handleMove('descendre')" style="font-size: 0.7rem; padding: 5px;">DESCENDRE</button>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:5px; border: 1px solid #222; padding: 5px;">
                        <button id="btn-pénétrer" class="btn-neon" onclick="handleMove('pénétrer')" style="font-size: 0.7rem; padding: 5px;">PÉNÉTRER</button>
                        <div style="color: var(--accent-gold); font-size: 0.6rem;">↔</div>
                        <button id="btn-sortir" class="btn-neon" onclick="handleMove('sortir')" style="font-size: 0.7rem; padding: 5px;">SORTIR</button>
                    </div>
                </div>
            </div>

            <div class="console-box">
                <div class="title-label" style="display: flex; justify-content: space-between;">
                    <span>Mini-Map</span>
                    <span style="font-size: 0.6rem; color: #444;">7x7 SENSOR RANGE</span>
                </div>
                <div id="mini-map-grid"></div>
                <div id="z-level-display">Level: 0</div>
            </div>
        </aside>

    </main>

    <!-- LOAD ENGINE LOGIC -->
    <script src="speech.js"></script>
    <script src="ui.js"></script>
    <script src="engine.js"></script>
</body>
</html>
