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

use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\data\bedrock\item\downgrade\ItemIdMetaDowngrader;
use pocketmine\data\bedrock\item\ItemDeserializer;
use pocketmine\data\bedrock\item\ItemSerializer;
use pocketmine\data\bedrock\item\ItemTypeDeserializeException;
use pocketmine\data\bedrock\item\ItemTypeSerializeException;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\format\io\GlobalItemDataHandlers;

/**
 * This class handles translation between network item ID+metadata to PocketMine-MP internal ID+metadata and vice versa.
 */
final class ItemTranslator{
	public const NO_BLOCK_RUNTIME_ID = 0; //this is technically a valid block runtime ID, but is used to represent "no block" (derp mojang)

	/**
	 * Dedicated (fake, out-of-band) schema ID for protocol 223 (MCPE 1.2.13).
	 *
	 * Every real schema file under pocketmine/bedrock-item-upgrade-schema is numbered starting at 0001,
	 * so passing 0 here to ItemIdMetaDowngrader means "apply every known id/meta downgrade schema",
	 * walking modern item string IDs all the way back to the oldest known string ID for that item -
	 * which is what actually corresponds to entries in Legacy223ItemTypeDictionary.
	 *
	 * PROTOCOL_1_2_13 must not share schema ID 111 with PROTOCOL_1_20_0 (see the match() below). 111 is
	 * the correct baseline for a *modern* string-ID-based client like 1.20.0, but it's meaningless for
	 * 1.2.13, which never had string IDs on the wire at all - it uses raw legacy numeric IDs. Reusing 111
	 * would hand legacy clients items keyed by 1.20.0-era numeric dictionary IDs instead of their own.
	 */
	private const LEGACY_223_ITEM_SCHEMA_ID = 0;

	public function __construct(
		private ItemTypeDictionary $itemTypeDictionary,
		private BlockStateDictionary $blockStateDictionary,
		private ItemSerializer $itemSerializer,
		private ItemDeserializer $itemDeserializer,
		private BlockItemIdMap $blockItemIdMap,
		private ItemIdMetaDowngrader $itemDataDowngrader,
		/**
		 * True only for the protocol-223 (1.2.13) ItemTranslator instance. Changes how fromNetworkId()
		 * resolves block-item state data - see the property's use below for why this can't be inferred
		 * from $networkBlockRuntimeId alone.
		 */
		private bool $isLegacy223IdSpace = false,
	){}

	/**
	 * @return int[]|null
	 * @phpstan-return array{int, int, ?int}|null
	 */
	public function toNetworkIdQuiet(Item $item) : ?array{
		try{
			return $this->toNetworkId($item);
		}catch(ItemTypeSerializeException){
			return null;
		}
	}

	/**
	 * @return int[]
	 * @phpstan-return array{int, int, ?int}
	 *
	 * @throws ItemTypeSerializeException
	 */
	public function toNetworkId(Item $item) : array{
		//TODO: we should probably come up with a cache for this

		$itemData = $this->itemSerializer->serializeType($item, $this->itemDataDowngrader);

		try {
			$numericId = $this->itemTypeDictionary->fromStringId($itemData->getName());
		} catch (\InvalidArgumentException) {
			throw new ItemTypeSerializeException("Unknown item type " . $itemData->getName());
		}

		$blockStateData = $itemData->getBlock();

		if($blockStateData !== null){
			$blockRuntimeId = $this->blockStateDictionary->lookupStateIdFromData($blockStateData);
			if($blockRuntimeId === null){
				throw new AssumptionFailedError("Unmapped blockstate returned by blockstate serializer: " . $blockStateData->toNbt());
			}
		}else{
			$blockRuntimeId = null;
		}

		return [$numericId, $itemData->getMeta(), $blockRuntimeId];
	}

	/**
	 * @throws ItemTypeSerializeException
	 */
	public function toNetworkNbt(Item $item) : CompoundTag{
		//TODO: this relies on the assumption that network item NBT is the same as disk item NBT, which may not always
		//be true - if we stick on an older world version while updating network version, this could be a problem (and
		//may be a problem for multi version implementations)
		return $this->itemSerializer->serializeStack($item, null, $this->itemDataDowngrader)->toNbt();
	}

	/**
	 * @throws TypeConversionException
	 */
	public function fromNetworkId(int $networkId, int $networkMeta, int $networkBlockRuntimeId) : Item{
		try{
			$stringId = $this->itemTypeDictionary->fromIntId($networkId);
		}catch(\InvalidArgumentException $e){
			//TODO: a quiet version of fromIntId() would be better than catching InvalidArgumentException
			throw TypeConversionException::wrap($e, "Invalid network itemstack ID $networkId");
		}

		if($this->isLegacy223IdSpace){
			//Protocol 223 (1.2.13) predates the network block-runtime-ID concept entirely: the legacy
			//Slot codec (CommonTypes::getItemStackLegacy223()) never has one on the wire and always
			//passes NO_BLOCK_RUNTIME_ID here. It also predates ID flattening, so $stringId above is
			//still the OLD generic pre-flattening name (e.g. "minecraft:planks", not
			//"minecraft:jungle_planks"), which blockItemIdMap - keyed by modern post-flattening item
			//IDs - never recognises as a blockitem. Using the runtime-ID path below for this protocol
			//would therefore always treat block items as non-blocks and drop their block data, and
			//passing networkBlockRuntimeId=0 to generateCurrentDataFromStateId() would resolve to
			//whatever block actually owns state ID 0 rather than throwing, silently corrupting the
			//conversion instead of failing loudly.
			//
			//The (string id, meta) pair is exactly what a legacy block's identity actually was, so
			//resolve it the same way legacy world data is upgraded: via the legacy block id/meta
			//upgrade table (BlockDataUpgrader::upgradeStringIdMeta(), also used by
			//BlockDataUpgrader::upgradeBlockStateNbt() for old block NBT). A failure here just means
			//$stringId isn't a legacy block name, i.e. this is a plain (non-block) item.
			try{
				$blockStateData = GlobalBlockStateHandlers::getUpgrader()->getBlockIdMetaUpgrader()->fromStringIdMeta($stringId, $networkMeta);
			}catch(BlockStateDeserializeException){
				$blockStateData = null;
			}
		}else{
			$blockStateData = null;
			if($this->blockItemIdMap->lookupBlockId($stringId) !== null){
				$blockStateData = $this->blockStateDictionary->generateCurrentDataFromStateId($networkBlockRuntimeId);
				if($blockStateData === null){
					throw new TypeConversionException("Blockstate runtimeID $networkBlockRuntimeId does not correspond to any known blockstate");
				}
			}elseif($networkBlockRuntimeId !== self::NO_BLOCK_RUNTIME_ID){
				throw new TypeConversionException("Item $stringId is not a blockitem, but runtime ID $networkBlockRuntimeId was provided");
			}
		}

		[$stringId, $networkMeta] = GlobalItemDataHandlers::getUpgrader()->getIdMetaUpgrader()->upgrade($stringId, $networkMeta);

		try{
			return $this->itemDeserializer->deserializeType(new SavedItemData($stringId, $networkMeta, $blockStateData));
		}catch(ItemTypeDeserializeException $e){
			throw TypeConversionException::wrap($e, "Invalid network itemstack data");
		}
	}

	public static function getItemSchemaId(int $protocolId) : int{
		return match($protocolId){
			ProtocolInfo::PROTOCOL_1_2_13 => self::LEGACY_223_ITEM_SCHEMA_ID,

			ProtocolInfo::PROTOCOL_1_26_40,
			ProtocolInfo::PROTOCOL_1_26_30,
			ProtocolInfo::PROTOCOL_1_26_20 => 261,

			ProtocolInfo::PROTOCOL_1_26_10,
			ProtocolInfo::PROTOCOL_1_26_0,
			ProtocolInfo::PROTOCOL_1_21_130,
			ProtocolInfo::PROTOCOL_1_21_124,
			ProtocolInfo::PROTOCOL_1_21_120,
			ProtocolInfo::PROTOCOL_1_21_111 => 251,

			ProtocolInfo::PROTOCOL_1_21_100 => 241,

			ProtocolInfo::PROTOCOL_1_21_93,
			ProtocolInfo::PROTOCOL_1_21_90,
			ProtocolInfo::PROTOCOL_1_21_80,
			ProtocolInfo::PROTOCOL_1_21_70,
			ProtocolInfo::PROTOCOL_1_21_60,
			ProtocolInfo::PROTOCOL_1_21_50 => 231,

			ProtocolInfo::PROTOCOL_1_21_40 => 221,

			ProtocolInfo::PROTOCOL_1_21_30 => 211,

			ProtocolInfo::PROTOCOL_1_21_20 => 201,

			ProtocolInfo::PROTOCOL_1_21_2,
			ProtocolInfo::PROTOCOL_1_21_0 => 191,

			ProtocolInfo::PROTOCOL_1_20_80 => 181,

			ProtocolInfo::PROTOCOL_1_20_70 => 171,

			ProtocolInfo::PROTOCOL_1_20_60 => 161,

			ProtocolInfo::PROTOCOL_1_20_50 => 151,

			ProtocolInfo::PROTOCOL_1_20_40,
			ProtocolInfo::PROTOCOL_1_20_30 => 141,

			ProtocolInfo::PROTOCOL_1_20_10 => 121,

			ProtocolInfo::PROTOCOL_1_20_0 => 111,

			default => throw new AssumptionFailedError("Unknown protocol ID $protocolId"),
		};
	}
}
