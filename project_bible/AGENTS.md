# VOXBOUND: The Spoken Frontier - Project Bible (v2.7)

## 1. System Philosophy & Architecture
*   **The "Manager vs. Judge" Model**:
    *   **Frontend (Managers)**: `engine.js`, `ui.js`, `speech.js`, `combat.js`, `dialogue.js`. These handle the "Experience" (Capturing voice, Animations, UI transitions, and Audio output).
    *   **Backend (Judges)**: PHP workers (e.g., `process_command.php`, `process_dialogue.php`, `move_player.php`). These handle the "Truth" (Database persistence, linguistic reward math, and state validation).
*   **Identity-Centric Scoping**: All world interactions are scoped to a `character_id`. This allows for isolated single-player instances where the environment (dead NPCs, moved items) persists specifically for that user.

## 2. The Linguistic Engine
*   **The Ears (STT)**: Uses the Web Speech API (`speech.js`) with a Levenshtein-based similarity algorithm to compare spoken input against target phrases.
    *   **Parfait (≥ 0.95)**: Critical Success. Grants 0.20 Gold and Max Damage.
    *   **Bien (≥ 0.75)**: Success. Grants 0.10 Gold and Half Damage.
    *   **Pas compris (< 0.75)**: Failure. No reward, missed turn in combat.
*   **The Mouth (TTS)**: Uses `window.VoxUI.speakText` (`ui.js`) to provide French audio modeling for all narrative text and NPC dialogue, featuring "Karaoke-style" word highlighting.
*   **Reward Economy**: Every successful spoken interaction (Navigation, Combat, or Dialogue) provides a micro-reward in Gold to incentivize vocal practice.

## 3. Data & State Persistence
*   **Static Library vs. Live State**:
    *   **Library Tables**: `Locations`, `Npcs`, `ItemLibrary`. These define the "blueprints" of the world.
    *   **State Tables**: `Character_NPC_State`, `Character_Room_State`, `ItemInstances`. These track the specific progress and modifications made by a character.
*   **The Spawner**: `spawner_worker.php` creates a unique world instance upon character creation by copying library blueprints into state tables for that specific `character_id`.
*   **World Authority**: All damage math, gold updates, and inventory moves are handled via PHP to prevent client-side manipulation.

## 4. The Trigger System (Scriptable Dialogue)
Dialogue is now a scriptable event system rather than just static text.
*   **JSON Definition**: Dialogues (e.g., `Buddy.json`) contain `nodes` with `options`.
*   **Triggers**: Options can include a `trigger` key (e.g., `"trigger": "recruit_npc"`).
*   **Adjudication**: `process_dialogue.php` inspects the JSON upon a successful speech match and executes logic (e.g., setting `isFollowing = 1` in `Character_NPC_State`).

## 5. UI State Machine (The Arena Overlay)
The interface is dynamic and re-skins itself based on the interaction mode via `ui.js`:
*   **Exploration Mode**: Standard console with directional navigation and room descriptions (Cyan theme).
*   **Engagement Mode (Dialogue/Combat)**: The `window.VoxUI.setUIMode('combat')` function triggers a "reskin" (Gold theme).
    *   Navigation buttons are locked/hidden.
    *   The "Action List" transforms into a "Response List" (Dialogue) or "Combat Maneuvers" (Combat).
    *   The "Quit" button provides a safe exit back to Exploration.

## 6. Content Pipeline & Maintenance
*   **The Editor**: `editor.php` provides a secure, admin-only CRUD interface for the library tables.
*   **Hardened TSV Import**:
    *   **BOM Stripping**: Automatically handles UTF-8 Byte Order Marks to prevent database corruption.
    *   **Atomic Transactions**: Imports are wrapped in PDO transactions; an error in one row rolls back the entire file.
    *   **ID Persistence**: Uses `ON DUPLICATE KEY UPDATE` to allow spreadsheets to act as the primary source of truth without duplicating records.

## 7. Combat Mechanics
*   **The Turn-Based Loop**:
    *   **Player Turn**: Uses STT grading to determine damage.
    *   **NPC/Ally Turn**: Calculated by the server based on `strength` and `agility`.
*   **Recruitment & Party**: NPCs with `isFollowing = 1` are added to the `playerParty` during combat calculations, contributing their stats to the encounter.
*   **Victory & Death**:
    *   **Victory**: `handle_victory.php` processes loot drops and attribute boosts.
    *   **Death**: `reset_game.php` enforces a 10% Gold penalty and respawns the player at the last `isCheckpoint` node.

## 8. Onboarding (The Entry Experience)
*   **Guided Voice Preview**: A mandatory (but skippable for veterans) intermediate step between character creation and gameplay (`voice_calibration.php`).
*   **Calibration**: Allows players to test microphone sensitivity and browser compatibility by repeating simple French phrases (e.g., "Bonjour") without game consequences.

## 9. File Directory & Duties

### Core Logic (The Judges)
| File | Duty |
| :--- | :--- |
| `db_config.php` | PDO Database connection and configuration. |
| `init.php` | Session management, global constants, and sanitization. |
| `process_command.php` | Intent identification (Navigation vs. Combat vs. Social). |
| `process_dialogue.php` | Branching dialogue logic and trigger execution. |
| `process_combat.php` | Adjudicates HP changes and combat rounds. |
| `move_player.php` | Adjudicates traversal and follower movement. |
| `spawner_worker.php` | Initializes new character world instances. |
| `reset_game.php` | Handles resurrection penalties and hard resets. |

### Client Interfaces (The Managers)
| File | Duty |
| :--- | :--- |
| `engine.js` | Main game loop orchestrator and UI state synchronizer. |
| `speech.js` | Web Speech API wrapper and similarity math. |
| `ui.js` | TTS, animations, sound effects, and UI reskinning. |
| `dialogue.js` | Dialogue rendering and response capturing. |
| `navigation.js` | Movement UI and traversal dispatching. |
| `combat.js` | Combat turn execution and round rendering. |

### Data & Assets
| File | Duty |
| :--- | :--- |
| `schema.sql` | The source of truth for database structure. |
| `dialogues/*.json` | Scripted NPC interaction trees. |

## 10. Future Vision: Multiplayer Authority
The architecture is prepared for **Shared Session Mode**. By shifting world state scoping from `characterId` to a `sessionId`, multiple characters can share NPC states and item persistence within the same authoritative world instance.