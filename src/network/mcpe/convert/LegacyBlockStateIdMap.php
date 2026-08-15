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
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function array_flip;

/**
 * Reverse of the id-meta-to-blockstate upgrade table (@see \pocketmine\data\bedrock\block\upgrade\BlockIdMetaUpgrader),
 * built once at first use.
 *
 * The upgrade table is many-to-one, so going backwards can't be exact in general: this class keeps the *first*
 * legacy id/meta pair encountered for each modern internal state ID and discards the rest.
 */
final class LegacyBlockStateIdMap{
	use SingletonTrait;

	/**
	 * @var int[][]
	 * @phpstan-var array<int, array{0: int, 1: int}>
	 */
	private array $internalStateIdToLegacy = [];

	/** Internal state ID used when a block has no legacy id/meta representative (falls back to legacy air, 0:0). */
	private const FALLBACK = [0, 0];

	private function __construct(){
		$idMetaUpgrader = GlobalBlockStateHandlers::getUpgrader()->getBlockIdMetaUpgrader();
		$deserializer = GlobalBlockStateHandlers::getDeserializer();

		//BlockIdMetaUpgrader's map is keyed by *string* ID; legacyToString() from the id map file only goes
		//legacy->string, so the numeric legacy ID has to be recovered by flipping it (the map is 1:1 by construction,
		//since it's generated from a fixed legacy ID list, so flipping is safe).
		$stringToLegacyId = array_flip($idMetaUpgrader->getLegacyNumericIdMap()->getLegacyToStringMap());

		foreach($idMetaUpgrader->getMappingTable() as $stringId => $metaMap){
			$legacyNumericId = $stringToLegacyId[$stringId] ?? null;
			if($legacyNumericId === null || $legacyNumericId < 0 || $legacyNumericId > 255){
				continue;
			}

			foreach($metaMap as $meta => $stateData){
				if($meta < 0 || $meta > 15){
					continue;
				}

				try{
					$internalStateId = $deserializer->deserialize($stateData);
				}catch(BlockStateDeserializeException | \LogicException){
					//this modern state isn't understood by the currently loaded block deserializer (e.g. a
					//custom/plugin block state referenced by the upgrade schema but never registered) - skip it,
					//it can't be looked up by internal state ID anyway.
					continue;
				}

				//first legacy id/meta found for this internal state ID wins; see class docblock.
				$this->internalStateIdToLegacy[$internalStateId] ??= [$legacyNumericId, $meta];
			}
		}
	}

	/**
	 * @return int[]
	 * @phpstan-return array{0: int, 1: int}
	 */
	public function toLegacy(int $internalStateId) : array{
		return $this->internalStateIdToLegacy[$internalStateId] ?? self::FALLBACK;
	}
}
