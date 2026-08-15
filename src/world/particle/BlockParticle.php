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

namespace pocketmine\world\particle;

use pocketmine\block\Block;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\convert\Legacy223BlockRuntimeIdMap;

abstract class BlockParticle implements Particle{

	private BlockTranslator $blockTranslator;
	private int $protocolId;

	public function __construct(protected Block $b){}

	public function setBlockTranslator(BlockTranslator $blockTranslator) : void{
		$this->blockTranslator = $blockTranslator;
	}
	public function setProtocolId(int $protocolId) : void{
		$this->protocolId = $protocolId;
	}
	public function toRuntimeId() : int{
		//Use the shared legacy-aware resolver rather than $this->blockTranslator->internalIdToNetworkId()
		//directly - see Legacy223BlockRuntimeIdMap::resolveNetworkBlockRuntimeId() doc comment.
		return Legacy223BlockRuntimeIdMap::resolveNetworkBlockRuntimeId($this->blockTranslator, $this->protocolId, $this->b->getStateId());
	}
}
