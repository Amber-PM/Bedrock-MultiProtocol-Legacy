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

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

/**
 * Builds the ItemTypeDictionary used for protocol 223 (MCPE 1.2.13).
 *
 * Unlike every other supported protocol, 1.2.13 predates the "item type dictionary" handshake concept
 * entirely (that was introduced around 1.16 with the ID flattening). A genuine 1.2.13 client has a
 * *fixed, compiled-in* table of legacy numeric item/block IDs (0-255 for blocks, 256-511 for items in
 * that era) and has no idea what to do with the arbitrary numeric "runtime IDs" used by the modern
 * (1.20.0+) item dictionary. Loading protocol 223 through the same path as modern protocols (see
 * ItemTypeDictionaryFromDataHelper::PATHS, which would otherwise map PROTOCOL_1_2_13 to the same
 * "-1.20.0" table as PROTOCOL_1_20_0) would cause legacy clients to show or receive the wrong items.
 *
 * This class instead loads a curated legacy string-id -> legacy numeric-id map
 * (resources/vanilla/item_legacy_223_id_map.json), built by cross-referencing
 * pocketmine/bedrock-item-upgrade-schema's item_legacy_id_map.json (PMMP's own historical legacy-ID
 * table, used for world/NBT compatibility) against era-accurate legacy ID constants for a
 * 1.2.13-generation server. Only IDs present in both sources were kept (441 of 807 legacy map entries);
 * this excludes items that only ever existed with PMMP-internal synthetic legacy IDs (e.g. IDs invented
 * after the 1.13 flattening), as well as items that are historically later than 1.2.13 but still happen
 * to have a "legacy-era" ID assigned in PMMP's map (e.g. minecraft:shield, added in a later Bedrock
 * version, is correctly absent from this table).
 *
 * Known limitation: this cross-reference is a best-effort approximation, not an authoritative MCPE
 * v1.2.13 item table, and has not been validated against real network traffic or a real 1.2.13 client.
 * Items missing from the table fall back to the existing TypeConverter::coreItemStackToNet() placeholder
 * behaviour (info_update block) rather than sending an invalid/unmapped numeric ID.
 */
final class Legacy223ItemTypeDictionary{

	private const RESOURCE_FILE = "vanilla/item_legacy_223_id_map.json";

	private static ?ItemTypeDictionary $instance = null;

	private function __construct(){
		//NOOP - static utility class
	}

	public static function get() : ItemTypeDictionary{
		return self::$instance ??= self::load();
	}

	private static function load() : ItemTypeDictionary{
		$path = \pocketmine\RESOURCE_PATH . self::RESOURCE_FILE;
		$raw = Filesystem::fileGetContents($path);

		$table = json_decode($raw, true);
		if(!is_array($table)){
			throw new AssumptionFailedError("Invalid format of $path, expected a JSON object");
		}

		$emptyNbt = new CacheableNbt(new CompoundTag());

		$entries = [];
		foreach(Utils::promoteKeys($table) as $stringId => $legacyId){
			if(!is_string($stringId) || !is_int($legacyId)){
				throw new AssumptionFailedError("$path should only contain string keys mapped to int values");
			}
			//version 1 marks these as pre-flattening legacy entries; componentBased is always false
			//since components did not exist on the 1.2.13 wire format.
			$entries[] = new ItemTypeEntry($stringId, $legacyId, false, 1, $emptyNbt);
		}

		return new ItemTypeDictionary($entries);
	}
}
