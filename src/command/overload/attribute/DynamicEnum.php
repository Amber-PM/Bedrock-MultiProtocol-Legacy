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

namespace pocketmine\command\overload\attribute;

use pocketmine\command\overload\ArgumentParser;
use pocketmine\command\overload\DynamicEnumArgumentParser;
use RuntimeException;
use function is_a;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class DynamicEnum implements ParserAttribute{

	/**
	 * @param class-string<DynamicEnumProvider> $provider
	 */
	public function __construct(
		private string $provider
	){
		if(!is_a($provider, DynamicEnumProvider::class, true)){
			throw new RuntimeException("$provider must implement " . DynamicEnumProvider::class);
		}
	}

	public function createParser() : ArgumentParser{
		return new DynamicEnumArgumentParser($this->provider);
	}
}
