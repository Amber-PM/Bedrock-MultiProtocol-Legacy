<?php

/*
 *
 *    _              _               
 *   / \   _ __ ___ | |__   ___ _ __ 
 *  / _ \ | '_ ` _ \| '_ \ / _ \ '__|
 * / ___ \| | | | | | |_) |  __/ |   
 * /_/   \_\_| |_| |_|_.__/ \___|_|   
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author AmberPM Team
 * @link https://github.com/Amber-PM/Amber
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\convert;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use function is_array;
use function is_int;
use function json_decode;

/**
 * Resolves the actual network "block runtime ID" a genuine MCPE 1.2.13 (protocol 223) client expects on
 * the wire for anything that ISN'T a full chunk send.
 *
 * Full chunks (see ChunkSerializer::serializeFullChunk223()/serializeSubChunk223()) use a flat legacy
 * id+meta byte array and never need a runtime ID at all - that part needs no special handling here.
 *
 * Everything else that identifies a block on the wire for this protocol - UpdateBlockPacket
 * (World::createBlockUpdatePackets()) being the main one - uses a single VarInt "block runtime ID" field,
 * exactly like every later protocol. But 1.2.13 predates the modern block-palette system (introduced with
 * the 1.16 flattening): there is no StartGame-time palette handshake, and the client has a small (~2070
 * entry), fixed, compiled-in table mapping legacy (id, meta) pairs to sequential runtime IDs it assigned
 * itself at startup, in a fixed built-in order.
 *
 * The modern per-protocol BlockTranslator (loaded via BlockTranslator::PATHS[PROTOCOL_1_2_13], which - for
 * lack of any actual 1.2.13 block data in the bundled bedrock-data vendor package - points at the
 * "-1.20.0" canonical block state table) can't be used directly for this: it produces network IDs in the
 * thousands-to-tens-of-thousands range, from a completely different palette than the one a 1.2.13 client
 * has compiled in. Feeding that straight to the client would make it index its own ~2070-entry table with
 * an out-of-range or unrelated number, resolving to the wrong block entirely. Since
 * World::createBlockUpdatePackets() also backs the break-resync path
 * (InGamePacketHandler::syncBlocksNearby()), an unresolved mismatch here would corrupt both the target
 * block and its resynced neighbours - visible as an oversized or irregular gap after breaking a single
 * block, since the neighbours were never actually removed server-side, just sent runtime IDs the legacy
 * client couldn't resolve to anything solid. This class exists to prevent that by using the client's real
 * table instead.
 *
 * The table backing this class (resources/vanilla/block_legacy_223_runtimeid_table.json) is the actual
 * compiled-in runtime ID table used by legacy PocketMine-MP for this MCPE generation, contemporary with
 * MCPE 1.2.13's original release. It is not a formula - the client's runtime IDs were assigned in
 * registration order and are not derivable from (id, meta) by any simple bit-shift, so a real table is
 * required to match a real client.
 *
 * That resource file legitimately contains 48 entries whose "id" is 257-265 (identifiable via the table's
 * "name" field: minecraft:prismarine_stairs, minecraft:dark_prismarine_stairs,
 * minecraft:prismarine_bricks_stairs, and the six stripped-log variants). These are real 1.2.13-era blocks
 * that were assigned an *extended* legacy numeric ID beyond the original 0-255 byte range - they are not
 * corruption, not a mismatched/foreign table, and not item IDs. However, this class's own public contract
 * already limits itself to a single legacy id byte (toRuntimeId() masks its input with & 0xff, and every
 * other consumer in this codebase - see LegacyBlockStateIdMap::__construct(), which silently skips
 * legacyNumericId > 255 for the same reason - treats that as the boundary of what this fixed 0-255/0-15
 * layout can represent). An extended-id entry can therefore never be queried through this class's API
 * regardless of whether it's loaded, so those entries are excluded from the usable map (not fatal - see
 * the loader below) while every other entry (2022 of the 2070) is preserved unchanged.
 */
final class Legacy223BlockRuntimeIdMap{
	use SingletonTrait;

	private const RESOURCE_FILE = "vanilla/block_legacy_223_runtimeid_table.json";

	/** minecraft:info_update, the same "unknown/unmapped block" placeholder used by the modern BlockTranslator fallback (BlockTypeNames::INFO_UPDATE), in its 1.2.13-era legacy (id, meta) form. */
	private const FALLBACK_LEGACY_ID = 248;
	private const FALLBACK_LEGACY_META = 0;

	/**
	 * @var int[]
	 * @phpstan-var array<int, int> indexed by (legacyId << 4) | legacyMeta
	 */
	private array $legacyIdMetaToRuntimeId = [];

	private int $fallbackRuntimeId;

	/**
	 * Number of entries excluded from {@see self::$legacyIdMetaToRuntimeId} because their legacy id fell
	 * outside the 0-255 byte range this map's API can represent (see the class docblock).
	 * Exposed only for test/diagnostic purposes; does not affect lookups.
	 */
	private int $excludedExtendedIdEntryCount = 0;

	private function __construct(){
		$path = \pocketmine\RESOURCE_PATH . self::RESOURCE_FILE;
		$raw = Filesystem::fileGetContents($path);

		$table = json_decode($raw, true);
		if(!is_array($table)){
			throw new AssumptionFailedError("Invalid format of $path, expected a JSON array");
		}

		foreach($table as $entry){
			if(
				!is_array($entry) ||
				!isset($entry["id"], $entry["data"], $entry["runtimeID"]) ||
				!is_int($entry["id"]) || !is_int($entry["data"]) || !is_int($entry["runtimeID"])
			){
				//this is a structural/type problem with the entry itself (missing/wrong-typed fields),
				//not a legitimately-out-of-range value - still fatal, since the loader can't safely
				//interpret the entry at all.
				throw new AssumptionFailedError("$path entries must be {id: int, data: int, runtimeID: int} objects");
			}
			if($entry["id"] < 0 || $entry["data"] < 0 || $entry["data"] > 15){
				//a negative id, or a meta outside the 4-bit legacy range, is not a known real shape for
				//this resource (unlike id > 255, which the class docblock explains is legitimate) -
				//still treat this as a corrupt/mismatched table.
				throw new AssumptionFailedError("$path contains an invalid legacy id/meta pair: {$entry["id"]}:{$entry["data"]}");
			}
			if($entry["id"] > 255){
				//legitimate extended-id block (e.g. prismarine stairs, stripped logs) that this map's
				//fixed single-byte-id API cannot represent - see the class docblock. Skip it without
				//discarding the rest of the (valid) table, and without throwing.
				$this->excludedExtendedIdEntryCount++;
				continue;
			}

			//first entry for a given (id, meta) wins, in case of accidental duplicates in the source table.
			$this->legacyIdMetaToRuntimeId[($entry["id"] << 4) | $entry["data"]] ??= $entry["runtimeID"];
		}

		$this->fallbackRuntimeId = $this->legacyIdMetaToRuntimeId[(self::FALLBACK_LEGACY_ID << 4) | self::FALLBACK_LEGACY_META] ??
			throw new AssumptionFailedError("$path should always contain an entry for the info_update fallback block ($this::FALLBACK_LEGACY_ID:" . self::FALLBACK_LEGACY_META . ")");
	}

	/**
	 * @internal test/diagnostic use only.
	 */
	public function getExcludedExtendedIdEntryCount() : int{ return $this->excludedExtendedIdEntryCount; }

	/**
	 * Returns the network runtime ID a real 1.2.13 client's compiled-in table associates with the given
	 * legacy (id, meta) pair. Falls back to the same meta-0 variant of the block (covers blocks whose
	 * upgrade path produced a meta value outside what 1.2.13 actually had), then to minecraft:info_update
	 * if the id itself is entirely unknown to this table - the same "unmapped block" placeholder policy
	 * BlockTranslator::$fallbackStateId uses for modern protocols. Never throws: an unmapped block should
	 * degrade to a visibly-wrong-but-stable placeholder, not break the packet.
	 */
	public function toRuntimeId(int $legacyId, int $legacyMeta) : int{
		$legacyId &= 0xff;
		$legacyMeta &= 0xf;

		return $this->legacyIdMetaToRuntimeId[($legacyId << 4) | $legacyMeta]
			?? $this->legacyIdMetaToRuntimeId[$legacyId << 4]
			?? $this->fallbackRuntimeId;
	}

	public function getFallbackRuntimeId() : int{ return $this->fallbackRuntimeId; }

	/**
	 * Shared resolution point for "internal block state ID -> network block runtime ID", covering both
	 * protocol 223 and every modern protocol, so every consumer that needs this gets the correct legacy
	 * runtime ID instead of a raw modern BlockTranslator::internalIdToNetworkId() result.
	 *
	 * BlockParticle, BlockSound, TerrainParticle and FallingBlock all route through here rather than
	 * calling the modern per-protocol BlockTranslator directly and unconditionally, which - for protocol
	 * 223 - would feed a genuine 1.2.13 client's small (~2070-entry) hardcoded runtime ID table an
	 * out-of-range index from the modern "-1.20.0" palette (thousands-to-tens-of-thousands range),
	 * resolving to an unrelated block from its table. That's the same class of mismatch
	 * World::createBlockUpdatePackets() guards against for block updates (see that method's doc comment),
	 * just surfacing through particles, sounds, and falling-block rendering instead.
	 */
	public static function resolveNetworkBlockRuntimeId(BlockTranslator $modernTranslator, int $protocolId, int $internalStateId) : int{
		if($protocolId === ProtocolInfo::PROTOCOL_1_2_13){
			[$legacyId, $legacyMeta] = LegacyBlockStateIdMap::getInstance()->toLegacy($internalStateId);
			return self::getInstance()->toRuntimeId($legacyId, $legacyMeta);
		}
		return $modernTranslator->internalIdToNetworkId($internalStateId);
	}
}
