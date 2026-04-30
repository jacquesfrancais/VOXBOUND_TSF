<?php
/**
 * voice_calibration.php
 * VOXBOUND: The Spoken Frontier
 * Voice Interface Calibration (Stateless)
 */

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['character_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VOXBOUND – Voice Calibration</title>

    <link rel="stylesheet" href="adventure-base.css">

    <style>
        .calibration-container {
            max-width: 800px;
            margin: 60px auto;
        }

        .phrase-box {
            font-size: 1.4rem;
            color: var(--primary-cyan);
            margin: 30px 0;
            letter-spacing: 1px;
        }

        .feedback {
            margin-top: 20px;
            font-size: 0.9rem;
            color: var(--accent-gold);
        }

        .system-log {
            font-size: 0.75rem;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<nav style="display:flex; justify-content:center; padding:20px; background:#161b22; border-bottom:1px solid #333;">
    <div style="color:var(--primary-cyan); font-weight:bold; letter-spacing:2px;">
        VOXBOUND VOICE INTERFACE
    </div>
</nav>

<div class="console-box calibration-container">

    <div class="title-label">VOICE INTERFACE CALIBRATION</div>

    <p class="system-log">
        This calibration verifies microphone input and pronunciation accuracy.
        No progress or penalties are applied.
    </p>

    <div class="phrase-box" id="phraseDisplay">
        Bonjour
    </div>

    <div>
        <button class="btn-neon" onclick="playPhrase()">PLAY PHRASE</button>
        <button class="btn-outline" onclick="listen()">SPEAK</button>
    </div>

    <div class="feedback" id="feedback"></div>

    <div class="system-log" id="systemLog"></div>

    <hr style="margin:40px 0; border-color:#222;">

    <div style="display:flex; gap:20px;">
        <a href="adventure.php" class="btn-outline" style="flex:1; text-align:center;">
            SKIP CALIBRATION
        </a>
        <a href="adventure.php" class="btn-neon" style="flex:1; text-align:center;">
            ENTER WORLD
        </a>
    </div>

</div>

<script>
/* ----------------------------------------
   Calibration Data (Simple & Safe)
----------------------------------------- */
const phrases = [
    "Bonjour",
    "Oui",
    "Je suis prêt",
    "Aller au nord",
    "Prendre l'épée",
    "Ouvrir la porte",
    "Parler au marchand",
    "Utiliser la potion",
    "j'attaque le monstre",
];

let currentIndex = 0;

/* ----------------------------------------
   Web Speech API Checks
----------------------------------------- */
const SpeechRecognition =
    window.SpeechRecognition || window.webkitSpeechRecognition;

if (!SpeechRecognition) {
    document.getElementById('systemLog').textContent =
        "Speech Recognition not supported in this browser.";
}

/* ----------------------------------------
   TTS: Play Phrase
----------------------------------------- */
function playPhrase() {
    const utterance = new SpeechSynthesisUtterance(phrases[currentIndex]);
    utterance.lang = "fr-FR";
    utterance.rate = 0.7;
    utterance.pitch = 1.05;
    speechSynthesis.speak(utterance);

    document.getElementById('systemLog').textContent =
        "TTS output active...";
}

/* ----------------------------------------
   STT: Listen & Evaluate
----------------------------------------- */
function listen() {
    const recognition = new SpeechRecognition();
    recognition.lang = "fr-FR";
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;

    document.getElementById('systemLog').textContent =
        "Listening for input...";

    recognition.start();

    recognition.onresult = function (event) {
        const spoken = event.results[0][0].transcript;
        evaluate(spoken);
    };

    recognition.onerror = function () {
        document.getElementById('feedback').textContent =
            "Input not recognized. Please try again.";
    };
}

/* ----------------------------------------
   Simple Similarity Evaluation
----------------------------------------- */
function evaluate(spoken) {
    const target = phrases[currentIndex].toLowerCase();
    const input = spoken.toLowerCase();

    let score = similarity(target, input);

    let result;
    if (score > 0.8) result = "✅ Parfait";
    else if (score > 0.6) result = "✅ Bien";
    else result = "⚠️ Try again";

    document.getElementById('feedback').textContent =
        `Heard: "${spoken}" — ${result}`;

    if (score > 0.6 && currentIndex < phrases.length - 1) {
        currentIndex++;
        document.getElementById('phraseDisplay').textContent =
            phrases[currentIndex];
    }
}

/* ----------------------------------------
   Levenshtein Similarity
----------------------------------------- */
function similarity(a, b) {
    if (!a.length || !b.length) return 0;
    let longer = a.length > b.length ? a : b;
    let shorter = a.length > b.length ? b : a;
    let longerLength = longer.length;

    return (longerLength - editDistance(longer, shorter)) / longerLength;
}

function editDistance(a, b) {
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
</script>

</body>
</html>