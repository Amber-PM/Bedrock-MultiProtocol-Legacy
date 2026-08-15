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

use pocketmine\command\CommandSender;
use pocketmine\command\OverloadedCommand;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;

class SeedCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"seed",
			KnownTranslationFactory::pocketmine_command_seed_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SEED);

		$this->addOverload(
			fn(CommandSender $sender) => $this->showSeed($sender)
		);
	}

	private function showSeed(CommandSender $sender) : bool{
		$world = $sender instanceof Player
			? $sender->getPosition()->getWorld()
			: $sender->getServer()->getWorldManager()->getDefaultWorld();

		$sender->sendMessage(KnownTranslationFactory::commands_seed_success((string) $world->getSeed()));
		return true;
	}
}
