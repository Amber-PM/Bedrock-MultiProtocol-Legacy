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
use pocketmine\command\overload\GreedyStringArgumentParser;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class SayCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"say",
			KnownTranslationFactory::pocketmine_command_say_description(),
			KnownTranslationFactory::commands_say_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SAY);

		$this->addOverload(
			fn(CommandSender $sender, string $message) => $this->announce($sender, $message),
			null,
			["message" => new GreedyStringArgumentParser()]
		);
	}

	private function announce(CommandSender $sender, string $message) : bool{
		$name = $sender instanceof Player ? $sender->getDisplayName() : ($sender instanceof ConsoleCommandSender ? "Server" : $sender->getName());
		$sender->getServer()->broadcastMessage(KnownTranslationFactory::chat_type_announcement($name, $message)->prefix(TextFormat::LIGHT_PURPLE));
		return true;
	}
}
