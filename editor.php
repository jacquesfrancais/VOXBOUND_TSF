<?php
/**
 * editor.php
 * VOXBOUND: The Spoken Frontier
 * Admin Content Editor for static world data.
 */

session_start();
require_once __DIR__ . '/db_config.php';

// 1. ADMIN SHIELD
// Verify the user is logged in and has the isAdmin flag.
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$adminCheck = $pdo->prepare("SELECT isAdmin FROM Users WHERE userId = :id");
$adminCheck->execute(['id' => $_SESSION['user_id']]);
$isAdmin = $adminCheck->fetchColumn();

if (!$isAdmin) {
    die("ACCESS DENIED: Administrative privileges required.");
}

// 2. CONFIGURATION
// Whitelist allowed tables to prevent SQL injection via dynamic table names
$allowedTables = [
    'Locations'   => 'nodeId',
    'Npcs'        => 'npcId',
    'ItemLibrary' => 'itemId'
];

$currentTable = $_GET['table'] ?? 'Locations';
if (!array_key_exists($currentTable, $allowedTables)) {
    $currentTable = 'Locations';
}
$primaryKey = $allowedTables[$currentTable];

// 3. HANDLE UPDATES (The Judge)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $idToUpdate = $_POST[$primaryKey];
    $updateFields = [];
    $params = [':id' => $idToUpdate];

    // Dynamically build the UPDATE query based on POST keys
    foreach ($_POST as $key => $value) {
        // Skip internal form controls
        if (in_array($key, ['action', $primaryKey])) continue;
        
        $updateFields[] = "$key = :$key";
        $params[":$key"] = $value;
    }

    $sql = "UPDATE $currentTable SET " . implode(', ', $updateFields) . " WHERE $primaryKey = :id";
    $updateStmt = $pdo->prepare($sql);
    $updateStmt->execute($params);
    
    header("Location: editor.php?table=$currentTable&success=1");
    exit;
}

// 4. FETCH EDIT TARGET
$editRow = null;
if (isset($_GET['edit'])) {
    $editStmt = $pdo->prepare("SELECT * FROM $currentTable WHERE $primaryKey = :id");
    $editStmt->execute(['id' => $_GET['edit']]);
    $editRow = $editStmt->fetch(PDO::FETCH_ASSOC);
}

// 3. DATA FETCHING
// Get all rows for the current table
$stmt = $pdo->query("SELECT * FROM $currentTable ORDER BY $primaryKey ASC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VOXBOUND – Content Editor</title>
    <link rel="stylesheet" href="adventure-base.css">
    <link rel="stylesheet" href="admin-base.css">
    <style>
        .editor-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.8rem;
        }
        .editor-table th {
            text-align: left;
            color: var(--primary-cyan);
            border-bottom: 1px solid #333;
            padding: 10px;
        }
        .editor-table td {
            padding: 10px;
            border-bottom: 1px solid #111;
            color: #ccc;
        }
        .editor-table tr:hover {
            background: rgba(0, 242, 255, 0.05);
        }
        .table-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .active-tab {
            background: var(--primary-cyan) !important;
            color: black !important;
        }
        .edit-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            background: #050505;
            padding: 20px;
            border: 1px solid #222;
        }
        .edit-form-grid label {
            display: block;
            color: var(--primary-cyan);
            font-size: 0.7rem;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <!-- TOP NAVIGATION BAR -->
    <nav style="display:flex; justify-content: space-between; padding: 20px 40px; background: #161b22; border-bottom: 1px solid #333;">
        <div style="color: var(--primary-cyan); font-weight: bold; letter-spacing: 1px;">VOXBOUND: CONTENT EDITOR</div>
        <div style="display:flex; gap: 30px;">
            <a href="index.php" style="color:var(--primary-cyan); text-decoration:none;">HOME</a>
            <a href="adventure.php" style="color:white; text-decoration:none;">ADVENTURE</a>
            <a href="admin.php" style="color:white; text-decoration:none;">ADMIN</a>
            <a href="editor.php" style="color:white; text-decoration:none;">EDITOR</a>
        </div>
    </nav>

    <div class="admin-view-wrapper">
        
        <!-- CENTER: TABLE DATA VIEW -->
        <main class="admin-console">
            <div class="table-nav">
                <?php foreach ($allowedTables as $tableName => $pk): ?>
                    <a href="?table=<?= $tableName ?>" 
                       class="btn-admin-solid <?= $currentTable === $tableName ? 'active-tab' : '' ?>" 
                       style="text-decoration:none;">
                        <?= strtoupper($tableName) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <h2 style="color: var(--primary-cyan); margin-top: 0;">DATABASE TABLE: <?= strtoupper($currentTable) ?></h2>
            <p style="font-size: 0.8rem; color: #888;">&gt; Accessing static library truth... Found <?= count($rows) ?> records.</p>

            <?php if ($editRow): ?>
                <!-- EDIT INTERFACE -->
                <div style="margin-bottom: 40px; border: 1px solid var(--accent-gold); padding: 20px;">
                    <h3 style="color: var(--accent-gold); margin-top: 0;">EDITING ENTRY: <?= htmlspecialchars($editRow[$primaryKey]) ?></h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="<?= $primaryKey ?>" value="<?= htmlspecialchars($editRow[$primaryKey]) ?>">
                        
                        <div class="edit-form-grid">
                            <?php foreach ($editRow as $column => $value): ?>
                                <?php if ($column === $primaryKey) continue; ?>
                                <div>
                                    <label><?= htmlspecialchars($column) ?></label>
                                    <?php if (strpos($column, 'text') !== false || strpos($column, 'greeting') !== false): ?>
                                        <textarea name="<?= $column ?>" style="width:100%; height:80px; background:#000; border:1px solid #333; color:#fff; font-family:var(--font-mono); font-size:0.8rem;"><?= htmlspecialchars($value ?? '') ?></textarea>
                                    <?php else: ?>
                                        <input type="text" name="<?= $column ?>" value="<?= htmlspecialchars($value ?? '') ?>" style="width:100%; background:#000; border:1px solid #333; color:#fff; font-family:var(--font-mono); padding:5px;">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top: 20px; display:flex; gap: 10px;">
                            <button class="btn-neon">SAVE CHANGES</button>
                            <a href="?table=<?= $currentTable ?>" class="btn-outline" style="text-decoration:none; display:inline-block; line-height:30px;">CANCEL</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <table class="editor-table">
                <thead>
                    <tr>
                        <?php if (!empty($rows)): ?>
                            <?php foreach (array_keys($rows[0]) as $column): ?>
                                <th><?= htmlspecialchars($column) ?></th>
                            <?php endforeach; ?>
                            <th>ACTIONS</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?= htmlspecialchars(substr((string)$value, 0, 50)) ?><?= strlen((string)$value) > 50 ? '...' : '' ?></td>
                            <?php endforeach; ?>
                            <td>
                                <a href="?table=<?= $currentTable ?>&edit=<?= $row[$primaryKey] ?>" class="btn-outline" style="font-size: 0.6rem; padding: 2px 5px; text-decoration:none;">EDIT</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>

        <!-- RIGHT: EDITOR ACTIONS -->
        <aside class="admin-sidebar">
            <div class="admin-btn-group">
                <button class="btn-admin-solid" style="border-color: var(--accent-gold); color: var(--accent-gold);">+ ADD NEW ENTRY</button>
                <button class="btn-admin-solid">EXPORT TSV</button>
                <button class="btn-admin-solid">IMPORT DATA</button>
            </div>

            <div class="admin-btn-group">
                <p style="color: #444; font-size: 0.7rem; text-align: center;">V.2.6 CONTENT EDITOR</p>
            </div>
        </aside>

    </div>
</body>
</html>