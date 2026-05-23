/**
 * VOXBOUND: inventory.js
 * The Manager for item interactions and carrying capacity feedback.
 */

function takeItem(instanceId) {
    console.log(`[ENGINE] Attempting to take item instance: ${instanceId}`);

    fetch('process_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'take', instanceId: instanceId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.debug) console.table(data.debug);
        
        if (data.success) {
            if (typeof initializeGame === 'function') initializeGame();
        } else {
            const feedbackArea = document.getElementById('command-feedback');
            if (feedbackArea) feedbackArea.innerHTML = `<span style="color:#ff5555;">&gt; ${data.error}</span>`;
            if (window.VoxUI) window.VoxUI.playEffect('error');
        }
    })
    .catch(err => console.error("[ENGINE] Item interaction communication error:", err));
}

/**
 * ITEM SYSTEM: Sends a request to drop an item into the current room.
 * @param {number} instanceId 
 */
function dropItem(instanceId) {
    console.log(`[ENGINE] Attempting to drop item instance: ${instanceId}`);

    fetch('process_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'drop', instanceId: instanceId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.debug) console.table(data.debug);
        
        if (data.success) {
            // Synchronize world state to reflect item on floor and lighter inventory
            if (typeof initializeGame === 'function') initializeGame();
        } else {
            const feedbackArea = document.getElementById('command-feedback');
            if (feedbackArea) feedbackArea.innerHTML = `<span style="color:#ff5555;">&gt; ${data.error}</span>`;
            if (window.VoxUI) window.VoxUI.playEffect('error');
        }
    })
    .catch(err => console.error("[ENGINE] Item interaction communication error:", err));
}

/**
 * ITEM SYSTEM: Sends a request to equip/unequip an item.
 * @param {number} instanceId 
 */
function equipItem(instanceId) {
    console.log(`[INVENTORY] Toggling equipment for instance: ${instanceId}`);
    fetch('process_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'equip', instanceId: instanceId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (typeof initializeGame === 'function') initializeGame();
        } else {
            const feedbackArea = document.getElementById('command-feedback');
            if (feedbackArea) feedbackArea.innerHTML = `<span style="color:#ff5555;">&gt; ${data.error}</span>`;
        }
    })
    .catch(err => console.error("[ENGINE] Item equipment communication error:", err));
}