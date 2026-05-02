/**
 * VOXBOUND: speech.js
 * The "Ear" (Manager) - Captures voice, calculates similarity, and dispatches to Engine.
 */

class VoxBoundSpeech {
    constructor() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        
        this.isSupported = !!SpeechRecognition;
        this.recognition = this.isSupported ? new SpeechRecognition() : null;
        this.isListening = false;

        if (this.isSupported) {
            this.recognition.lang = 'fr-FR';
            this.recognition.interimResults = false;
            this.recognition.maxAlternatives = 1;
            console.log("[SPEECH DEBUG] Web Speech API Initialized. Language: fr-FR");
        } else {
            console.error("[SPEECH DEBUG] Web Speech API not supported in this browser.");
        }
    }

    /**
     * Listens for a single command and compares it against a target phrase.
     * @param {string} targetPhrase - The French phrase we are expecting.
     * @param {function} callback - Function to handle the result {spoken, score, tier}.
     */
    captureCommand(targetPhrase, callback) {
        if (!this.isSupported) return;
        if (this.isListening) {
            console.warn("[SPEECH DEBUG] Already listening. Ignoring request.");
            return;
        }

        this.isListening = true;
        console.log(`[SPEECH DEBUG] Starting listener. Expecting: "${targetPhrase}"`);

        this.recognition.start();

        this.recognition.onresult = (event) => {
            const spoken = event.results[0][0].transcript;
            const confidence = event.results[0][0].confidence;
            console.log(`[SPEECH DEBUG] Raw Result: "${spoken}" (Confidence: ${confidence.toFixed(2)})`);

            // If no targetPhrase is provided, we skip similarity and treat as a successful capture
            const score = targetPhrase ? this.calculateSimilarity(targetPhrase.toLowerCase(), spoken.toLowerCase()) : 1.0;
            
            let tier = "Pas compris";
            if (!targetPhrase || score >= 0.95) tier = "Parfait";
            else if (score >= 0.75) tier = "Bien";

            console.log(`[SPEECH DEBUG] Similarity Score: ${score.toFixed(2)} | Tier: ${tier}`);
            
            this.isListening = false;
            callback({ spoken, score, tier });
        };

        this.recognition.onerror = (event) => {
            console.error(`[SPEECH DEBUG] Recognition Error: ${event.error}`);
            this.isListening = false;
            callback({ error: event.error });
        };

        this.recognition.onend = () => {
            this.isListening = false;
            console.log("[SPEECH DEBUG] Recognition service disconnected.");
        };
    }

    /**
     * Levenshtein-based similarity calculation
     */
    calculateSimilarity(a, b) {
        if (!a.length || !b.length) return 0;
        const longer = a.length > b.length ? a : b;
        const shorter = a.length > b.length ? b : a;
        const longerLength = longer.length;

        return (longerLength - this.editDistance(longer, shorter)) / longerLength;
    }

    editDistance(a, b) {
        const dp = Array(b.length + 1).fill(null).map(() =>
            Array(a.length + 1).fill(null)
        );

        for (let i = 0; i <= a.length; i++) dp[0][i] = i;
        for (let j = 0; j <= b.length; j++) dp[j][0] = j;

        for (let j = 1; j <= b.length; j++) {
            for (let i = 1; i <= a.length; i++) {
                const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                dp[j][i] = Math.min(
                    dp[j][i - 1] + 1,
                    dp[j - 1][i] + 1,
                    dp[j - 1][i - 1] + cost
                );
            }
        }
        return dp[b.length][a.length];
    }
}

// Global instance for the game
window.VoxSpeech = new VoxBoundSpeech();

/*
### How this aligns with your project:
1.  **Manager Role**: It strictly handles the "Experience" (capturing and math) while leaving the "Truth" (what happens when you succeed) to your PHP backend workers.
2.  **Debugging**: I've added `[SPEECH DEBUG]` tags to all major events. You can open your browser console (F12) to see exactly why a command might be failing or how high the similarity score was.
3.  **Tiers**: It uses the 0.75 (Bien) and 0.95 (Parfait) thresholds defined in your **Combat & Interaction** rules.
4.  **Global Access**: By attaching it to `window.VoxSpeech`, you can call it from anywhere in your game (like your navigation buttons or combat loop).

Would you like to integrate this into `adventure.php` now so your movement buttons can be triggered by voice?

<!--
[PROMPT_SUGGESTION]How do I update adventure.php to include speech.js and use voice for the NORD button?[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]Can you help me implement the 'Reward Economy' logic in PHP to pay the player when they get a 'Parfait' score?[/PROMPT_SUGGESTION]
*/