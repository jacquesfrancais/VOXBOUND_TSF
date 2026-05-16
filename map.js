/**
 * VOXBOUND: map.js
 * The Manager for spatial rendering and the Mini-Map grid.
 */

function updateMiniMap(data) {
    const grid = document.getElementById('mini-map-grid');
    if (!grid || !data.mapNodes) return;

    grid.replaceChildren(); 
    console.log(`[ENGINE DEBUG] Rendering map for Node ${data.nodeId}. Discovered nodes: ${data.mapNodes.length}`);
    
    const current = (data.mapNodes || []).find(n => n.nodeId == data.nodeId);
    if (!current) console.warn(`[ENGINE DEBUG] Current Node ${data.nodeId} NOT found in discovered mapNodes.`);
    
    const centerX = current ? Number(current.mapX) : 0;
    const centerY = current ? Number(current.mapY) : 0;
    const centerZ = current ? Number(current.mapZ) : 0;
    const range = 3; 

    const zDisplay = document.getElementById('z-level-display');
    if (zDisplay) {
        zDisplay.textContent = `Level: ${centerZ}`;
    }

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