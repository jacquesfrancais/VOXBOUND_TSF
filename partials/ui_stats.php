<div class="console-box" style="background: var(--primary-cyan); color: black;">
    <strong id="char-name" style="text-transform: uppercase;"><?= htmlspecialchars($character['characterName']) ?></strong>
    <div style="font-size: 0.85rem; margin-top: 5px;">
        HP: <span id="stat-hp"><?= (int)$character['hitPoints'] ?> / <?= (int)$character['maxHitPoints'] ?></span><br>
        STR: <span id="stat-str"><?= (int)$character['strength'] ?></span> | AGI: <span id="stat-agi"><?= (int)$character['agility'] ?></span><br>
        GOLD: <span id="stat-gold"><?= number_format($character['gold'], 2) ?></span>
    </div>
</div>