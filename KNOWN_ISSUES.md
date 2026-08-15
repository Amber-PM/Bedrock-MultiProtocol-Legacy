# Known Issues: Legacy 1.2.13 (Protocol 223)

[← Back to the AmberPM README](README.md)

This document tracks reproducible, unresolved problems affecting Minecraft: Bedrock Edition **1.2.13** clients using wire protocol **223**. Modern-protocol clients are not affected by the issues listed here.

> [!WARNING]
> Protocol 223 support is experimental. Review these limitations before enabling it on a production server.

## 1. Crafting results are granted and then rolled back

**Affected version:** Minecraft: Bedrock Edition 1.2.13 (protocol 223)  
**Status:** Not fixed

### Symptom

A player has the correct ingredients and the recipe appears available. After selecting the output, the crafted item appears briefly and then disappears while the ingredients return to the inventory.

### Steps to reproduce

1. Connect with a 1.2.13 client.
2. Open a crafting table with the ingredients for a recipe, such as planks for a crafting table.
3. Select the result slot.
4. Observe that the result disappears and the ingredients are restored.

### Expected behavior

The ingredients should be consumed once and the crafted result should remain in the player's inventory.

### Observed behavior

The transaction appears to begin but is rolled back instead of being committed. Crafting through this legacy transaction path is currently unreliable.

---

## 2. Recipe-book category switching and search crash the client

**Affected version:** Minecraft: Bedrock Edition 1.2.13 (protocol 223)  
**Status:** Not fixed

### Symptom

Switching recipe-book categories or opening its search interface closes the client.

### Steps to reproduce: category switching

1. Connect with a 1.2.13 client.
2. Open the inventory crafting grid or a crafting table.
3. Open the recipe book.
4. Switch to another recipe category.

### Steps to reproduce: search

1. Connect with a 1.2.13 client.
2. Open the recipe book.
3. Select the search or magnifying-glass control.

### Expected behavior

The selected category or search interface should open normally.

### Observed behavior

The client closes. Avoid relying on recipe-book categories or search until the underlying incompatibility is resolved.

---

## 3. Modern players become invisible to legacy clients

**Affected version:** Minecraft: Bedrock Edition 1.2.13 (protocol 223)  
**Status:** Not fixed

### Symptom

A 1.2.13 player can initially see a modern-protocol player. After combat or command execution, the modern player disappears from the legacy client's view despite remaining connected and visible to modern clients.

### Steps to reproduce

1. Connect one 1.2.13 client and one modern client to the same server.
2. Confirm that the legacy client can see the modern player.
3. Have the legacy player hit the modern player, or execute a command from either client or the console.
4. Check the modern player's visibility from the legacy client.

### Expected behavior

The modern player should remain visible and tracked after combat and command-related metadata updates.

### Observed behavior

The modern player stops rendering for the 1.2.13 client while remaining connected. The root cause has not yet been verified.

## Reporting additional legacy issues

When reporting another legacy-protocol problem, include:

1. the affected client version and protocol;
2. exact reproduction steps;
3. expected and observed behavior;
4. whether the client disconnects or closes;
5. relevant server logs or packet captures; and
6. the current investigation status.

Do not state a root cause unless it has been verified. Reproducible observations are more useful than an untested diagnosis.
