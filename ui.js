/**
 * VOXBOUND: ui.js
 * The "Mouth" (Manager) - Handles Text-to-Speech (TTS) and UI Experience.
 */

class VoxBoundUI {
    constructor() {
        this.synth = window.speechSynthesis;
        this.isSpeechEnabled = true; // Toggle for "Read Aloud" feature
    }

    /**
     * Reads French text aloud using the browser's Speech Synthesis API.
     * Fulfills Bible Section 3: Audio Support.
     * @param {string} text - The French text to be spoken.
     */
    speakText(text) {
        if (!this.isSpeechEnabled || !this.synth) return;

        // Strip HTML tags (like <br>) so the voice engine only reads plain text
        const cleanText = text.replace(/<\/?[^>]+(>|$)/g, "");

        // Cancel any ongoing speech to prevent overlapping
        this.synth.cancel();

        const utterance = new SpeechSynthesisUtterance(cleanText);
        utterance.lang = 'fr-FR';
        utterance.rate = 0.85; // Slightly slower for learning clarity
        utterance.pitch = 1.0;

        console.log(`[UI DEBUG] Speaking: "${cleanText}"`);
        this.synth.speak(utterance);
    }

    /**
     * Reskins the console for different game states.
     * Fulfills Bible Section 5: The Arena Overlay.
     * @param {string} mode - 'exploration' or 'combat'
     */
    setUIMode(mode) {
        const consoleBox = document.getElementById('game-console');
        if (!consoleBox) return;

        if (mode === 'combat') {
            consoleBox.style.borderColor = 'var(--accent-gold)';
            console.log("[UI DEBUG] Switching to COMBAT ARENA mode.");
        } else {
            consoleBox.style.borderColor = 'var(--primary-cyan)';
            console.log("[UI DEBUG] Returning to EXPLORATION mode.");
        }
    }
}

// Initialize Global UI Manager
window.VoxUI = new VoxBoundUI();