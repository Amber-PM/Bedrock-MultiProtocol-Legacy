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

/**
 * Compatibility profile for any protocol that isn't a dedicated legacy branch (currently: everything except
 * protocol 223 / 1.2.13-1.2.16). Supports every capability the server currently knows about.
 *
 * When a future version needs to *lose* a capability relative to current modern behaviour (rather than gain
 * one, which is the normal case), give it its own ProtocolCompatibility implementation instead of adding
 * branches here.
 */
final class ModernCompatibility implements ProtocolCompatibility{

	private CapabilityRegistry $capabilities;

	public function __construct(
		private int $protocolId
	){
		$this->capabilities = new CapabilityRegistry([
			Capability::CHAT,
			Capability::MOVEMENT,
			Capability::CHUNKS,
			Capability::BLOCKS,
			Capability::ITEMS,
			Capability::INVENTORY,
			Capability::CRAFTING,
			Capability::ENTITIES,
			Capability::SCOREBOARD,
			Capability::FORMS,
			Capability::RESOURCE_PACKS,
			Capability::PARTICLES,
			Capability::SOUNDS,
			Capability::MODERN_BLOCKSTATES,
			Capability::ITEM_STACK_REQUESTS,
			Capability::PLAYER_AUTH_INPUT,
			Capability::ACTOR_IDENTIFIERS_PACKET,
			Capability::CREATIVE_CONTENT_PACKET,
			Capability::MODERN_CRAFTING_STATIONS,
		]);
	}

	public function getProtocolId() : int{
		return $this->protocolId;
	}

	public function getLabel() : string{
		return "Modern (protocol {$this->protocolId})";
	}

	public function supports(Capability $capability) : bool{
		return $this->capabilities->supports($capability);
	}
}
