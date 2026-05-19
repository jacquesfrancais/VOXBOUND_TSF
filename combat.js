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
    });
}

function renderCombatRound(data) {
    const roomDesc = document.getElementById('room-description');
    
    // Display the combat log as a sequence of events
    let logHtml = "";
    data.log.forEach(line => {
        logHtml += `<div style="margin-bottom:5px;">&gt; ${line}</div>`;
    });
    
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