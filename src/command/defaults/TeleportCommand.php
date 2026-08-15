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
use pocketmine\command\overload\Vector3ArgumentParser;
use pocketmine\entity\Location;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use function round;

class TeleportCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"tp",
			KnownTranslationFactory::pocketmine_command_tp_description(),
			KnownTranslationFactory::commands_tp_usage(),
			["teleport"]
		);
		$this->setPermissions([
			DefaultPermissionNames::COMMAND_TELEPORT_SELF,
			DefaultPermissionNames::COMMAND_TELEPORT_OTHER
		]);

		$this->addOverload(
			fn(Player $sender, Player $target) => $this->teleportToEntity($sender, $sender, $target),
			DefaultPermissionNames::COMMAND_TELEPORT_SELF
		);
		$this->addOverload(
			fn(CommandSender $sender, Player $subject, Player $target) => $this->teleportToEntity($sender, $subject, $target),
			DefaultPermissionNames::COMMAND_TELEPORT_OTHER
		);
		$this->addOverload(
			fn(Player $sender, Vector3 $position, ?float $yaw = null, ?float $pitch = null) => $this->teleportToPosition($sender, $sender, $position, $yaw, $pitch),
			DefaultPermissionNames::COMMAND_TELEPORT_SELF
		);
		$this->addOverload(
			fn(CommandSender $sender, Player $subject, Vector3 $position, ?float $yaw = null, ?float $pitch = null) => $this->teleportToPosition($sender, $subject, $position, $yaw, $pitch),
			DefaultPermissionNames::COMMAND_TELEPORT_OTHER,
			["position" => new Vector3ArgumentParser(baseParamIndex: 0)]
		);
	}

	private function teleportToEntity(CommandSender $sender, Player $subject, Player $target) : bool{
		$subject->teleport($target->getLocation());
		self::broadcastCommandMessage($sender, KnownTranslationFactory::commands_tp_success($subject->getName(), $target->getName()));
		return true;
	}

	private function teleportToPosition(CommandSender $sender, Player $subject, Vector3 $position, ?float $yaw, ?float $pitch) : bool{
		$base = $subject->getLocation();
		$targetLocation = new Location(
			$position->x,
			$position->y,
			$position->z,
			$base->getWorld(),
			$yaw ?? $base->yaw,
			$pitch ?? $base->pitch
		);

		$subject->teleport($targetLocation);
		self::broadcastCommandMessage($sender, KnownTranslationFactory::commands_tp_success_coordinates(
			$subject->getName(),
			(string) round($targetLocation->x, 2),
			(string) round($targetLocation->y, 2),
			(string) round($targetLocation->z, 2)
		));
		return true;
	}
}
