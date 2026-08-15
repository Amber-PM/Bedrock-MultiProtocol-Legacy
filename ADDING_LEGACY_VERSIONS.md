# Adding Support for Another Legacy Version

[← Back to the AmberPM README](README.md)

AmberPM already supports one legacy client generation: Minecraft: Bedrock Edition **1.2.13**, wire protocol **223**. Its currently known limitations are documented in [`KNOWN_ISSUES.md`](KNOWN_ISSUES.md).

This contributor guide explains how AmberPM's legacy compatibility layer is organized and what is required to add another legacy version using the same architecture.

> [!IMPORTANT]
> Before writing compatibility code, obtain an accurate reference for the target version: a real client, packet captures, and/or a trustworthy server implementation from the same era. Do not infer packet layouts, numeric IDs, metadata flags, or runtime-ID tables from modern data. Legacy formats often depend on fixed tables and historical assignment order.

## 1. Determine whether the version needs legacy handling

Not every older version requires a dedicated legacy implementation. Ordinary version differences—such as different block sets, item sets, or schema IDs—are already handled through per-protocol data tables:

- `BlockTranslator::PATHS` in `src/network/mcpe/convert/BlockTranslator.php`
- `ItemTypeDictionaryFromDataHelper::PATHS` in `src/network/mcpe/convert/ItemTypeDictionaryFromDataHelper.php`
- `ItemTranslator::getItemSchemaId()` in `src/network/mcpe/convert/ItemTranslator.php`

If the target version only needs another snapshot of those datasets, add the corresponding entries there. That is the normal multi-version path.

A dedicated legacy path is necessary when the target version predates wire-level concepts assumed by the modern protocol. Protocol 223, for example, predates or differs from modern behavior in areas such as:

- the item type dictionary handshake;
- the block-palette and runtime-ID handshake;
- `ItemStackRequestPacket`;
- `CreativeContentPacket`;
- `AvailableActorIdentifiersPacket`;
- `BiomeDefinitionListPacket`;
- modern crafting stations and furnace variants;
- the current `EntityMetadataFlags` bit layout; and
- the current `AvailableCommandsPacket` layout.

Document which differences actually apply to the new version before implementing them. Do not assume every protocol older than the current one behaves exactly like protocol 223.

## 2. Register the protocol

Add the protocol constant to:

```text
vendor/vapebw/bedrock-protocol/src/ProtocolInfo.php
```

For example:

```php
public const PROTOCOL_1_X_Y = 000;
```

Then add the constant to `ProtocolInfo::ACCEPTED_PROTOCOL`.

The protocol package is normally generated upstream. A direct change under `vendor/` may be overwritten when dependencies are reinstalled or regenerated. Keep the change in a maintained fork or dependency patch if the project has such a workflow, and verify it after every dependency update.

## 3. Define the version's capabilities

Create a compatibility class such as:

```text
src/multiversion/Compatibility/LegacyXXXCompatibility.php
```

Use `Legacy223Compatibility.php` as a structural reference. Its `CapabilityRegistry` should contain only the features genuinely supported by the target client. Available capability names are defined in:

```text
src/multiversion/Capability/Capability.php
```

For each omitted capability, add a short comment identifying where the fallback or protocol-specific behavior is implemented. Register the new class in:

```text
src/multiversion/Compatibility/CompatibilityFactory.php
```

Prefer capability checks:

```php
$compatibility->supports(Capability::SOME_FEATURE)
```

over scattered comparisons against a numeric protocol ID. Use a direct protocol check only when behavior is unique to one exact wire format and cannot be expressed meaningfully as a reusable capability.

## 4. Add item and block mappings

### Legacy item IDs

If the client predates the item type dictionary handshake:

1. Add a curated mapping file:

   ```text
   resources/vanilla/item_legacy_XXX_id_map.json
   ```

   It should map string identifiers to the numeric item IDs expected by that client.

2. Add a dictionary implementation modeled on:

   ```text
   Legacy223ItemTypeDictionary.php
   ```

3. Select it in `TypeConverter::__construct()` for the new protocol.

4. Add the protocol's item schema case to `ItemTranslator::getItemSchemaId()`.

### Legacy block runtime IDs

If the client predates the block-palette/runtime-ID handshake:

1. Add a curated table:

   ```text
   resources/vanilla/block_legacy_XXX_runtimeid_table.json
   ```

   The table should map the legacy block ID and metadata pair to the runtime ID expected by the client.

2. Add a map implementation modeled on:

   ```text
   Legacy223BlockRuntimeIdMap.php
   ```

3. Route every network block-runtime-ID lookup through the legacy map for the target protocol. Current consumers include:

   - `World::createBlockUpdatePackets()`
   - `BlockSound`
   - `BlockParticle`
   - `TerrainParticle`
   - `FallingBlock`

Do not generate a legacy runtime-ID table with a simple formula. These IDs are commonly determined by the client's compiled-in registration order. Extract or verify the table against a real implementation from the target version.

## 5. Implement chunk serialization

If the target client uses a different chunk or subchunk format, add dedicated methods to:

```text
src/network/mcpe/serializer/ChunkSerializer.php
```

Use clear names such as:

```php
serializeFullChunkXXX()
serializeSubChunkXXX()
```

Select them for the target protocol at the same point where protocol 223 selects its serializer. The existing `223()` methods are a useful structural example, but they are not proof that another legacy version has the same layout.

Verify at least:

- subchunk version and count;
- block storage layout;
- ID and metadata packing;
- biome data;
- height-map data;
- block entities; and
- empty-subchunk behavior.

A chunk that appears to load correctly can still contain a misaligned section that causes crashes when the player moves, places a block, or opens a container.

## 6. Handle session and packet differences

Review `src/network/mcpe/NetworkSession.php` for behavior that must vary by protocol.

### Compression

Determine whether the client expects the modern raw-deflate format or the legacy zlib wrapper represented by `ZlibCompressorLegacy`.

### Packet-ID collisions

Review the existing legacy guards:

- `LEGACY_PROTOCOL_WIRE_COLLISION_CLASSES`
- `LEGACY_PROTOCOL_MAX_KNOWN_PACKET_ID`
- `LEGACY_PROTOCOL_HIGH_FREQUENCY_CLASSES`

Build protocol-specific lists when necessary. Do not automatically reuse protocol 223's limits: a packet ID may be unused in one version and assigned to a completely different packet in another. Sending a modern packet under a colliding ID can cause malformed decoding or an immediate client crash.

### Feature fallbacks

If the target client predates scoreboard packets, determine whether it also needs the existing scoreboard-to-action-bar fallback. Apply the same review to any modern packet suppressed or replaced for protocol 223.

## 7. Support inventory transactions and crafting

If the target version predates `ItemStackRequestPacket`, its inventory actions must use the legacy transaction path.

Use `InGamePacketHandler::handleLegacy223NormalTransaction()` as a structural reference. The legacy handler converts `NetworkInventoryAction` objects into core `InventoryAction` objects instead of passing the request to the modern item-stack-request executor.

Validate more than simple slot movement. Test:

- moving and splitting stacks;
- dropping and picking up items;
- armor and off-hand slots, if supported;
- containers;
- creative inventory;
- crafting-grid input and output;
- furnace input, fuel, and output; and
- transaction rejection and resynchronization.

In `src/network/mcpe/cache/CraftingDataCache.php`, exclude every recipe type, station, and furnace variant that the client does not understand. Follow the intent of the existing `ProtocolInfo::PROTOCOL_1_2_13` branches, but base the new conditions on the target version's actual feature set.

Unsupported recipe data is not harmless: malformed or unknown entries can break the recipe book or crash the client while changing categories or using search.

## 8. Remap entity metadata and commands

### Entity metadata

If `EntityMetadataFlags` bit positions differ from the modern protocol, add a bidirectional remap in:

```text
vendor/vapebw/bedrock-protocol/src/serializer/CommonTypes.php
```

Update both:

- `CommonTypes::putEntityMetadata()`
- `CommonTypes::getEntityMetadata()`

Gate the remap to the applicable protocol or capability. A one-way remap may make entities look correct initially while still corrupting metadata received from the client.

Test entity visibility again after metadata changes caused by combat, commands, effects, sneaking, sprinting, riding, and respawning. Protocol 223 has shown that an entity can spawn correctly and disappear only after a later metadata update.

### Available commands

If `AvailableCommandsPacket` differs in flag widths, enum encoding, argument type IDs, or parameter layout, add a matching encode/decode branch. Use the protocol 223 implementation as a guide, then verify the resulting bytes against the target client or a known-good packet capture.

Test commands containing:

- literals and aliases;
- optional parameters;
- enums;
- selectors;
- positions;
- messages or raw text; and
- multiple overloads.

## 9. Add automated tests

Mirror the legacy-223 coverage for the new protocol. Relevant tests currently include:

- `tests/phpunit/network/mcpe/convert/Legacy223BlockRuntimeIdMapTest.php`
- `tests/phpunit/network/mcpe/convert/Legacy223ItemTypeDictionaryTest.php`
- `tests/phpunit/network/mcpe/convert/Legacy223BlockTranslationConsistencyTest.php`
- `tests/phpunit/network/mcpe/protocol/EntityMetadataLegacy223Test.php`
- `tests/phpunit/network/mcpe/protocol/AvailableCommandsPacketLegacyTest.php`
- `tests/phpunit/multiversion/Compatibility/CompatibilityFactoryTest.php`

At minimum, add coverage for:

- compatibility-factory selection;
- declared capabilities;
- item ID round trips;
- block runtime-ID lookup and consistency;
- representative chunk serialization fixtures;
- entity metadata remapping in both directions;
- command packet encoding and decoding;
- packet suppression and collision guards; and
- inventory transaction conversion.

Keep fixtures tied to documented sources or captured client behavior. A test that only reproduces assumptions made by the implementation will preserve the same mistake rather than detect it.

## 10. Test with a real client

Automated tests are necessary but not sufficient. Before declaring support complete, connect a real client running the target version and exercise a repeatable test matrix.

### Connection and world

- Join, spawn, move between chunks, teleport, die, and respawn.
- Visit terrain containing different block states, biomes, block entities, and lighting conditions.
- Place and break representative blocks.

### Items and inventory

- Receive, move, split, drop, pick up, consume, equip, and use items.
- Open containers and verify that rejected actions resynchronize cleanly.
- Test creative inventory if the client supports it.

### Crafting

- Craft in the player grid and at every station available in that version.
- Browse every recipe-book category.
- Use recipe-book search.
- Verify that ingredients are consumed exactly once and the result persists.

### Entities and gameplay

- Spawn players, mobs, items, projectiles, and falling blocks.
- Trigger metadata changes through combat, commands, effects, and movement states.
- Verify cross-version visibility from both the legacy and modern clients.

### Commands and UI

- Open command suggestions and execute representative commands.
- Exercise scoreboards or their fallback.
- Open forms, inventories, and other supported UI paths.

Watch both server logs and the client process. Treat silent disconnects, rolled-back transactions, invisible entities, corrupted chunks, and client closures as failures even if the server reports no exception.

## 11. Document known issues

Add every reproducible unresolved problem to [`KNOWN_ISSUES.md`](KNOWN_ISSUES.md). Include:

1. the affected client version and protocol;
2. a concise symptom;
3. exact reproduction steps;
4. expected behavior;
5. observed behavior;
6. impact, including whether the client disconnects or closes; and
7. current status.

Do not claim a root cause unless it has been verified. An honest description of observable behavior is more useful than a confident but untested diagnosis.

Suggested format:

```md
## Short issue title

**Affected version:** Minecraft: Bedrock Edition X.Y.Z (protocol XXX)  
**Status:** Not fixed

### Steps to reproduce

1. ...
2. ...
3. ...

### Expected behavior

...

### Observed behavior

...
```

## Completion checklist

A legacy version is ready to advertise as supported only when all applicable items below are complete:

- [ ] Protocol constant registered and accepted.
- [ ] Compatibility class and capabilities added.
- [ ] Item mapping verified against the target client.
- [ ] Block runtime-ID table verified against the target client.
- [ ] Chunk and subchunk formats verified.
- [ ] Compression behavior confirmed.
- [ ] Packet-ID collisions and unsupported packets handled.
- [ ] Inventory transactions tested, including rejection and resync.
- [ ] Crafting data filtered to supported recipes and stations.
- [ ] Entity metadata remapped in both directions where required.
- [ ] Available commands encoded and decoded correctly.
- [ ] Automated tests added and passing.
- [ ] Real-client test matrix completed.
- [ ] Cross-version interaction tested with at least one modern client.
- [ ] Remaining problems recorded in `KNOWN_ISSUES.md`.
- [ ] Any changes under `vendor/` preserved through the dependency workflow.

Legacy support is complete when the client behaves reliably in normal gameplay—not merely when it can connect.