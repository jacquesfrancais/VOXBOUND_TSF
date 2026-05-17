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

    <?php include __DIR__ . '/partials/navbar.php'; ?>

    <!-- MAIN GAME AREA -->
    <main class="layout-grid">
        
        <?php include __DIR__ . '/partials/ui_console.php'; ?>

        <!-- RIGHT: STATS & CONTROLS -->
        <aside style="display:flex; flex-direction:column; gap:20px;">
            <?php 
            include __DIR__ . '/partials/ui_command_manual.php';
            include __DIR__ . '/partials/ui_stats.php';
            include __DIR__ . '/partials/ui_inventory.php';
            include __DIR__ . '/partials/ui_party.php';
            include __DIR__ . '/partials/ui_movement.php';
            include __DIR__ . '/partials/ui_map.php';
            ?>
        </aside>

    </main>

    <!-- LOAD ENGINE LOGIC -->
    <script src="speech.js"></script>
    <script src="navigation.js"></script>
    <script src="map.js"></script>
    <script src="inventory.js"></script>
    <script src="dialogue.js"></script>
    <script src="ui.js"></script>
    <script src="engine.js"></script>
</body>
</html>
