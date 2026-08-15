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

class SaveOffCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"save-off",
			KnownTranslationFactory::pocketmine_command_saveoff_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SAVE_DISABLE);

		$this->addOverload(
			fn(CommandSender $sender) => $this->disable($sender)
		);
	}

	private function disable(CommandSender $sender) : bool{
		$sender->getServer()->getWorldManager()->setAutoSave(false);
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_save_disabled());
		return true;
	}
}
