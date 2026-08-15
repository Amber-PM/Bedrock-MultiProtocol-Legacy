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

class StopCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"stop",
			KnownTranslationFactory::pocketmine_command_stop_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_STOP);

		$this->addOverload(
			fn(CommandSender $sender) => $this->stop($sender)
		);
	}

	private function stop(CommandSender $sender) : bool{
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_stop_start());
		$sender->getServer()->shutdown();
		return true;
	}
}
