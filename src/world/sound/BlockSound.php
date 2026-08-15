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

namespace pocketmine\world\sound;

use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\convert\Legacy223BlockRuntimeIdMap;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacketV1;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;

abstract class BlockSound implements Sound{

	private BlockTranslator $blockTranslator;
	private int $protocolId;

	public function __construct(private Block $block){}

	public function setBlockTranslator(BlockTranslator $blockTranslator) : void{
		$this->blockTranslator = $blockTranslator;
	}
	public function setProtocolId(int $protocolId) : void{
		$this->protocolId = $protocolId;
	}

	public function toRuntimeId() : int{
		//Use the shared legacy-aware resolver rather than $this->blockTranslator->internalIdToNetworkId()
		//directly - see Legacy223BlockRuntimeIdMap::resolveNetworkBlockRuntimeId() doc comment.
		return Legacy223BlockRuntimeIdMap::resolveNetworkBlockRuntimeId($this->blockTranslator, $this->protocolId, $this->block->getStateId());
	}

	/**
	 * Builds a non-actor LevelSoundEvent packet for this block's place/break/hit sounds, routed to the
	 * packet class the connected protocol actually understands.
	 *
	 * The modern LevelSoundEventPacket (network ID 0x7b) only exists from protocol 1.21 onward. Protocol
	 * 223 (Minecraft: PE 1.2.13) never had that ID in its packet table; its sound-event packet is what this
	 * codebase calls LevelSoundEventPacketV1 (network ID 0x18, byte-encoded sound ID). Sending the modern
	 * packet's fixed network ID to a 223 client means the client simply doesn't recognise 0x7b, so the
	 * packet is silently dropped - no crash, just no sound. Don't call
	 * LevelSoundEventPacket::nonActorSound() directly from a BlockSound subclass, or place/break/hit sounds
	 * will silently stop working for protocol 223 clients again.
	 */
	protected function nonActorSoundPacket(string $sound, Vector3 $pos, bool $disableRelativeVolume, int $extraData = -1) : ClientboundPacket{
		if($this->protocolId === ProtocolInfo::PROTOCOL_1_2_13){
			return LevelSoundEventPacketV1::create(
				LevelSoundEvent::toId($sound),
				$pos,
				$extraData,
				1, //entityType: no entity is involved in a block sound; 1 matches the legacy reference's non-actor default
				false,
				$disableRelativeVolume
			);
		}
		return LevelSoundEventPacket::nonActorSound($sound, $pos, $disableRelativeVolume, $extraData);
	}
}
