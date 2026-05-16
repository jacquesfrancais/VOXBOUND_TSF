/**
 * VOXBOUND: navigation.js
 * The Manager for world traversal and movement adjudication calls.
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
            // Call orchestrator to refresh world
            if (typeof initializeGame === 'function') initializeGame();
        } else {
            console.warn(`[ENGINE DEBUG] Move blocked: ${data.error}`);
            if (data.debug) console.table(data.debug);
            alert(data.error); 
        }
    })
    .catch(err => console.error("[ENGINE] Movement communication error:", err));
}