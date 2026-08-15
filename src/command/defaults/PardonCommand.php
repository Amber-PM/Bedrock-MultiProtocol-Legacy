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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;

class PardonCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"pardon",
			KnownTranslationFactory::pocketmine_command_unban_player_description(),
			KnownTranslationFactory::commands_unban_usage(),
			["unban"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_UNBAN_PLAYER);

		$this->addOverload(
			fn(CommandSender $sender, string $name) => $this->pardon($sender, $name)
		);
	}

	private function pardon(CommandSender $sender, string $name) : bool{
		$sender->getServer()->getNameBans()->remove($name);
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_unban_success($name));
		return true;
	}
}
