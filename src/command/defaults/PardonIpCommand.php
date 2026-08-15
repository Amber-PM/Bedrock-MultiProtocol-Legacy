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
use function inet_pton;

class PardonIpCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"pardon-ip",
			KnownTranslationFactory::pocketmine_command_unban_ip_description(),
			KnownTranslationFactory::commands_unbanip_usage(),
			["unban-ip"]
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_UNBAN_IP);

		$this->addOverload(
			fn(CommandSender $sender, string $ip) => $this->pardonIp($sender, $ip)
		);
	}

	private function pardonIp(CommandSender $sender, string $ip) : bool{
		if(inet_pton($ip) === false){
			$sender->sendMessage(KnownTranslationFactory::commands_unbanip_invalid());
			return true;
		}

		$sender->getServer()->getIPBans()->remove($ip);
		$sender->getServer()->getNetwork()->unblockAddress($ip);
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::commands_unbanip_success($ip));
		return true;
	}
}
