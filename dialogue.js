/**
 * VOXBOUND: dialogue.js
 * The Manager for branching social interactions and practicing speech.
 */

let isDialogueActive = false;
let lastDialogueData = null; // Cache for current dialogue node
let lastNpcId = null;        // Cache for active NPC
let lastNpcName = null;      // Cache for active NPC name

/**
 * Transitions the UI to Dialogue Mode.
 */
function startDialogue(npcId, npcName) {
    isDialogueActive = true;
    lastNpcId = npcId;
    lastNpcName = npcName;

    console.log(`[DIALOGUE] Starting engagement with NPC: ${npcId}`);
    
    fetch(`get_dialogue.php?npcId=${npcId}`)
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                isDialogueActive = false;
                return;
            }
            renderDialogueNode(data, npcId, npcName);
        });
}

function renderDialogueNode(data, npcId, npcName) {
    lastDialogueData = data;
    if (typeof refreshCommandManual === 'function') refreshCommandManual();
    
    const roomDesc = document.getElementById('room-description');
    const objectList = document.getElementById('object-list');
    
    // Reskin Console for engagement
    if (window.VoxUI) window.VoxUI.setUIMode('combat'); 

    const npcText = (currentLanguage === 'fr') ? data.fr : data.en;

    roomDesc.innerHTML = `<strong style="color:var(--primary-cyan)">${npcName.toUpperCase()}:</strong><br><span id="npc-statement">${npcText}</span>`;
    if (window.VoxUI && currentLanguage === 'fr') window.VoxUI.speakText(data.fr, 'npc-statement', 0.75);

    // Render Options in the Actions area
    objectList.innerHTML = '<div class="title-label" style="font-size:0.7rem;">VOTRE RÉPONSE :</div>';
    data.options.forEach((opt, index) => {
        const div = document.createElement('div');
        div.style.marginBottom = "10px";
        const optText = (currentLanguage === 'fr') ? opt.fr : opt.en;

        div.innerHTML = `
            <button class="btn-outline" onclick="window.VoxUI.speakText('${opt.fr.replace(/'/g, "\\'")}', 'opt-text-${index}', 0.6)" style="font-size:0.6rem; padding:2px 5px;">ÉCOUTER</button>
            <button class="btn-neon" onclick="handleDialogueSpeech('${opt.fr.replace(/'/g, "\\'")}', '${opt.next}', ${npcId}, '${npcName}')" style="padding:2px 5px; margin-left:5px;">🎤</button>
            <span id="opt-text-${index}" style="margin-left:10px; font-size:0.85rem;">${optText}</span>
        `;
        objectList.appendChild(div);
    });

    // Add Quit Button
    const quitDiv = document.createElement('div');
    quitDiv.style.marginTop = "20px";
    quitDiv.innerHTML = `<button class="btn-outline" onclick="endDialogue(${npcId})" style="width:100%; border-color:#ff5555; color:#ff5555;">QUITTER LA CONVERSATION</button>`;
    objectList.appendChild(quitDiv);
}

function handleDialogueSpeech(expectedText, nextNodeId, npcId, npcName) {
    const feedbackArea = document.getElementById('command-feedback');
    feedbackArea.innerHTML = `<span style="color:var(--primary-cyan); opacity:0.7;">&gt; [SYSTÈME] ÉCOUTE ACTIVE...</span>`;

    window.VoxSpeech.captureCommand(expectedText, (result) => {
        if (result.error) {
            feedbackArea.innerHTML = `<span style="color:#ff5555;">&gt; ERREUR VOCALE: ${result.error}</span>`;
            if (window.VoxUI) window.VoxUI.playEffect('error');
            return;
        }

        fetch('process_dialogue.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                npcId: npcId,
                expected: expectedText,
                spoken: result.spoken,
                score: result.score,
                tier: result.tier,
                next: nextNodeId
            })
        })
        .then(res => res.json())
        .then(data => {
            const isCorrect = (result.tier === 'Parfait' || result.tier === 'Bien');
            const qualityColor = isCorrect ? '#00ff00' : '#ff5555';
            const transcriptionColor = 'var(--primary-cyan)';

            let statusHtml = `<span style="color:${transcriptionColor}; font-weight:bold;">&gt; ENTENDU: "${result.spoken.toUpperCase()}"</span> `;
            statusHtml += `<span style="color:${qualityColor}; font-size:0.7rem;">[QUALITÉ: ${result.tier.toUpperCase()} (${(result.score * 100).toFixed(0)}%)]</span>`;
            feedbackArea.innerHTML = statusHtml;

            if (result.tier === 'Parfait' && window.VoxUI) window.VoxUI.playEffect('success');

            if (data.error) {
                console.warn(`[DIALOGUE] Speech rejected: ${data.error}`);
                if (window.VoxUI) window.VoxUI.playEffect('error');
                return; 
            }

            if (data.finished) {
                endDialogue(npcId);
            } else {
                renderDialogueNode(data, npcId, npcName);
            }
        });
    });
}

function endDialogue(npcId) {
    isDialogueActive = false;
    if (window.VoxUI) window.VoxUI.setUIMode('exploration');
    if (typeof initializeGame === 'function') initializeGame(); 
}