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
use pocketmine\utils\TextFormat;
use function count;
use function memory_get_usage;
use function number_format;
use function round;

class GarbageCollectorCommand extends OverloadedCommand{

	public function __construct(){
		parent::__construct(
			"gc",
			KnownTranslationFactory::pocketmine_command_gc_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_GC);

		$this->addOverload(
			fn(CommandSender $sender) => $this->collectGarbage($sender)
		);
	}

	private function collectGarbage(CommandSender $sender) : bool{
		$chunksCollected = 0;
		$entitiesCollected = 0;
		$memory = memory_get_usage();

		foreach($sender->getServer()->getWorldManager()->getWorlds() as $world){
			$loadedChunks = count($world->getLoadedChunks());
			$entities = count($world->getEntities());

			$world->doChunkGarbageCollection();
			$world->unloadChunks(true);
			$world->clearCache(true);

			$chunksCollected += $loadedChunks - count($world->getLoadedChunks());
			$entitiesCollected += $entities - count($world->getEntities());
		}

		$cyclesCollected = $sender->getServer()->getMemoryManager()->triggerGarbageCollector();
		$memoryFreed = round((($memory - memory_get_usage()) / 1024) / 1024, 2);

		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gc_header()->format(TextFormat::GREEN . "---- " . TextFormat::RESET, TextFormat::GREEN . " ----" . TextFormat::RESET));
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gc_chunks(TextFormat::RED . number_format($chunksCollected))->prefix(TextFormat::GOLD));
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gc_entities(TextFormat::RED . number_format($entitiesCollected))->prefix(TextFormat::GOLD));
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gc_cycles(TextFormat::RED . number_format($cyclesCollected))->prefix(TextFormat::GOLD));
		$sender->sendMessage(KnownTranslationFactory::pocketmine_command_gc_memoryFreed(TextFormat::RED . number_format($memoryFreed, 2))->prefix(TextFormat::GOLD));

		return true;
	}
}
