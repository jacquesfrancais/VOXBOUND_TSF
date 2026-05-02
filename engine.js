/**
 * engine.js
 * VOXBOUND: The Spoken Frontier
 * Game Engine Logic
 */

let currentLanguage = 'fr'; // 'fr' or 'en'
let lastRoomData = null;    // Cache for the current room's bilingual data

/**
 * Updates the Stage (HTML) with the Truth (Data) provided by the Judges.
 * Handles room descriptions, entity lists, and character stats.
 * @param {Object} data - The state data returned from PHP workers.
 */
function updateUI(data) {
    if (!data) return;
    lastRoomData = data;

    console.log("[ENGINE DEBUG] Updating UI with incoming data packet.");

    // 1. Update Identity & Location
    const locationDisplay = document.getElementById('location-id-display');
    if (locationDisplay && data.title) {
        locationDisplay.textContent = data.title;
    }

    // 2. Update Narrative Description
    const descText = (currentLanguage === 'fr') ? data.descriptionFr : data.descriptionEn;
    const roomDesc = document.getElementById('room-description');
    if (roomDesc && descText) {
        // Using innerHTML allows the Judge to send <br> tags or formatted terminal text
        roomDesc.innerHTML = `&gt; ${descText}`;

        // Bible Section 3: Audio Support - Read the new description aloud via ui.js
        if (window.VoxUI && currentLanguage === 'fr') window.VoxUI.speakText(data.descriptionFr);
    }

    // 3. Update NPC List (Entities)
    const npcList = document.getElementById('npc-list');
    if (npcList) {
        npcList.innerHTML = ''; // Clear current state
        if (data.npcs && data.npcs.length > 0) {
            data.npcs.forEach(npc => {
                const li = document.createElement('li');
                li.style.cursor = "help";
                const name = (currentLanguage === 'fr') ? npc.npcNameFrench : npc.npcNameEnglish;
                li.innerHTML = `• <span style="color:var(--accent-gold)">[PARLER]</span> ${name}`;
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
                const name = (currentLanguage === 'fr') ? item.nameFrench : item.nameEnglish;
                li.textContent = `[ ] ${name}`;
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
    const directions = ['nord', 'sud', 'est', 'ouest', 'remonter', 'descendre', 'pénétrer', 'sortir'];
    if (data.exits) {
        directions.forEach(dir => {
            const btn = document.getElementById(`btn-${dir}`);
            if (btn) {
                // Disable the button if the target node is 0 (disconnected)
                btn.disabled = !(data.exits[dir] > 0);
            }
        });
    }

    // 7. Update Mini-Map
    updateMiniMap(data);
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

/**
 * Re-triggers the Text-to-Speech narration for the current room description.
 */
function replayRoomDescription() {
    const roomDesc = document.getElementById('room-description');
    if (roomDesc && window.VoxUI) {
        console.log("[ENGINE DEBUG] Manually re-triggering room narration.");
        // Strip the leading ">" prompt character before sending to the TTS engine
        const text = roomDesc.textContent.replace(/^>\s*/, '');
        window.VoxUI.speakText(text);
    }
}

/**
 * Toggles the UI between French and English and re-renders the current room content.
 */
function toggleLanguage() {
    currentLanguage = (currentLanguage === 'fr') ? 'en' : 'fr';
    console.log(`[ENGINE DEBUG] UI Language toggled to: ${currentLanguage}`);
    if (lastRoomData) {
        updateUI(lastRoomData);
    }
}

/**
 * Activates the speech recognition to listen for a general French command.
 * Dispatches the result to the process_command Judge.
 */
function startVoiceCommand() {
    if (!window.VoxSpeech) {
        console.error("[ENGINE] Speech system not initialized.");
        return;
    }

    const feedbackArea = document.getElementById('command-feedback');
    feedbackArea.innerHTML = `<span style="color:var(--primary-cyan); opacity:0.7;">&gt; [SYSTÈME] ÉCOUTE ACTIVE...</span>`;

    window.VoxSpeech.captureCommand("", (result) => {
        if (result.error) {
            feedbackArea.innerHTML = `<span style="color:#ff5555;">&gt; ERREUR: ${result.error}</span>`;
            return;
        }

        // Dispatch to the Command Judge
        fetch('process_command.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                command: result.spoken,
                score: result.score,
                tier: result.tier
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.debug) console.table(data.debug);

            // Determine color based on tier
            let color = "#888";
            if (result.tier === 'Parfait') color = 'var(--primary-cyan)';
            else if (result.tier === 'Bien') color = 'var(--accent-gold)';

            // Update Persistent Feedback
            let statusHtml = `<span style="color:${color}; font-weight:bold;">&gt; ENTENDU: "${result.spoken.toUpperCase()}"</span> `;
            statusHtml += `<span style="color:#666; font-size:0.7rem;">[QUALITÉ: ${result.tier}]</span>`;
            
            if (data.reward_granted > 0) {
                statusHtml += ` <span style="color:var(--accent-gold); font-size:0.7rem;">[PRIME: +${data.reward_granted}G]</span>`;
            }
            feedbackArea.innerHTML = statusHtml;
            
            if (data.success && data.category === 'navigation') {
                // Execute the movement identified by the Judge
                handleMove(data.command);
            } else if (!data.success) {
                feedbackArea.innerHTML += ` <span style="color:#ff5555; font-size:0.7rem;">[REJETÉ]</span>`;
            } else {
                // For non-navigation commands, we refresh the UI to show the result
                initializeGame();
            }
        })
        .catch(err => console.error("[ENGINE] Command processing error:", err));
    });
}

/**
 * Renders a relative 7x7 grid centered on the player's map coordinates.
 */
function updateMiniMap(data) {
    const grid = document.getElementById('mini-map-grid');
    if (!grid || !data.mapNodes) return;

    grid.replaceChildren(); // Faster and cleaner than innerHTML = ''
    console.log(`[ENGINE DEBUG] Rendering map for Node ${data.nodeId}. Discovered nodes: ${data.mapNodes.length}`);
    
    // Find current node coords
    // Default to (0,0,0) if the current room isn't found or mapped yet
    const current = (data.mapNodes || []).find(n => n.nodeId == data.nodeId);
    if (!current) console.warn(`[ENGINE DEBUG] Current Node ${data.nodeId} NOT found in discovered mapNodes.`);
    
    // Use Number() to ensure we are doing math, not string concatenation
    const centerX = current ? Number(current.mapX) : 0;
    const centerY = current ? Number(current.mapY) : 0;
    const centerZ = current ? Number(current.mapZ) : 0;
    const range = 3; // Render a 7x7 grid centered on player

    // Update Z-Level Display
    const zDisplay = document.getElementById('z-level-display');
    if (zDisplay) {
        zDisplay.textContent = `Level: ${centerZ}`;
    }

    console.log(`[ENGINE DEBUG] Map Center: (${centerX}, ${centerY}, ${centerZ})`);

    // Filter discovered nodes to only those on the same vertical level
    const levelNodes = (data.mapNodes || []).filter(n => Number(n.mapZ) === centerZ);

    for (let y = centerY - range; y <= centerY + range; y++) {
        for (let x = centerX - range; x <= centerX + range; x++) {
            const cell = document.createElement('div');
            cell.className = 'map-cell';
            
            const node = levelNodes.find(n => Number(n.mapX) === x && Number(n.mapY) === y);
            if (node) {
                cell.classList.add('active');
                cell.setAttribute('data-title', node.title || 'Unknown');
                if (node.nodeId == data.nodeId) {
                    cell.classList.add('current');
                }
            }
            grid.appendChild(cell);
        }
    }
}