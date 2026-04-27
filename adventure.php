<!-- adventure.php - Main game interface for the Lingua Quest French Learning RPG -->
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="adventure-base.css">
</head>
<body>

    <!-- TOP NAVIGATION BAR -->
    <nav style="display:flex; justify-content: space-between; padding: 20px 40px; background: #161b22; border-bottom: 1px solid #333;">
        <div style="color: var(--primary-cyan); font-weight: bold;">LINGUA QUEST - FRENCH LEARNING RPG</div>
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
            <div class="title-label">Location: Sentier</div>
            <p style="color: var(--accent-gold);">The path winds through the dark woods...</p>
        </section>

        <!-- RIGHT: STATS & CONTROLS -->
        <aside style="display:flex; flex-direction:column; gap:20px;">
            <div class="console-box" style="background: var(--primary-cyan); color: black;">
                <strong>PLAYER STATS</strong>
                <p>HP: 85/100</p>
            </div>

            <div class="console-box" style="text-align:center;">
                <div class="title-label">Movement</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <button class="btn-neon">NORD</button>
                    <button class="btn-neon">SUD</button>
                </div>
            </div>
        </aside>

    </main>
</body>
</html>
