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

namespace pocketmine\network\mcpe\serializer;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\block\tile\Campfire;
use pocketmine\block\tile\ItemFrame;
use pocketmine\block\tile\Jukebox;
use pocketmine\block\tile\Lectern;
use pocketmine\block\tile\Spawnable;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\data\bedrock\LegacyBiomeIdToStringIdMap;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\convert\LegacyBlockStateIdMap;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use function chr;
use function count;
use function ord;
use function str_repeat;

final class ChunkSerializer{
	private function __construct(){
		//NOOP
	}

	/**
	 * Returns the min/max subchunk index expected in the protocol.
	 * This has no relation to the world height supported by PM.
	 *
	 * @phpstan-param DimensionIds::* $dimensionId
	 * @return int[]
	 * @phpstan-return array{int, int}
	 */
	public static function getDimensionChunkBounds(int $dimensionId) : array{
		return match($dimensionId){
			DimensionIds::OVERWORLD => [-4, 19],
			DimensionIds::NETHER => [0, 7],
			DimensionIds::THE_END => [0, 15],
			default => throw new \InvalidArgumentException("Unknown dimension ID $dimensionId"),
		};
	}

	/**
	 * Returns the number of subchunks that will be sent from the given chunk.
	 * Chunks are sent in a stack, so every chunk below the top non-empty one must be sent.
	 *
	 * @phpstan-param DimensionIds::* $dimensionId
	 */
	public static function getSubChunkCount(Chunk $chunk, int $dimensionId) : int{
		//if the protocol world bounds ever exceed the PM supported bounds again in the future, we might need to
		//polyfill some stuff here
		[$minSubChunkIndex, $maxSubChunkIndex] = self::getDimensionChunkBounds($dimensionId);
		for($y = $maxSubChunkIndex, $count = $maxSubChunkIndex - $minSubChunkIndex + 1; $y >= $minSubChunkIndex; --$y, --$count){
			if($chunk->getSubChunk($y)->isEmptyFast()){
				continue;
			}
			return $count;
		}

		return 0;
	}

	/**
	 * @phpstan-param DimensionIds::* $dimensionId
	 * @return string[]
	 */
	public static function serializeSubChunks(Chunk $chunk, int $dimensionId, TypeConverter $typeConverter) : array{
		$stream = new ByteBufferWriter();
		$subChunks = [];

		$subChunkCount = self::getSubChunkCount($chunk, $dimensionId);
		$writtenCount = 0;

		[$minSubChunkIndex, ] = self::getDimensionChunkBounds($dimensionId);
		for($y = $minSubChunkIndex; $writtenCount < $subChunkCount; ++$y, ++$writtenCount){
			$stream->clear();
			self::serializeSubChunk($chunk->getSubChunk($y), $typeConverter->getBlockTranslator(), $stream, false);
			$subChunks[] = $stream->getData();
		}

		return $subChunks;
	}

	/**
	 * @phpstan-param DimensionIds::* $dimensionId
	 */
	public static function serializeFullChunk(Chunk $chunk, int $dimensionId, TypeConverter $typeConverter, ?string $tiles = null) : string{
		$stream = new ByteBufferWriter();

		foreach(self::serializeSubChunks($chunk, $dimensionId, $typeConverter) as $subChunk){
			$stream->writeByteArray($subChunk);
		}

		self::serializeBiomes($chunk, $dimensionId, $stream);
		self::serializeChunkData($chunk, $stream, $typeConverter, $tiles);

		return $stream->getData();
	}

	/**
	 * @phpstan-param DimensionIds::* $dimensionId
	 */
	public static function serializeBiomes(Chunk $chunk, int $dimensionId, ByteBufferWriter $stream) : void{
		[$minSubChunkIndex, $maxSubChunkIndex] = self::getDimensionChunkBounds($dimensionId);
		$biomeIdMap = LegacyBiomeIdToStringIdMap::getInstance();
		//all biomes must always be written :(
		for($y = $minSubChunkIndex; $y <= $maxSubChunkIndex; ++$y){
			self::serializeBiomePalette($chunk->getSubChunk($y)->getBiomeArray(), $biomeIdMap, $stream);
		}
	}

	public static function serializeBorderBlocks(ByteBufferWriter $stream) : void {
		Byte::writeUnsigned($stream, 0); //border block array count
		//Border block entry format: 1 byte (4 bits X, 4 bits Z). These are however useless since they crash the regular client.
	}

	public static function serializeChunkData(Chunk $chunk, ByteBufferWriter $stream, TypeConverter $typeConverter, ?string $tiles = null) : void{
		self::serializeBorderBlocks($stream);

		if($tiles !== null){
			$stream->writeByteArray($tiles);
		}else{
			$stream->writeByteArray(self::serializeTiles($chunk, $typeConverter));
		}
	}

	public static function serializeSubChunk(SubChunk $subChunk, BlockTranslator $blockTranslator, ByteBufferWriter $stream, bool $persistentBlockStates) : void{
		$layers = $subChunk->getBlockLayers();
		Byte::writeUnsigned($stream, 8); //version

		Byte::writeUnsigned($stream, count($layers));

		$blockStateDictionary = $blockTranslator->getBlockStateDictionary();

		foreach($layers as $blocks){
			$bitsPerBlock = $blocks->getBitsPerBlock();
			$words = $blocks->getWordArray();
			Byte::writeUnsigned($stream, ($bitsPerBlock << 1) | ($persistentBlockStates ? 0 : 1));
			$stream->writeByteArray($words);
			$palette = $blocks->getPalette();

			if($bitsPerBlock !== 0){
				VarInt::writeSignedInt($stream, count($palette)); //yes, this is intentionally zigzag
			}
			if($persistentBlockStates){
				$nbtSerializer = new NetworkNbtSerializer();
				foreach($palette as $p){
					//TODO: introduce a binary cache for this
					$state = $blockStateDictionary->generateDataFromStateId($blockTranslator->internalIdToNetworkId($p));
					if($state === null){
						$state = $blockTranslator->getFallbackStateData();
					}

					$stream->writeByteArray($nbtSerializer->write(new TreeRoot($state->toNbt())));
				}
			}else{
				//we would use writeSignedIntArray() here, but the gains of writing in batch are negated by the cost of
				//allocating a temporary array for the mapped palette IDs, especially for small palettes
				foreach($palette as $p){
					VarInt::writeSignedInt($stream, $blockTranslator->internalIdToNetworkId($p));
				}
			}
		}
	}

	private static function serializeBiomePalette(PalettedBlockArray $biomePalette, LegacyBiomeIdToStringIdMap $biomeIdMap, ByteBufferWriter $stream) : void{
		$biomePaletteBitsPerBlock = $biomePalette->getBitsPerBlock();
		Byte::writeUnsigned($stream, ($biomePaletteBitsPerBlock << 1) | 1); //the last bit is non-persistence (like for blocks), though it has no effect on biomes since they always use integer IDs
		$stream->writeByteArray($biomePalette->getWordArray());

		$biomePaletteArray = $biomePalette->getPalette();
		if($biomePaletteBitsPerBlock !== 0){
			VarInt::writeSignedInt($stream, count($biomePaletteArray));
		}

		foreach($biomePaletteArray as $p){
			//we would use writeSignedIntArray() here, but the gains of writing in batch are negated by the cost of
			//allocating a temporary array for the mapped palette IDs, especially for small palettes
			VarInt::writeSignedInt($stream, $biomeIdMap->legacyToString($p) !== null ? $p : BiomeIds::OCEAN);
		}
	}

	public static function serializeTiles(Chunk $chunk, TypeConverter $typeConverter) : string{
		$stream = new ByteBufferWriter();
		$skipItemTiles = $typeConverter->getProtocolId() === ProtocolInfo::PROTOCOL_1_2_13;
		foreach($chunk->getTiles() as $tile){
			if($tile instanceof Spawnable){
				if($skipItemTiles && ($tile instanceof Campfire || $tile instanceof ItemFrame || $tile instanceof Jukebox || $tile instanceof Lectern)){
					continue;
				}
				$stream->writeByteArray($tile->getSerializedSpawnCompound($typeConverter)->getEncodedNbt());
			}
		}

		return $stream->getData();
	}

	/**
	 * Legacy chunk wire format, as sent inside FullChunkDataPacket::data. Overworld only: fixed 128-block-tall
	 * world (8 subchunks, index 0-7 mapping 1:1 onto the low 8 indices of the modern -4..19 range).
	 *
	 * $tiles is the block-entity NBT blob, appended raw (no outer length prefix - self-delimited by end of
	 * packet). Expected to already be in "network NBT" (LE numbers, varint-prefixed strings, no root name) form,
	 * produced the same way as for modern protocols - see ChunkSerializer::serializeTiles(). Pass null only when
	 * no tiles exist in the chunk yet - the field is still written, just empty.
	 */
	public static function serializeFullChunk223(Chunk $chunk, ?string $tiles = null) : string{
		$stream = new ByteBufferWriter();

		$subChunkCount = self::getSubChunkCount223($chunk);
		Byte::writeUnsigned($stream, $subChunkCount);
		for($y = 0; $y < $subChunkCount; ++$y){
			self::serializeSubChunk223($chunk->getSubChunk($y), $stream);
		}

		self::serializeHeightMapAndBiomes223($chunk, $stream);

		Byte::writeUnsigned($stream, 0); //border block array count - legacy border blocks are unused/crash the client

		VarInt::writeUnsignedInt($stream, 0); //extraData count - not implemented, see class docblock

		$stream->writeByteArray($tiles ?? ""); //see method docblock for format/known limitation

		return $stream->getData();
	}

	/**
	 * Mirrors Chunk::getSubChunkSendCount() in the legacy tree: the number of subchunks from the bottom (index 0)
	 * up to and including the highest non-empty one. Fixed 8-subchunk (128 block) world height, overworld only.
	 */
	private static function getSubChunkCount223(Chunk $chunk) : int{
		for($y = 7; $y >= 0; --$y){
			if(!$chunk->getSubChunk($y)->isEmptyFast()){
				return $y + 1;
			}
		}
		return 0;
	}

	/**
	 * Mirrors SubChunk::networkSerialize() in the legacy tree: a single storage-version byte (always 0, meaning
	 * "flat array of ids+data", the only format 1.2.13 understands), then a flat 4096-byte block ID array and a
	 * packed 2048-byte (2 blocks/byte) metadata array. No light is sent - the legacy client computes it locally,
	 * same as the modern client does with block light.
	 */
	private static function serializeSubChunk223(SubChunk $subChunk, ByteBufferWriter $stream) : void{
		Byte::writeUnsigned($stream, 0); //storage version: legacy flat id+data array

		$ids = str_repeat("\x00", 4096);
		$data = str_repeat("\x00", 2048);

		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				for($y = 0; $y < 16; ++$y){
					$internalStateId = $subChunk->getBlockStateId($x, $y, $z);
					[$legacyId, $legacyMeta] = LegacyBlockStateIdMap::getInstance()->toLegacy($internalStateId);

					$idIndex = ($x << 8) | ($z << 4) | $y;
					$ids[$idIndex] = chr($legacyId);

					$dataIndex = ($x << 7) | ($z << 3) | ($y >> 1);
					$byte = ord($data[$dataIndex]);
					$data[$dataIndex] = ($y & 1) === 0 ?
						chr(($byte & 0xf0) | ($legacyMeta & 0x0f)) :
						chr((($legacyMeta & 0x0f) << 4) | ($byte & 0x0f));
				}
			}
		}

		$stream->writeByteArray($ids);
		$stream->writeByteArray($data);
	}

	/**
	 * Mirrors the heightmap/biomeIds section of Chunk::networkSerialize() in the legacy tree: 256 little-endian
	 * uint16 heightmap values followed by 256 raw biome ID bytes, both indexed (z<<4)|x - the opposite order from
	 * the block ID array above, which is (x<<8)|(z<<4)|y. Confirmed by legacy Chunk::getHeightMap()/getBiomeId().
	 *
	 * Modern Chunk::getHeightMap() can return values outside 1.2.13's 0-127 world height (e.g. if the world was
	 * generated with an extended height range); those are clamped here rather than left to overflow/wrap the
	 * unsigned 16-bit field. Biome IDs are taken from the single block at the heightmap surface for each column -
	 * legacy biomes were 2D (one per column) where modern PM's are 3D (one per block), so this is a lossy
	 * down-sample, not a 1:1 translation. The numeric biome IDs themselves need no translation: modern PM already
	 * stores them using the same legacy numeric scheme (confirmed by ChunkSerializer::serializeBiomePalette() above,
	 * which falls back to BiomeIds::OCEAN using exactly this legacy ID map when a stored ID isn't in it).
	 */
	private static function serializeHeightMapAndBiomes223(Chunk $chunk, ByteBufferWriter $stream) : void{
		$biomeIdMap = LegacyBiomeIdToStringIdMap::getInstance();

		$heights = [];
		$biomes = str_repeat("\x00", 256);
		for($z = 0; $z < 16; ++$z){
			for($x = 0; $x < 16; ++$x){
				$height = $chunk->getHeightMap($x, $z);
				$clampedHeight = $height < 0 ? 0 : ($height > 127 ? 127 : $height);
				$heights[] = $clampedHeight;

				$biomeId = $chunk->getBiomeId($x, $clampedHeight, $z);
				$biomes[($z << 4) | $x] = chr(($biomeIdMap->legacyToString($biomeId) !== null ? $biomeId : BiomeIds::OCEAN) & 0xff);
			}
		}

		//$heights above was built in the same (z<<4)|x order the 256 values need to be written in
		foreach($heights as $height){
			LE::writeUnsignedShort($stream, $height);
		}
		$stream->writeByteArray($biomes); //raw 256 bytes, no length prefix - writeByteArray() never adds one (see CommonTypes::putString for the convention: callers write their own VarInt length first when a field needs one)
	}
}
