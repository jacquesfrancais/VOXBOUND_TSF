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
    'ItemLibrary' => 'itemId',
    'Characters'  => 'id',
    'Users'       => 'userId'
];

$currentTable = $_GET['table'] ?? 'Locations';
if (!array_key_exists($currentTable, $allowedTables)) {
    $currentTable = 'Locations';
}
$primaryKey = $allowedTables[$currentTable];

// 3. HANDLE UPDATES, INSERTS & DELETES (The Judge)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'delete' && isset($_POST[$primaryKey])) {
        $idToDelete = $_POST[$primaryKey];
        $stmt = $pdo->prepare("DELETE FROM $currentTable WHERE $primaryKey = :id");
        $stmt->execute(['id' => $idToDelete]);
        header("Location: editor.php?table=$currentTable&deleted=1");
        exit;
    }

    if ($action === 'update' || $action === 'insert') {
        $fields = [];
        $params = [];

        foreach ($_POST as $key => $value) {
            if ($key === 'action') continue;
            // Skip Primary Key during updates to prevent changing IDs
            if ($action === 'update' && $key === $primaryKey) continue;
            // Skip Primary Key during inserts if empty (let DB auto-increment handle it)
            if ($action === 'insert' && $key === $primaryKey && empty($value)) continue;
            
            $fields[] = $key;
            $params[":$key"] = $value;
        }

        if ($action === 'update') {
            $setClause = implode(', ', array_map(fn($f) => "$f = :$f", $fields));
            $params[':target_id'] = $_POST[$primaryKey];
            $sql = "UPDATE $currentTable SET $setClause WHERE $primaryKey = :target_id";
        } else {
            $cols = implode(', ', $fields);
            $vals = implode(', ', array_map(fn($f) => ":$f", $fields));
            $sql = "INSERT INTO $currentTable ($cols) VALUES ($vals)";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        header("Location: editor.php?table=$currentTable&success=1");
        exit;
    }
}

// 3.5 HANDLE EXPORT (The Judge)
if (isset($_GET['export'])) {
    // Fetch all records for the currently selected table
    $stmt = $pdo->query("SELECT * FROM $currentTable ORDER BY $primaryKey ASC");
    $exportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($exportRows)) {
        $filename = strtolower($currentTable) . "_export_" . date('Y-m-d_His') . ".tsv";

        // Set headers to force file download as TSV
        header('Content-Type: text/tab-separated-values');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // Write column headers as the first line
        fputcsv($out, array_keys($exportRows[0]), "\t", '"', "\\");
        // Write data rows
        foreach ($exportRows as $row) {
            fputcsv($out, $row, "\t", '"', "\\");
        }
        fclose($out);
        exit;
    }
}

// 3.6 HANDLE IMPORT (The Judge)
// 3.6 HANDLE IMPORT (The Judge)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import' && isset($_FILES['tsv_file'])) {
    $file = $_FILES['tsv_file'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("UPLOAD ERROR: System returned error code " . $file['error']);
    }

    if (is_uploaded_file($file['tmp_name'])) {
        $handle = fopen($file['tmp_name'], 'r');

        // Detect and strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle, 0, "\t", '"', "\\");

        if ($headers) {
            $pdo->beginTransaction();
            try {
                while (($data = fgetcsv($handle, 0, "\t", '"', "\\")) !== FALSE) {
                    // Skip empty lines or data mismatches
                    if (empty($data) || count($data) !== count($headers)) continue;

                    $row = array_combine($headers, $data);

                    // Backtick column names to prevent reserved word conflicts
                    $cols = implode(', ', array_map(fn($k) => "`$k`", $headers));
                    $placeholders = implode(', ', array_map(fn($k) => ":$k", $headers));

                    // Build the ON DUPLICATE KEY UPDATE clause
                    $updateParts = [];
                    foreach ($headers as $key) {
                        if ($key === $primaryKey) continue;
                        $updateParts[] = "`$key` = VALUES(`$key`)";
                    }
                    $updateClause = implode(', ', $updateParts);

                    $sql = "INSERT INTO `$currentTable` ($cols) VALUES ($placeholders) 
                            ON DUPLICATE KEY UPDATE $updateClause";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($row);
                }
                $pdo->commit();
                fclose($handle);
                header("Location: editor.php?table=$currentTable&imported=1");
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                fclose($handle);
                die("IMPORT ERROR: " . $e->getMessage());
            }
        }
        fclose($handle);
    }
}

// 4. FETCH EDIT TARGET OR INITIALIZE NEW (The Manager's Data)
$editRow = null;
$isAdding = isset($_GET['add']);

if (isset($_GET['edit'])) {
    $editStmt = $pdo->prepare("SELECT * FROM $currentTable WHERE $primaryKey = :id");
    $editStmt->execute(['id' => $_GET['edit']]);
    $editRow = $editStmt->fetch(PDO::FETCH_ASSOC);
} elseif ($isAdding) {
    // Generate an empty row based on table schema for the "Add" form
    $q = $pdo->query("DESCRIBE $currentTable");
    $columns = $q->fetchAll(PDO::FETCH_COLUMN);
    $editRow = array_fill_keys($columns, '');
}

// 5. DATA FETCHING
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
                    <h3 style="color: var(--accent-gold); margin-top: 0;">
                        <?= $isAdding ? 'ADDING NEW ENTRY' : 'EDITING ENTRY: ' . htmlspecialchars($editRow[$primaryKey]) ?>
                    </h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $isAdding ? 'insert' : 'update' ?>">
                        <input type="hidden" name="<?= $primaryKey ?>" value="<?= htmlspecialchars($editRow[$primaryKey]) ?>">
                        
                        <div class="edit-form-grid">
                            <?php foreach ($editRow as $column => $value): ?>
                                <?php // Hide PK field in Edit mode, show in Add mode if it's not auto-incrementing (like Locations) ?>
                                <?php if (!$isAdding && $column === $primaryKey) continue; ?>
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
                            <button class="btn-neon"><?= $isAdding ? 'CREATE ENTRY' : 'SAVE CHANGES' ?></button>
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
                                <form method="POST" style="display:inline;" onsubmit="return confirm('CRITICAL: Permanent deletion of system data requested. Confirm erasure?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="<?= $primaryKey ?>" value="<?= htmlspecialchars($row[$primaryKey]) ?>">
                                    <button type="submit" class="btn-outline" style="font-size: 0.6rem; padding: 2px 5px; color: #ff5555; border-color: #ff5555; cursor:pointer;">DELETE</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>

        <!-- RIGHT: EDITOR ACTIONS -->
        <aside class="admin-sidebar">
            <div class="admin-btn-group">
                <a href="?table=<?= $currentTable ?>&add=1" class="btn-admin-solid" style="border-color: var(--accent-gold); color: var(--accent-gold); text-decoration:none; display:block; text-align:center;">
                    + ADD NEW ENTRY
                </a>
                <a href="?table=<?= $currentTable ?>&export=1" class="btn-admin-solid" onclick="return confirm('SYSTEM: Confirm data stream request? This will generate a TSV download of the currently selected table.');" style="text-decoration:none; display:block; text-align:center;">
                    EXPORT TSV
                </a>
                <form method="POST" enctype="multipart/form-data" style="margin:0;">
                    <input type="hidden" name="action" value="import">
                    <label class="btn-admin-solid" style="display:block; text-align:center; cursor:pointer;">
                        IMPORT TSV
                        <input type="file" name="tsv_file" accept=".tsv,.txt" style="display:none;" onchange="this.form.submit()">
                    </label>
                </form>
            </div>

            <div class="admin-btn-group">
                <p style="color: #444; font-size: 0.7rem; text-align: center;">V.2.6 CONTENT EDITOR</p>
            </div>
        </aside>

    </div>
</body>
</html>