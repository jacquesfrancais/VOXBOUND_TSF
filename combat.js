/**
 * VOXBOUND: combat.js
 * The Manager for combat interactions and Arena UI transitions.
 */

let isCombatActive = false;

function startCombatTurn(transcription, tier, targetNpcId) {
    isCombatActive = true;

    const feedbackArea = document.getElementById('command-feedback');
    feedbackArea.innerHTML = `<span style="color:var(--accent-gold); opacity:0.7;">&gt; [SYSTEM] EXÉCUTION DU COMBAT...</span>`;

    fetch('process_combat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            npcId: targetNpcId,
            command: transcription,
            tier: tier
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.debug) console.table(data.debug);
        if (data.success) {
            renderCombatRound(data);
        } else {
            feedbackArea.innerHTML = `<span style="color:#ff5555;">&gt; ERREUR COMBAT: ${data.error}</span>`;
        }
    })
    .catch(err => console.error("[COMBAT] Critical Protocol Error:", err));
}

function renderCombatRound(data) {
    const roomDesc = document.getElementById('room-description');
    
    // Display the combat log as a sequence of events
    let logHtml = "";
    data.log.forEach(line => {
        logHtml += `<div style="margin-bottom:5px;">&gt; ${line}</div>`;
    });

    if (data.dead) {
        // Display Death Screen
        const feedbackArea = document.getElementById('command-feedback');
        if (feedbackArea) feedbackArea.innerHTML = `<span style="color:#ff5555; font-weight:bold;">&gt; SYSTEM CRITICAL: VITAL SIGNS LOST</span>`;

        let deathHtml = `<div style="color:#ff5555; font-weight:bold; margin-bottom:15px; border-bottom:1px solid #ff5555; padding-bottom:10px;">&gt; TERMINAL ALERT: CHARACTER TERMINATED</div>`;
        deathHtml += `<div style="margin-bottom:20px;">${logHtml}</div>`;
        deathHtml += `<div style="color:var(--accent-gold); font-size:0.9rem; background:rgba(255,0,0,0.1); padding:10px; border-left:3px solid var(--accent-gold);">
            RÉCAPITULATIF DES PERTES :<br>
            - SANTÉ : Échec Critique (0 HP)<br>
            - OR : -${parseFloat(data.goldLost).toFixed(2)}G (Pénalité de 10%)
        </div>`;

        deathHtml += `<div style="margin-top:30px; display:flex; flex-direction:column; gap:12px;">
            <button class="btn-neon" onclick="resurrectPlayer()" style="background:#ff5555; color:white; border:none; box-shadow: 0 0 10px #ff5555;">RESSUSCITER LE JOUEUR</button>
            <button class="btn-outline" onclick="hardResetGame()" style="color:#888; border-color:#444; font-size:0.7rem;">RÉINITIALISER (NOUVELLE PARTIE)</button>
        </div>`;

        roomDesc.innerHTML = `<div style="color:var(--accent-gold); font-family:var(--font-mono);">${deathHtml}</div>`;
        if (window.VoxUI) window.VoxUI.playEffect('error');
        return;
    }
    
    // Create a "Continue" button to allow the player to digest the combat log
    const continueBtn = `<div style="margin-top:20px; text-align:center;">
        <button class="btn-neon" onclick="resolveCombatTurn(${data.victory ? 'true' : 'false'})" style="padding: 5px 20px;">CONTINUER</button>
    </div>`;

    roomDesc.innerHTML = `<div style="color:var(--accent-gold); font-family:var(--font-mono);">${logHtml}${continueBtn}</div>`;

    // Use the success chime if it was a good round
    if (!data.victory && window.VoxUI) window.VoxUI.playEffect('success');
}

/**
 * Finalizes the combat round and restores the UI/Syncs world state.
 */
function resolveCombatTurn(isVictory) {
    isCombatActive = false;

    if (isVictory && window.VoxUI) {
        window.VoxUI.setUIMode('exploration');
    }
    if (typeof initializeGame === 'function') initializeGame();
}

/**
 * Death Handshake: Resurrects the player at their last checkpoint with a gold penalty.
 */
function resurrectPlayer() {
    fetch('reset_game.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'resurrect' })
    })
    .then(res => res.json())
    .then(data => {
        isCombatActive = false;
        if (window.VoxUI) window.VoxUI.setUIMode('exploration');
        initializeGame();
    });
}

/**
 * Death Handshake: Completely wipes state and restarts the character.
 */
function hardResetGame() {
    const warning = "CRITICAL: This will reset your progress, gold, and attributes to factory defaults. Continue?";
    if (!confirm(warning)) return;

    fetch('reset_game.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'hard_reset' })
    })
    .then(res => res.json())
    .then(data => {
        isCombatActive = false;
        if (window.VoxUI) window.VoxUI.setUIMode('exploration');
        initializeGame();
    });
}