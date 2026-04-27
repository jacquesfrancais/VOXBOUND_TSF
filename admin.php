<!-- admin.php - Admin dashboard for system diagnostics and management of the Lingua Quest French Learning RPG -->
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Links to the same base styles + the admin extensions -->
    <link rel="stylesheet" href="adventure-base.css"> 
    <link rel="stylesheet" href="admin-base.css">
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

    <div class="admin-view-wrapper">
        
        <!-- CENTER: SYSTEM DIAGNOSTICS -->
        <main class="admin-console">
            <h2 style="color: var(--primary-cyan); margin-top: 0;">SYSTEM DIAGNOSTICS</h2>
            <p>OVERVIEW: Access the core engine truth tables...</p>
            
            <p><span class="highlight-red">1. FIELD AUDIT</span><br>
            Scans CMS collections for data integrity.</p>
            
            <p><span class="highlight-red">2. PATH AUDIT</span><br>
            Verifies all navigation routes are reachable.</p>
        </main>

        <!-- RIGHT: ACTION SIDEBAR -->
        <aside class="admin-sidebar">
            <!-- TOP ACTIONS -->
            <div class="admin-btn-group">
                <button class="btn-admin-solid">Audit Database</button>
                <button class="btn-admin-solid">Path Audit</button>
                <button class="btn-admin-solid">Full Audit</button>
            </div>

            <!-- BOTTOM HELP -->
            <div class="admin-btn-group">
                <button class="btn-admin-solid">Instructions</button>
                <button class="btn-admin-solid" style="background: white; color: black;">LOGOUT</button>
            </div>
        </aside>

    </div>

</body>
</html>
