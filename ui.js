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
     * Reads French text with Karaoke-style highlighting.
     * @param {string} text - The text to speak.
     * @param {string} elementId - The ID of the HTML element containing the text.
     * @param {number} rate - The speed of speech (default 0.7 for beginners).
     */
    speakText(text, elementId = null, rate = 0.7) {
        if (!this.isSpeechEnabled || !this.synth) return;

        const cleanText = text.replace(/<\/?[^>]+(>|$)/g, "");
        this.synth.cancel();

        const utterance = new SpeechSynthesisUtterance(cleanText);
        utterance.lang = 'fr-FR';
        utterance.rate = rate; 
        utterance.pitch = 1.0;

        if (elementId) {
            const container = document.getElementById(elementId);
            const words = cleanText.split(/\s+/);
            container.innerHTML = words.map((w, i) => `<span id="word-${elementId}-${i}">${w}</span>`).join(' ');

            utterance.onboundary = (event) => {
                if (event.name === 'word') {
                    const charIndex = event.charIndex;
                    let currentPos = 0;
                    words.forEach((w, i) => {
                        const span = document.getElementById(`word-${elementId}-${i}`);
                        if (currentPos <= charIndex && charIndex < currentPos + w.length + 1) {
                            span.classList.add('word-highlight');
                        } else {
                            span.classList.remove('word-highlight');
                        }
                        currentPos += w.length + 1;
                    });
                }
            };
        }

        this.synth.speak(utterance);
    }

    /**
     * Generates a short synthesized audio effect using Web Audio API.
     * @param {string} type - The type of sound to play (e.g., 'error').
     */
    playEffect(type) {
        try {
            const context = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = context.createOscillator();
            const gain = context.createGain();

            oscillator.connect(gain);
            gain.connect(context.destination);

            if (type === 'error') {
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(150, context.currentTime); // Low frequency
                gain.gain.setValueAtTime(0.1, context.currentTime); // Subtle volume
                gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.2); // Fade out
                oscillator.start();
                oscillator.stop(context.currentTime + 0.2);
            } else if (type === 'success') {
                oscillator.type = 'sine';
                // A pleasant rising two-tone chime (C5 to G5)
                oscillator.frequency.setValueAtTime(523.25, context.currentTime); // C5
                oscillator.frequency.setValueAtTime(783.99, context.currentTime + 0.1); // G5
                gain.gain.setValueAtTime(0.1, context.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.4);
                oscillator.start();
                oscillator.stop(context.currentTime + 0.4);
            }
        } catch (e) {
            console.warn("[UI] AudioContext failed to initialize:", e);
        }
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