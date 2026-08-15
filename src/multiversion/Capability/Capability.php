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

namespace pocketmine\multiversion\Capability;

/**
 * A Capability describes something a client protocol may or may not be able to do. Server code should ask
 * "can this client do X?" via ProtocolCompatibility::supports() instead of comparing protocol IDs or version
 * numbers directly.
 *
 * This list covers the capabilities that differ between supported client versions, including the ones that
 * older Bedrock clients lack (see PreSpawnPacketHandler, InGamePacketHandler, CraftingDataCache, BlockSound
 * for examples of how they're used). It is expected to grow as new versions are added.
 */
enum Capability{
	case CHAT;
	case MOVEMENT;
	case CHUNKS;
	case BLOCKS;
	case ITEMS;
	case INVENTORY;
	case CRAFTING;
	case ENTITIES;
	case SCOREBOARD;
	case FORMS;
	case RESOURCE_PACKS;
	case PARTICLES;
	case SOUNDS;

	/** Modern per-block NBT-based blockstate system (as opposed to legacy numeric ID+meta on the wire). */
	case MODERN_BLOCKSTATES;

	/** ItemStackRequestPacket-based inventory transactions (as opposed to legacy NetworkInventoryAction). */
	case ITEM_STACK_REQUESTS;

	/** PlayerAuthInputPacket-based movement (as opposed to legacy MovePlayerPacket-only movement). */
	case PLAYER_AUTH_INPUT;

	/** AvailableActorIdentifiersPacket / BiomeDefinitionListPacket, which predate protocol 223. */
	case ACTOR_IDENTIFIERS_PACKET;

	/** CreativeContentPacket, which predates protocol 223 (legacy fills its creative tab from a fixed table). */
	case CREATIVE_CONTENT_PACKET;

	/** Advanced furnace variants (blast furnace, smoker, campfire) and stonecutter/cartography recipes. */
	case MODERN_CRAFTING_STATIONS;
}
