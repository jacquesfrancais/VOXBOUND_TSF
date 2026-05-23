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
    refreshCommandManual();

    // GUARD: Do not overwrite the console if a Dialogue or Combat result is active
    if (isDialogueActive || (typeof isCombatActive !== 'undefined' && isCombatActive)) {
        return;
    }

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
                const name = (currentLanguage === 'fr') ? npc.npcNameFrench : npc.npcNameEnglish;

                if (Number(npc.isDead) === 1) {
                    // Render as a persistent lifeless object
                    li.style.color = "#666";
                    li.innerHTML = `• <span style="color:#ff5555">[CADAVRE]</span> ${name}`;
                } else {
                    const stats = `<span style="color:var(--accent-gold); font-size:0.7rem; opacity:0.8;"> [HP:${npc.currentHitPoints}/${npc.maxHitPoints} STR:${npc.strength} AGI:${npc.agility}]</span>`;
                    const attBtn = `<span style="cursor:pointer; color:var(--accent-gold);" onclick="startCombatTurn('J\\\'attaque', 'Bien', ${npc.npcId})">[ATTAQUER]</span>`;
                    const parlBtn = `<span style="cursor:help; color:var(--primary-cyan);" onclick="startDialogue(${npc.npcId}, '${npc.npcNameFrench.replace(/'/g, "\\'")}')">[PARLER]</span>`;
                    const greeting = (currentLanguage === 'fr') ? npc.greetingFrench : npc.greetingEnglish;
                    
                    li.innerHTML = `• ${attBtn} ${parlBtn} ${name}${stats} <span style="color:#666; font-style:italic;">"${greeting}"</span>`;
                }
                npcList.appendChild(li);
            });
        }
    }

    // 3.5 Update Party List
    const partySection = document.getElementById('party-section');
    const partyList = document.getElementById('party-list');
    if (partySection && partyList) {
        partyList.innerHTML = '';
        console.log(`[ENGINE DEBUG] Party members detected: ${data.party ? data.party.length : 0}`);

        if (data.party && Array.isArray(data.party) && data.party.length > 0) {
            partySection.style.display = 'block';
            data.party.forEach(member => {
                const li = document.createElement('li');
                li.style.marginBottom = "5px";
                const name = ((currentLanguage === 'fr') ? member.npcNameFrench : member.npcNameEnglish) || "Unknown Ally";
                // Displaying stats beside the name for a tactical overview
                li.innerHTML = `▶ <span style="color:var(--primary-cyan)">${name.toUpperCase()}</span> <span style="color:var(--accent-gold); font-size:0.7rem; opacity:0.8;">[HP:${member.currentHitPoints} STR:${member.strength} AGI:${member.agility}]</span>`;
                partyList.appendChild(li);
            });
        } else {
            partySection.style.display = 'none';
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
                // Visual feedback for weight to help testing
                const weightInfo = `<span style="color:#444; font-size:0.7rem;"> (${item.weight}kg)</span>`;
                li.innerHTML = `<span style="cursor:pointer;" onclick="takeItem(${item.instanceId})">[<span style="color:var(--accent-gold)">PRENDRE</span>]</span> ${name}${weightInfo}`;
                objectList.appendChild(li);
            });
        }
    }

    // 4.5 Update Inventory List & Weight
    const invList = document.getElementById('inventory-list');
    const weightDisplay = document.getElementById('total-weight');
    const maxWeightDisplay = document.getElementById('max-weight');
    
    if (invList && weightDisplay) {
        invList.innerHTML = '';
        if (data.inventory && data.inventory.length > 0) {
            data.inventory.forEach(item => {
                const li = document.createElement('li');
                const name = (currentLanguage === 'fr') ? item.nameFrench : item.nameEnglish;

                let actionBtns = '';
                if (item.itemType === 'Weapon' || item.itemType === 'Armor') {
                    const activeStyle = (item.isEquipped == 1) ? 'background:var(--accent-gold); color:#000;' : '';
                    actionBtns = `<button class="btn-outline" onclick="equipItem(${item.instanceId})" style="font-size:0.6rem; padding:1px 4px; margin-left:5px; ${activeStyle}">ÉQUIPER</button>`;
                } else if (item.itemType === 'Consumable') {
                    actionBtns = `<button class="btn-outline" onclick="useItem(${item.instanceId})" style="font-size:0.6rem; padding:1px 4px; margin-left:5px; border-color:var(--accent-gold); color:var(--accent-gold);">UTILISER</button>`;
                }

                li.innerHTML = `• ${name} <span style="color:#444; font-size:0.7rem;">(${item.weight}kg)</span> 
                                ${actionBtns}
                                <button class="btn-outline" onclick="dropItem(${item.instanceId})" style="font-size:0.6rem; padding:1px 4px; margin-left:5px; border-color:#888; color:#888;">POSER</button>`;
                invList.appendChild(li);
            });
        } else {
            invList.innerHTML = '<li style="font-style:italic; color:#444;">&gt; Vide...</li>';
        }
        
        const currentW = parseFloat(data.totalWeight || 0);
        weightDisplay.textContent = currentW.toFixed(2);
        weightDisplay.style.color = (currentW > parseFloat(maxWeightDisplay.textContent)) ? '#ff5555' : 'var(--accent-gold)';
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
    
    if (isDialogueActive && lastDialogueData) {
        renderDialogueNode(lastDialogueData, lastNpcId, lastNpcName);
    }

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
            if (window.VoxUI) window.VoxUI.playEffect('error');
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
            
            // Success chime for Parfait results
            if (result.tier === 'Parfait' && window.VoxUI) window.VoxUI.playEffect('success');

            if (data.success && data.category === 'navigation') {
                // Execute the movement identified by the Judge
                handleMove(data.command);
            } else if (data.success && data.category === 'combat') {
                // Combat Target Detection
                const spoken = result.spoken.toLowerCase();
                // Look for the NPC name in the room regardless of life status
                const roomNpcs = (lastRoomData && lastRoomData.npcs) ? lastRoomData.npcs : [];
                const target = roomNpcs.find(npc => spoken.includes(npc.npcNameFrench.toLowerCase()));

                if (target) {
                    if (typeof startCombatTurn === 'function') startCombatTurn(result.spoken, result.tier, target.npcId);
                } else {
                    feedbackArea.innerHTML += ` <span style="color:#ff5555; font-size:0.7rem;">[CIBLE NON TROUVÉE]</span>`;
                    if (window.VoxUI) window.VoxUI.playEffect('error');
                }
            } else if (data.success && data.category === 'interaction') {
                // STT Parsing for Item Interaction (Prenez/Take or Posez/Drop)
                // Normalize spoken text by removing trailing punctuation
                const spoken = result.spoken.toLowerCase().replace(/[.,\/#!$%\^&\*;:{}=\-_`~()]/g,"");
                
                if (spoken.includes("prenez") || spoken.includes("prendre")) {
                    const roomItems = (lastRoomData && lastRoomData.items) ? lastRoomData.items : [];
                    const itemToTake = roomItems.find(item => spoken.includes(item.nameFrench.toLowerCase()));
                    
                    if (itemToTake) {
                        takeItem(itemToTake.instanceId);
                    } else {
                        feedbackArea.innerHTML += ` <span style="color:#ff5555; font-size:0.7rem;">[OBJET NON TROUVÉ]</span>`;
                        if (window.VoxUI) window.VoxUI.playEffect('error');
                    }
                } else if (spoken.includes("posez") || spoken.includes("poser") || spoken.includes("posé")) {
                    const playerInv = (lastRoomData && lastRoomData.inventory) ? lastRoomData.inventory : [];
                    const itemToDrop = playerInv.find(item => spoken.includes(item.nameFrench.toLowerCase()));
                    
                    if (itemToDrop) {
                        dropItem(itemToDrop.instanceId);
                    } else {
                        feedbackArea.innerHTML += ` <span style="color:#ff5555; font-size:0.7rem;">[NON DANS L'INVENTAIRE]</span>`;
                        if (window.VoxUI) window.VoxUI.playEffect('error');
                    }
                } else if (spoken.includes("utiliser")) {
                    // Smart Matching: Ignore articles (le, la, l', les)
                    const playerInv = (lastRoomData && lastRoomData.inventory) ? lastRoomData.inventory : [];
                    const strip = (s) => s.toLowerCase().replace(/^(le |la |les |l')/i, "").trim();
                    
                    const spokenItem = strip(spoken.replace("utiliser", ""));
                    const target = playerInv.find(item => strip(item.nameFrench) === spokenItem);

                    if (target) useItem(target.instanceId);
                    else {
                        feedbackArea.innerHTML += ` <span style="color:#ff5555; font-size:0.7rem;">[NON TROUVÉ DANS L'INVENTAIRE]</span>`;
                        if (window.VoxUI) window.VoxUI.playEffect('error');
                    }
                } else {
                    feedbackArea.innerHTML += ` <span style="color:#ff5555; font-size:0.7rem;">[OBJET NON TROUVÉ]</span>`;
                    if (window.VoxUI) window.VoxUI.playEffect('error');
                }
            } else if (!data.success) {
                feedbackArea.innerHTML += ` <span style="color:#ff5555; font-size:0.7rem;">[REJETÉ]</span>`;
                if (window.VoxUI) window.VoxUI.playEffect('error');
            } else {
                // For non-navigation commands, we refresh the UI to show the result
                initializeGame();
            }
        })
        .catch(err => console.error("[ENGINE] Command processing error:", err));
    });
}

/**
 * Updates the "Command Manual" sidebar with context-appropriate French phrases.
 */
function refreshCommandManual() {
    const list = document.getElementById('command-manual-list');
    if (!list) return;
    
    list.innerHTML = '';
    console.log(`[ENGINE DEBUG] Refreshing Command Manual. Mode: ${isDialogueActive ? 'Dialogue' : 'Exploration'}`);

    const addHint = (text) => {
        const li = document.createElement('li');
        li.innerHTML = `<span style="opacity:0.5;">&gt;</span> <span style="cursor:pointer;" onclick="window.VoxUI.speakText('${text.replace(/'/g, "\\'")}')">"${text}"</span>`;
        li.style.marginBottom = "4px";
        list.appendChild(li);
    };

    if (isDialogueActive) {
        // Maintain system commands even during dialogue
        addHint("Quitter la conversation");
    } 

    // All other STT commands (Exploration) go here
    if (lastRoomData) {
        addHint("Regardez");
        addHint("Inventaire");
        
        if (lastRoomData.npcs && lastRoomData.npcs.length > 0) {
            lastRoomData.npcs.forEach(npc => addHint(`Parlez à ${npc.npcNameFrench}`));
        }

        if (lastRoomData.items && lastRoomData.items.length > 0) {
            lastRoomData.items.forEach(item => addHint(`Prenez ${item.nameFrench}`));
        }

        // Show drop hints if holding items
        if (lastRoomData.inventory && lastRoomData.inventory.length > 0) {
            lastRoomData.inventory.forEach(item => addHint(`Posez ${item.nameFrench}`));
        }

        if (lastRoomData.exits) {
            const directions = ['nord', 'sud', 'est', 'ouest', 'remonter', 'descendre', 'pénétrer', 'sortir'];
            
            // Mapping for consistent Imperative display in the manual
            const navLabels = {
                'nord': 'Nord',
                'sud': 'Sud',
                'est': 'Est',
                'ouest': 'Ouest',
                'remonter': 'Remontez',
                'descendre': 'Descendez',
                'pénétrer': 'Pénétrez',
                'sortir': 'Sortez'
            };

            directions.forEach(dir => {
                if (lastRoomData.exits[dir] > 0) addHint(navLabels[dir] || dir);
            });
        }
    }
}