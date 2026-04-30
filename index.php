<?php
session_start();
require_once __DIR__ . '/db_config.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/* --------------------------------------------------
   STATE
-------------------------------------------------- */
$isAuthenticated = isset($_SESSION['user_id']);
$hasCharacter    = isset($_SESSION['character_id']);
$errorMessage = '';

$existingCharacterId = null;
$characterData = null;

if ($isAuthenticated) {
    $stmt = $pdo->prepare(
        "SELECT * FROM Characters WHERE ownerId = :ownerId LIMIT 1"
    );
    $stmt->execute(['ownerId' => $_SESSION['username']]);
    $characterData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($characterData) {
        $existingCharacterId = $characterData['id'];
        // Restore session if missing
        if (!isset($_SESSION['character_id'])) {
            $_SESSION['character_id'] = $existingCharacterId;
        }
    }
}

/* --------------------------------------------------
   DETECT EXISTING CHARACTER (DATABASE = SOURCE OF TRUTH)
-------------------------------------------------- */
// Simplify: $hasCharacter can be determined directly from $existingCharacterId
$hasCharacter = ($existingCharacterId !== false && $existingCharacterId !== null);

if ($isAuthenticated && !isset($_SESSION['email'])) {
    // Ensure email is available for existing sessions
    $stmt = $pdo->prepare("SELECT email FROM Users WHERE userId = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $_SESSION['email'] = $stmt->fetchColumn();
}

/* --------------------------------------------------
   STEP 1: LOGIN / REGISTER
-------------------------------------------------- */

// LOGOUT HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isAuthenticated) {

    $action   = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email    = trim($_POST['email'] ?? '');

    if ($username === '' || $password === '') {
        $errorMessage = 'Username and password are required.';
    } else {

        if ($action === 'login') {
            $stmt = $pdo->prepare(
                'SELECT userId, email, passwordHash FROM Users WHERE username = :username'
            );
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['passwordHash'])) {
                $_SESSION['user_id']  = $user['userId'];
                $_SESSION['username'] = $username;
                $_SESSION['email']    = $user['email'];
                header('Location: index.php');
                exit;
            } else {
                $errorMessage = 'Invalid credentials.';
            }
        }

        if ($action === 'register') {
            if ($email === '') {
                $errorMessage = 'Email is required for registration.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO Users (username, email, passwordHash)
                         VALUES (:username, :email, :hash)'
                    );
                    $stmt->execute([
                        'username' => $username,
                        'email'    => $email,
                        'hash'     => password_hash($password, PASSWORD_DEFAULT)
                    ]);

                    $_SESSION['user_id']  = $pdo->lastInsertId();
                    $_SESSION['username'] = $username;
                    $_SESSION['email']    = $email;
                    header('Location: index.php');
                    exit;
                } catch (PDOException $e) {
                    $errorMessage = 'Username or email already exists.';
                }
            }
        }
    }
}

/* --------------------------------------------------
   STEP 2: CHARACTER CREATION
   ✅ BLOCKED IF CHARACTER ALREADY EXISTS IN DB
-------------------------------------------------- */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'create_character' &&
    $isAuthenticated &&
    !$hasCharacter
) {
    $characterName = trim($_POST['character_name'] ?? '');

    if ($characterName === '') {
        $errorMessage = 'Character name is required.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO Characters (characterName, ownerId, hitPoints, maxHitPoints, strength, agility, charisma, gold, currentLocationID, respawnNodeId)
             VALUES (:name, :ownerId, 100, 100, 10, 10, 10, 50.00, 101, 101)'
        );
        $stmt->execute([
            'name'  => $characterName,
            'ownerId' => $_SESSION['username']
        ]);

        $_SESSION['character_id'] = $pdo->lastInsertId();
        header('Location: voice_calibration.php');
        exit;
    }
}
?>

<!-- HTML BELOW IS COMPLETELY UNCHANGED -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VOXBOUND: The Spoken Frontier</title>

    <link rel="stylesheet" href="adventure-base.css">

    <style>
        .login-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            padding: 60px;
            max-width: 1200px;
            margin: auto;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            background: #000;
            border: 1px solid var(--primary-cyan);
            color: var(--primary-cyan);
            padding: 10px;
            font-family: var(--font-mono);
            margin: 5px 0 20px 0;
            box-sizing: border-box;
        }

        .attribute-display {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
            color: var(--accent-gold);
            font-size: 0.9rem;
        }

        .status-text {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            display: block;
            color: #555;
        }

        .disabled {
            opacity: 0.15;
            pointer-events: none;
        }

        .error {
            color: #ff6666;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<!-- TOP SYSTEM BAR -->
<nav style="display:flex; justify-content: center; padding: 20px; background: #161b22; border-bottom: 1px solid #333;">
    <div style="color: var(--primary-cyan); font-weight: bold; letter-spacing: 2px;">
        VOXBOUND SYSTEM ACCESS
    </div>
</nav>

<main class="login-grid">

<!-- LEFT: ACCOUNT ACCESS -->
<section class="console-box">

    <div style="margin-bottom: 25px; border-bottom: 1px solid #222; padding-bottom: 15px;">
        <p style="color: var(--accent-gold); margin: 0 0 5px 0; font-size: 1.1rem; letter-spacing: 1px;">
            &gt; INITIALIZING VOXBOUND INTERFACE
        </p>
        <div style="color: #888; font-size: 0.8rem; line-height: 1.5;">
            <p>
                Welcome to <span style="color: var(--primary-cyan);">VOXBOUND: The Spoken Frontier</span>.
                This is a voice‑controlled RPG where accuracy determines outcomes.
            </p>
            <p style="margin-top: 10px;">
                • ACTION: Navigate using spoken commands.<br>
                • MISSION: Master French pronunciation to shape the world.<br>
                • PROTOCOL: Speak clearly. The system evaluates accuracy.
            </p>
        </div>
    </div>

    <div style="background: rgba(0,242,255,0.05); border-left: 3px solid var(--primary-cyan); padding: 10px 15px; margin-bottom: 20px;">
        <strong style="color: var(--primary-cyan);">SYSTEM ACCESS:</strong>
        Enter credentials to log in or create a new account.
    </div>

    <div class="title-label">STEP 1: ACCOUNT ACCESS</div>
    <span class="status-text">
        <?= $isAuthenticated ? 'Status: AUTHENTICATED' : 'Status: Awaiting Input...' ?>
    </span>

    <?php if ($errorMessage): ?>
        <div class="error"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <?php if (!$isAuthenticated): ?>
        <form method="POST">
            <label>USERNAME</label>
            <input type="text" name="username" required>

            <label>EMAIL ADDRESS (registration only)</label>
            <input type="email" name="email">

            <label>ACCESS KEY (PASSWORD)</label>
            <input type="password" name="password" required>

            <div style="display: flex; gap: 10px;">
                <button class="btn-neon" name="action" value="login" style="flex: 1;">LOGIN</button>
                <button class="btn-outline" name="action" value="register" style="flex: 1;">REGISTER</button>
            </div>
        </form>
    <?php else: ?>
        <div style="margin-top: 10px;">
            <div style="margin-bottom: 20px; border-top: 1px solid #222; padding-top: 15px;">
                <div style="color: var(--accent-gold); font-size: 0.8rem; text-transform: uppercase; margin-bottom: 5px;">Authenticated User:</div>
                <div style="color: var(--primary-cyan); font-weight: bold; font-size: 1.1rem;"><?= htmlspecialchars($_SESSION['username']) ?></div>
                <div style="color: #888; font-size: 0.85rem;"><?= htmlspecialchars($_SESSION['email']) ?></div>
            </div>
            <form method="POST">
                <button class="btn-outline" name="action" value="logout" style="width: 100%; font-size: 0.7rem;">LOGOUT / DISCONNECT</button>
            </form>
        </div>
    <?php endif; ?>

</section>

<!-- RIGHT: CHARACTER INITIALIZATION -->
<section class="console-box <?= !$isAuthenticated ? 'disabled' : '' ?>">

    <div class="title-label">STEP 2: CHARACTER INITIALIZATION</div>

    <?php if ($isAuthenticated): ?>
        <?php if (!$hasCharacter): ?>
        <form method="POST">
            <input type="hidden" name="action" value="create_character">

            <label>CHARACTER NAME</label>
            <input type="text" name="character_name" placeholder="Assign Name..." required>

            <div class="attribute-display">
                <div>HP: 100</div>
                <div>STR: 10</div>
                <div>AGI: 10</div>
                <div>CHA: 10</div>
                <div>GOLD: 50.00</div>
                <div>LOC: 101</div>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <button class="btn-outline" style="flex: 1;" disabled>REROLL</button>
                <button class="btn-neon" style="flex: 2;">CREATE CHARACTER</button>
            </div>
        </form>
        <?php else: ?>
            <div style="background: rgba(233, 250, 112, 0.05); border-left: 3px solid var(--accent-gold); padding: 15px; margin-bottom: 25px;">
                <p style="color: var(--accent-gold); margin: 0; font-size: 0.9rem;">&gt; CHARACTER DATA VERIFIED</p>
                
                <p style="color: var(--primary-cyan); font-weight: bold; margin: 10px 0 5px 0; text-transform: uppercase;">
                    NAME: <?= htmlspecialchars($characterData['characterName']) ?>
                </p>

                <div class="attribute-display" style="margin-top: 5px; opacity: 0.8;">
                    <div>HP: <?= (int)$characterData['hitPoints'] ?> / <?= (int)$characterData['maxHitPoints'] ?></div>
                    <div>STR: <?= (int)$characterData['strength'] ?></div>
                    <div>AGI: <?= (int)$characterData['agility'] ?></div>
                    <div>CHA: <?= (int)$characterData['charisma'] ?></div>
                    <div>GOLD: <?= number_format($characterData['gold'], 2) ?></div>
                    <div>LOC: <?= (int)$characterData['currentLocationID'] ?></div>
                </div>

                <p style="color: #666; font-size: 0.75rem; margin: 15px 0 0 0; border-top: 1px solid #222; padding-top: 10px;">
                    The terminal has identified an active character bound to <span style="color: var(--primary-cyan);"><?= htmlspecialchars($_SESSION['username']) ?></span>.
                </p>
            </div>

            <a href="voice_calibration.php" class="btn-neon" style="display: block; text-align: center; text-decoration: none;">RESUME ADVENTURE</a>
        <?php endif; ?>
    <?php endif; ?>

</section>

</main>

<footer style="text-align: center; padding: 20px; color: #333; font-size: 0.7rem;">
    SECURE_CONNECTION: AES‑256 // ORIGIN: <?= htmlspecialchars($_SERVER['REMOTE_ADDR']); ?>
</footer>

</body>
</html>