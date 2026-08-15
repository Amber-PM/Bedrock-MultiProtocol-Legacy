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

namespace pocketmine\command\overload;

use pocketmine\command\CommandSender;
use function implode;
use function in_array;

final class StringArgumentParser implements ArgumentParser{

	public function __construct(
		private ?array $allowedValues = null
	){}

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "string";
	}

	public function getAllowedValues() : ?array{
		return $this->allowedValues;
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$token = $tokens[0];
		if($this->allowedValues !== null && !in_array($token, $this->allowedValues, true)){
			return ParseResult::fail("\"$token\" is not one of: " . implode(", ", $this->allowedValues));
		}

		return ParseResult::ok($token);
	}
}
