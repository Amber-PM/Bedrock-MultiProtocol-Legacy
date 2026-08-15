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
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\world\World;

class SetWorldSpawnCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"setworldspawn",
			KnownTranslationFactory::pocketmine_command_setworldspawn_description(),
			KnownTranslationFactory::commands_setworldspawn_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SETWORLDSPAWN);

		$this->addOverload(
			fn(Player $sender) => $this->setSpawn($sender, $sender->getWorld(), $sender->getPosition()->asVector3())
		);
		$this->addOverload(
			fn(CommandSender $sender, Vector3 $position) => $this->setSpawn(
				$sender,
				$sender instanceof Player ? $sender->getWorld() : $sender->getServer()->getWorldManager()->getDefaultWorld(),
				$position
			)
		);
	}

	private function setSpawn(CommandSender $sender, World $world, Vector3 $position) : bool{
		$spawn = $position->floor();
		$world->setSpawnLocation($spawn);

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_setworldspawn_success(
			(string) $spawn->x,
			(string) $spawn->y,
			(string) $spawn->z
		));
		return true;
	}
}
