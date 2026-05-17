<section class="console-box" id="game-console">
    <div class="title-label" id="location-label">
        Location: <span id="location-id-display"><?= htmlspecialchars($locTitle) ?></span>
        <button class="btn-outline" onclick="replayRoomDescription()" style="font-size: 0.6rem; margin-left: 10px; padding: 2px 5px; vertical-align: middle;">READ ALOUD</button>
        <button class="btn-outline" onclick="toggleLanguage()" style="font-size: 0.6rem; margin-left: 5px; padding: 2px 5px; vertical-align: middle;">TRANSLATE</button>
        <button id="btn-voice-command" class="btn-neon" onclick="startVoiceCommand()" style="font-size: 0.6rem; margin-left: 5px; padding: 2px 5px; vertical-align: middle;">ÉNONCER LES COMMANDES</button>
    </div>
    
    <!-- PERSISTENT FEEDBACK AREA -->
    <div id="command-feedback" style="min-height: 25px; font-size: 0.8rem; margin-bottom: 15px; font-family: var(--font-mono); border-bottom: 1px solid #1a1a1a; padding-bottom: 5px;"></div>

    <div id="room-description" style="color: var(--accent-gold); line-height: 1.6; margin-bottom: 20px;">
        &gt; INITIALIZING ENVIRONMENT...<br>
        The terminal flickers to life. The path winds through the dark woods.
    </div>

    <div id="room-entities" style="border-top: 1px solid #222; padding-top: 15px; font-size: 0.85rem;">
        <div class="title-label" style="font-size: 0.7rem;">Detected Entities:</div>
        <ul id="npc-list" style="list-style: none; padding: 0; color: var(--primary-cyan);">
            <!-- NPCs will be injected here -->
        </ul>
        <ul id="object-list" style="list-style: none; padding: 0; color: #888;">
            <!-- Objects will be injected here -->
        </ul>
    </div>
</section>