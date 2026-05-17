<div class="console-box" style="text-align:center;">
    <div class="title-label">Movement</div>
    
    <!-- PRIMARY COMPASS: N, S, E, W -->
    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 5px; margin-bottom: 20px; align-items: center;">
        <div></div><button id="btn-nord" class="btn-neon" onclick="handleMove('nord')" style="padding: 5px; font-size: 0.75rem;">NORD</button><div></div>
        <button id="btn-ouest" class="btn-neon" onclick="handleMove('ouest')" style="padding: 5px; font-size: 0.75rem;">OUEST</button>
        <div style="color:var(--primary-cyan); font-size: 1.2rem;">◈</div>
        <button id="btn-est" class="btn-neon" onclick="handleMove('est')" style="padding: 5px; font-size: 0.75rem;">EST</button>
        <div></div><button id="btn-sud" class="btn-neon" onclick="handleMove('sud')" style="padding: 5px; font-size: 0.75rem;">SUD</button><div></div>
    </div>

    <!-- UTILITY AXIS: REMONTER/DESCENDRE & PÉNÉTRER/SORTIR -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
        <div style="display:flex; flex-direction:column; gap:5px; border: 1px solid #222; padding: 5px;">
            <button id="btn-remonter" class="btn-neon" onclick="handleMove('remonter')" style="font-size: 0.7rem; padding: 5px;">REMONTER</button>
            <div style="color: var(--accent-gold); font-size: 0.6rem;">↕</div>
            <button id="btn-descendre" class="btn-neon" onclick="handleMove('descendre')" style="font-size: 0.7rem; padding: 5px;">DESCENDRE</button>
        </div>
        <div style="display:flex; flex-direction:column; gap:5px; border: 1px solid #222; padding: 5px;">
            <button id="btn-pénétrer" class="btn-neon" onclick="handleMove('pénétrer')" style="font-size: 0.7rem; padding: 5px;">PÉNÉTRER</button>
            <div style="color: var(--accent-gold); font-size: 0.6rem;">↔</div>
            <button id="btn-sortir" class="btn-neon" onclick="handleMove('sortir')" style="font-size: 0.7rem; padding: 5px;">SORTIR</button>
        </div>
    </div>
</div>