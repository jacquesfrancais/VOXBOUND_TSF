<!-- index.php - Terminal-style login and character creation page for the Lingua Quest French Learning RPG -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lingua Quest - Terminal Login</title>
    <link rel="stylesheet" href="adventure-base.css">
    <style>
        /* Specific tweaks for the Login Layout */
        .login-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            padding: 60px;
            max-width: 1200px;
            margin: auto;
        }
        
        input[type="text"], input[type="password"], input[type="email"] {
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
        }
    </style>
</head>
<body>

    <!-- TOP NAVIGATION BAR -->
    <nav style="display:flex; justify-content: center; padding: 20px; background: #161b22; border-bottom: 1px solid #333;">
        <div style="color: var(--primary-cyan); font-weight: bold; letter-spacing: 2px;">LINGUA QUEST SYSTEM ACCESS</div>
    </nav>

        <main class="login-grid">
        <!-- LEFT: USER ACCOUNT ACCESS -->
        <section class="console-box">
            
            <!-- Move Welcome Message Here -->
            <div style="margin-bottom: 25px; border-bottom: 1px solid #222; padding-bottom: 15px;">
                <p style="color: var(--accent-gold); margin: 0 0 5px 0; font-size: 1.1rem; letter-spacing: 1px;">
                    > INITIALIZING LINGUA QUEST INTERFACE
                </p>
                <div style="color: #888; font-size: 0.8rem; line-height: 1.5; margin: 0;">
                    <p>Welcome to a bilingual RPG adventure designed to bridge the gap between study and immersion. In this realm, <span style="color: var(--primary-cyan);">your voice is your weapon</span>.</p>
                    <p style="margin-top: 10px;">
                        <span style="color: var(--text-main);">• ACTION:</span> Navigate using a <span style="color: var(--accent-gold);">Voice Command Interface</span>.<br>
                        <span style="color: var(--text-main);">• MISSION:</span> Master French pronunciation to trigger events and defeat foes.<br>
                        <span style="color: var(--text-main);">• PROTOCOL:</span> Speak clearly; the system rewards accuracy with progression and gold.
                    </p>
                </div>
            </div>

            <!-- Move Prompt Here -->
            <div style="background: rgba(0, 242, 255, 0.05); border-left: 3px solid var(--primary-cyan); padding: 10px 15px; margin-bottom: 20px;">
                <p style="margin: 0; font-size: 0.85rem; color: var(--text-main);">
                    <strong style="color: var(--primary-cyan);">SYSTEM ACCESS:</strong> 
                    Enter credentials to login, or provide a new Username/Key to <strong>Create an Account</strong>. 
                </p>
            </div>

            <div class="title-label">User Authentication</div>
            <span class="status-text" style="color: #555;">Status: Awaiting Input...</span>

            <label>USERNAME</label>
            <input type="text" name="username" placeholder="Unique Identifier">
            
            <label>EMAIL ADDRESS</label>
            <input type="email" name="email" placeholder="user@domain.com">
            
            <label>ACCESS KEY (PASSWORD)</label>
            <input type="password" name="password" placeholder="••••••••">
            
            <div style="display: flex; gap: 10px;">
                <button class="btn-neon" style="flex: 1;">LOGIN</button>
                <button class="btn-outline" style="flex: 1;">REGISTER</button>
            </div>
        </section>


                <!-- RIGHT: CHARACTER CREATION / INFO DISPLAY -->
        <section class="console-box" style="opacity: 0.1;">
            <div class="title-label">Character Initialization</div>
            
            <label>CHARACTER NAME</label>
            <input type="text" placeholder="Assign Name...">

            <div class="attribute-display">
                <div>HP: 100</div>
                <div>STR: 10</div>
                <div>AGI: 10</div>
                <div>CHA: 10</div>
                <div>GOLD: 50.00</div>
                <div>LOC: 101</div>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <button class="btn-outline" style="flex: 1;">REROLL STATS</button>
                <button class="btn-neon" style="flex: 2;">CREATE CHARACTER</button>
            </div>
    
        </section>
    </main>


    <footer style="text-align: center; padding: 20px; color: #333; font-size: 0.7rem;">
        SECURE_CONNECTION: AES-256 // ENTRANCE_ID: <?php echo $_SERVER['REMOTE_ADDR']; ?>
    </footer>

</body>
</html>
