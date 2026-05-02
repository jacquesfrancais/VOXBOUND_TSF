/**
 * engine.js
 * VOXBOUND: The Spoken Frontier
 * Game Engine Logic
 */

/**
 * Updates the Stage (HTML) with the Truth (Data) provided by the Judges.
 * Handles room descriptions, entity lists, and character stats.
 * @param {Object} data - The state data returned from PHP workers.
 */
function updateUI(data) {
    if (!data) return;

    console.log("[ENGINE DEBUG] Updating UI with incoming data packet.");

    // 1. Update Identity & Location
    const locationLabel = document.getElementById('location-label');
    if (locationLabel && data.nodeId) {
        locationLabel.textContent = `Location ID: ${data.nodeId}`;
    }

    // 2. Update Narrative Description
    const roomDesc = document.getElementById('room-description');
    if (roomDesc && data.description) {
        // Using innerHTML allows the Judge to send <br> tags or formatted terminal text
        roomDesc.innerHTML = `&gt; ${data.description}`;
    }

    // 3. Update NPC List (Entities)
    const npcList = document.getElementById('npc-list');
    if (npcList) {
        npcList.innerHTML = ''; // Clear current state
        if (data.npcs && data.npcs.length > 0) {
            data.npcs.forEach(npc => {
                const li = document.createElement('li');
                li.style.cursor = "help";
                li.innerHTML = `• <span style="color:var(--accent-gold)">[PARLER]</span> ${npc.npcNameFrench}`;
                npcList.appendChild(li);
            });
        }
    }

    // 4. Update Object/Item List
    const objectList = document.getElementById('object-list');
    if (objectList) {
        objectList.innerHTML = '';
        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                const li = document.createElement('li');
                li.textContent = `[ ] ${item}`;
                objectList.appendChild(li);
            });
        }
    }

    // 5. Update Sidebar Stats (Character Truth)
    if (data.stats) {
        const s = data.stats;
        if (document.getElementById('stat-hp'))   document.getElementById('stat-hp').textContent = `${s.hitPoints} / ${s.maxHitPoints}`;
        if (document.getElementById('stat-str'))  document.getElementById('stat-str').textContent = s.strength;
        if (document.getElementById('stat-agi'))  document.getElementById('stat-agi').textContent = s.agility;
        if (document.getElementById('stat-gold')) document.getElementById('stat-gold').textContent = parseFloat(s.gold).toFixed(2);
    }

    // 6. Update Navigation Controls (Dim disconnected paths)
    const directions = ['nord', 'sud', 'est', 'ouest', 'haut', 'bas', 'entrer', 'sortir'];
    if (data.exits) {
        directions.forEach(dir => {
            const btn = document.getElementById(`btn-${dir}`);
            if (btn) {
                // Disable the button if the target node is 0 (disconnected)
                btn.disabled = !(data.exits[dir] > 0);
            }
        });
    }
}

/**
 * Communicates with the Move Judge to update the character's position.
 * @param {string} direction - The direction to move (nord, sud, etc.)
 */
function handleMove(direction) {
    console.log(`[ENGINE DEBUG] Dispatching move request: ${direction}`);

    fetch('move_player.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ direction: direction })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(`[ENGINE DEBUG] Move successful: ${data.message}`);
            if (data.debug) console.table(data.debug);
            // Refresh the environmental Truth after movement
            initializeGame();
        } else {
            // Visual feedback for blocked paths
            console.warn(`[ENGINE DEBUG] Move blocked: ${data.error}`);
            if (data.debug) console.table(data.debug);
            alert(data.error); 
        }
    })
    .catch(err => console.error("[ENGINE DEBUG] Movement communication error:", err));
}

/**
 * Bootstraps the game state by fetching the current room data from the Judge.
 */
function initializeGame() {
    console.log("[ENGINE DEBUG] Syncing initial game state...");

    fetch('get_room.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateUI(data);
                console.log("[ENGINE DEBUG] Synchronization complete.");
                if (data.debug) console.table(data.debug);
            } else {
                console.error("[ENGINE DEBUG] Server-side error:", data.error);
                if (data.debug) console.table(data.debug);
            }
        })
        .catch(err => console.error("[ENGINE DEBUG] Network error:", err));
}

// Trigger initialization when the window finishes loading the DOM
document.addEventListener('DOMContentLoaded', initializeGame);