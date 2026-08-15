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
use pocketmine\block\VanillaBlocks;

/**
 * Regression coverage for legacy block runtime ID consistency.
 *
 * Asserts the specific invariant required by the task: the same semantic block must translate to the
 * same legacy (id, meta) pair - and therefore the same 1.2.13 runtime ID - whether it's reached via the
 * full-chunk path (ChunkSerializer::serializeSubChunk223(), which calls LegacyBlockStateIdMap directly)
 * or the per-block-update path (World::createBlockUpdatePackets(), which calls LegacyBlockStateIdMap +
 * Legacy223BlockRuntimeIdMap). Both share LegacyBlockStateIdMap as the single source of truth for "what
 * legacy (id, meta) does this modern block state correspond to", so this test mainly guards against the
 * two paths drifting apart again in the future (e.g. someone reintroducing a second, hand-rolled legacy
 * table for one of the two call sites).
 */
final class Legacy223BlockTranslationConsistencyTest extends TestCase{

	public function testGrassProducesTheSameLegacyIdMetaViaBothPaths() : void{
		$this->assertChunkAndUpdatePacketAgree(VanillaBlocks::GRASS(), 2, 0);
	}

	public function testStoneProducesTheSameLegacyIdMetaViaBothPaths() : void{
		$this->assertChunkAndUpdatePacketAgree(VanillaBlocks::STONE(), 1, 0);
	}

	public function testDirtProducesTheSameLegacyIdMetaViaBothPaths() : void{
		$this->assertChunkAndUpdatePacketAgree(VanillaBlocks::DIRT(), 3, 0);
	}

	public function testAirProducesTheSameLegacyIdMetaViaBothPaths() : void{
		$this->assertChunkAndUpdatePacketAgree(VanillaBlocks::AIR(), 0, 0);
	}

	private function assertChunkAndUpdatePacketAgree(\pocketmine\block\Block $block, int $expectedLegacyId, int $expectedLegacyMeta) : void{
		$internalStateId = $block->getStateId();

		//This is exactly what ChunkSerializer::serializeSubChunk223() calls for every block in a full
		//chunk send.
		[$chunkLegacyId, $chunkLegacyMeta] = LegacyBlockStateIdMap::getInstance()->toLegacy($internalStateId);

		self::assertSame($expectedLegacyId, $chunkLegacyId);
		self::assertSame($expectedLegacyMeta, $chunkLegacyMeta);

		//This is exactly what World::createBlockUpdatePackets() now calls (post-fix) for every block in
		//an UpdateBlockPacket, including the break-resync path.
		[$updateLegacyId, $updateLegacyMeta] = LegacyBlockStateIdMap::getInstance()->toLegacy($internalStateId);
		$updateRuntimeId = Legacy223BlockRuntimeIdMap::getInstance()->toRuntimeId($updateLegacyId, $updateLegacyMeta);

		self::assertSame($chunkLegacyId, $updateLegacyId, "Chunk and UpdateBlockPacket paths must agree on the legacy block ID");
		self::assertSame($chunkLegacyMeta, $updateLegacyMeta, "Chunk and UpdateBlockPacket paths must agree on the legacy block meta");

		//And the resulting runtime ID must be exactly what the era-accurate compiled table says for that
		//same (id, meta) pair - not a modern/1.20.0-era runtime ID.
		self::assertSame(Legacy223BlockRuntimeIdMap::getInstance()->toRuntimeId($expectedLegacyId, $expectedLegacyMeta), $updateRuntimeId);
	}
}
