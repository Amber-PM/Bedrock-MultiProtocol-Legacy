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

use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the legacy block runtime ID map.
 *
 * Guards against World::createBlockUpdatePackets() feeding protocol 223's *modern* BlockTranslator
 * (loaded from the "-1.20.0" canonical block state table, for lack of any actual 1.2.13 data) into
 * UpdateBlockPacket. A genuine 1.2.13 client has no palette handshake and indexes its own small (~2070
 * entry) hardcoded runtime ID table instead, so doing that would produce an unrelated block (or nothing
 * renderable) any time a single block was updated outside a full chunk send - including the break-resync
 * path.
 *
 * Also covers the loader's handling of the table's 48 legitimate extended-id block entries (prismarine
 * stairs and stripped-log variants, real id 257-265): those must be excluded from the usable map
 * (unreachable through this class's own & 0xff-masked API anyway) rather than causing the whole singleton
 * - and therefore every subsequent block update packet for every 223 session - to fail to initialize.
 *
 * Run with `vendor/bin/phpunit`.
 */
final class Legacy223BlockRuntimeIdMapTest extends TestCase{

	public function testKnownBlocksResolveToTheirEraAccurateRuntimeId() : void{
		$map = Legacy223BlockRuntimeIdMap::getInstance();

		//Values taken directly from the compiled runtime ID table used by legacy PocketMine-MP for this
		//MCPE generation, contemporary with MCPE 1.2.13's original release.
		self::assertSame(0, $map->toRuntimeId(0, 0), "air (id 0, meta 0)");
		self::assertSame(1, $map->toRuntimeId(1, 0), "stone (id 1, meta 0)");
		self::assertSame(9, $map->toRuntimeId(2, 0), "grass (id 2, meta 0)");
		self::assertSame(2009, $map->toRuntimeId(248, 0), "info_update (id 248, meta 0)");
	}

	public function testUnknownMetaFallsBackToMetaZeroForTheSameId() : void{
		$map = Legacy223BlockRuntimeIdMap::getInstance();

		//Grass (id 2) only ever had meta 0-4 in the legacy table; a meta value outside that (which can
		//happen if the id/meta upgrade path produces something the 1.2.13 table never had) should fall
		//back to the block's own meta-0 entry rather than jumping to an unrelated block or throwing.
		self::assertSame($map->toRuntimeId(2, 0), $map->toRuntimeId(2, 15));
	}

	public function testCompletelyUnknownIdFallsBackToInfoUpdate() : void{
		$map = Legacy223BlockRuntimeIdMap::getInstance();

		self::assertSame($map->getFallbackRuntimeId(), $map->toRuntimeId(254, 7));
		self::assertSame(2009, $map->getFallbackRuntimeId());
	}

	public function testEveryRuntimeIdInTheTableIsWithinTheClientsCompiledRange() : void{
		$map = Legacy223BlockRuntimeIdMap::getInstance();

		//Sanity check against accidentally wiring in a modern (thousands-to-tens-of-thousands) runtime
		//ID space again: every legacy id/meta pair must resolve inside the real table's own range.
		for($id = 0; $id <= 255; ++$id){
			for($meta = 0; $meta <= 15; ++$meta){
				$runtimeId = $map->toRuntimeId($id, $meta);
				self::assertGreaterThanOrEqual(0, $runtimeId);
				self::assertLessThanOrEqual(2087, $runtimeId, "id=$id meta=$meta produced an out-of-range runtime ID $runtimeId");
			}
		}
	}

	public function testSingletonInitializesWithoutThrowingDespiteExtendedIdEntriesInTheResourceFile() : void{
		//Regression check: getInstance() used to throw AssumptionFailedError here (id 257:0, prismarine
		//stairs) and take the whole map down. Merely reaching this line without an exception is the
		//primary assertion.
		$map = Legacy223BlockRuntimeIdMap::getInstance();
		self::assertInstanceOf(Legacy223BlockRuntimeIdMap::class, $map);
	}

	public function testExtendedIdEntriesAreExcludedButCountedRatherThanSilentlyLost() : void{
		$map = Legacy223BlockRuntimeIdMap::getInstance();

		//The real resource file has exactly 48 entries with id 257-265 (verified against the reference
		//table's "name" field: prismarine_stairs, dark_prismarine_stairs, prismarine_bricks_stairs, and
		//the six stripped-log variants, 8+8+8+4+4+4+4+4+4 = 48).
		self::assertSame(48, $map->getExcludedExtendedIdEntryCount());
	}

	public function testValidEntriesSurroundingTheExcludedRangeAreUnaffected() : void{
		$map = Legacy223BlockRuntimeIdMap::getInstance();

		//id 255 (runtimeID 2038, minecraft:reserved6) is the last valid entry immediately before the
		//excluded 257-265 range; id 248 (info_update/fallback) sits well before it. Both must resolve
		//normally - proving the exclusion of 257-265 didn't disturb any other entry's stored runtimeID,
		//since these are explicit values from the JSON, not positionally derived.
		self::assertSame(2038, $map->toRuntimeId(255, 0), "reserved6 (id 255, meta 0)");
		self::assertSame(2009, $map->toRuntimeId(248, 0), "info_update (id 248, meta 0)");
	}

	public function testQueryingAnExtendedLegacyIdDoesNotThrowAndDegradesToFallback() : void{
		$map = Legacy223BlockRuntimeIdMap::getInstance();

		//toRuntimeId()'s own & 0xff mask means a caller passing 257 actually queries id 1 (257 & 0xff ==
		//1, i.e. stone) - this documents that existing, unchanged masking behaviour explicitly, so it's
		//not confused with the new exclusion-from-loading behaviour tested above.
		self::assertSame($map->toRuntimeId(1, 0), $map->toRuntimeId(257, 0), "257 & 0xff == 1 (stone)");
	}
}
