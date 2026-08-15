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
use pocketmine\player\Player;

/**
 * Resolves a target player argument, additionally accepting the "@s" selector to mean "the sender itself"
 * (sender must be a Player in that case). Used for commands where the target is always an explicit token
 * (never implicit), e.g. /enchant and /effect.
 */
final class PlayerOrSelfArgumentParser implements ArgumentParser{

	public function getConsumedTokens() : int{
		return 1;
	}

	public function getTypeHint() : string{
		return "player";
	}

	public function parse(array $tokens, CommandSender $sender, array $previousValues) : ParseResult{
		$token = $tokens[0];
		if($token === "@s"){
			if(!($sender instanceof Player)){
				return ParseResult::fail(KnownTranslationFactory::pocketmine_command_error_playerUserOnly());
			}

			return ParseResult::ok($sender);
		}

		$player = $sender->getServer()->getPlayerByPrefix($token);
		if($player === null){
			return ParseResult::fail(KnownTranslationFactory::commands_generic_player_notFound());
		}

		return ParseResult::ok($player);
	}
}
