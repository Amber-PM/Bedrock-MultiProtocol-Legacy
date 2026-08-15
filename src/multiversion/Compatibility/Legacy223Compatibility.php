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

namespace pocketmine\multiversion\Compatibility;

use pocketmine\multiversion\Capability\Capability;
use pocketmine\multiversion\Capability\CapabilityRegistry;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

/**
 * Compatibility profile for the legacy 1.2.13-1.2.16 Bedrock client (wire protocol 223).
 *
 * The capability set below reflects behaviour already implemented elsewhere in the codebase (see the
 * legacy-protocol branches in InGamePacketHandler, PreSpawnPacketHandler, CraftingDataCache, World and
 * BlockSound) - this class centralises what those branches already assume, it doesn't change behaviour by
 * itself. Callers should prefer `$compatibility->supports(...)` over comparing
 * `getProtocolId() === ProtocolInfo::PROTOCOL_1_2_13` directly.
 */
final class Legacy223Compatibility implements ProtocolCompatibility{

	private CapabilityRegistry $capabilities;

	public function __construct(){
		$this->capabilities = new CapabilityRegistry([
			Capability::CHAT,
			Capability::MOVEMENT,
			Capability::CHUNKS,
			Capability::BLOCKS,
			Capability::ITEMS,
			Capability::INVENTORY,
			Capability::CRAFTING,
			Capability::ENTITIES,
			Capability::RESOURCE_PACKS,
			Capability::PARTICLES,
			Capability::SOUNDS,

			//Deliberately NOT supported - see class doc comment for where each of these is currently handled:
			//Capability::SCOREBOARD           - not wired up for protocol 223 anywhere in the current handlers
			//Capability::FORMS                - not wired up for protocol 223 anywhere in the current handlers
			//Capability::MODERN_BLOCKSTATES   - World::sendBlocks() routes through Legacy223BlockRuntimeIdMap instead
			//Capability::ITEM_STACK_REQUESTS  - InGamePacketHandler routes through handleLegacy223NormalTransaction()
			//Capability::PLAYER_AUTH_INPUT    - InGamePacketHandler tracks lastMove223Position/Yaw/Pitch instead
			//Capability::ACTOR_IDENTIFIERS_PACKET - predates AvailableActorIdentifiersPacket/BiomeDefinitionListPacket
			//Capability::CREATIVE_CONTENT_PACKET  - predates CreativeContentPacket (InventoryManager::syncCreative())
			//Capability::MODERN_CRAFTING_STATIONS - predates blast furnace/smoker/stonecutter/cartography (CraftingDataCache)
		]);
	}

	public function getProtocolId() : int{
		return ProtocolInfo::PROTOCOL_1_2_13;
	}

	public function getLabel() : string{
		return "1.2.13-1.2.16 (protocol 223)";
	}

	public function supports(Capability $capability) : bool{
		return $this->capabilities->supports($capability);
	}
}
