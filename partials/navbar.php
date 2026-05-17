<nav style="display:flex; justify-content: space-between; padding: 20px 40px; background: #161b22; border-bottom: 1px solid #333;">
    <div style="display: flex; align-items: center; color: var(--primary-cyan); font-weight: bold; letter-spacing: 1px;">
        <img src="public/icons/VOXBOUND_logo180x180.png" alt="VoxBound Logo" style="height: 30px; margin-right: 15px;">
        VOXBOUND: THE SPOKEN FRONTIER
    </div>
    <div style="display:flex; gap: 30px;">
        <a href="index.php" style="color:var(--primary-cyan); text-decoration:none;">HOME</a>
        <?php if (isset($_SESSION['character_id'])): ?>
            <a href="adventure.php" style="color:white; text-decoration:none;">ADVENTURE</a>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
            <a href="admin.php" style="color:white; text-decoration:none;">ADMIN</a>
            <a href="editor.php" style="color:white; text-decoration:none;">EDITOR</a>
        <?php endif; ?>
    </div>
</nav>