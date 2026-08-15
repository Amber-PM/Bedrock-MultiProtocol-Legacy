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
use pocketmine\lang\KnownTranslationFactory;
use function preg_match;

final class IntegerArgumentParser implements ArgumentParser{

	public function __construct(
		private ?int $min = null,
		private ?int $max = null
	){}

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "int";
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$token = $tokens[0];
		if(preg_match('/^[-+]?\d+$/', $token) !== 1){
			return ParseResult::fail("\"$token\" is not a valid integer");
		}

		$value = (int) $token;
		if($this->min !== null && $value < $this->min){
			return ParseResult::fail(KnownTranslationFactory::commands_generic_num_tooSmall($token, (string) $this->min));
		}
		if($this->max !== null && $value > $this->max){
			return ParseResult::fail(KnownTranslationFactory::commands_generic_num_tooBig($token, (string) $this->max));
		}

		return ParseResult::ok($value);
	}
}
