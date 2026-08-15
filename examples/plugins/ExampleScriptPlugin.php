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
namespace pmmp\ExampleScriptPlugin;

use pocketmine\event\Listener;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\plugin\PluginBase;

/**
 * Script plugins are self-contained .php files. They are intended for quick testing only.
 * They don't support all the features of a normal plugin.
 * See the documentation at https://doc.pmmp.io/en/rtfd/developers/plugin-docs/plugin-formats/development.html#script
 *
 * Required fields
 * @main pmmp\ExampleScriptPlugin\Main
 * @api 5.37.0
 *
 * Optional fields
 * Version and name are optional in script plugins for convenience, and will be filled with 1.0.0 and
 * ScriptPlugin_{file name without extension} respectively.
 * @version 1.0.0
 * @name ExampleScriptPlugin
 * @load STARTUP
 */
class Main extends PluginBase{
	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents(new ExampleListener($this->getLogger()), $this);
	}
}

class ExampleListener implements Listener{

	public function __construct(
		private \Logger $logger
	){}

	public function onWorldLoad(WorldLoadEvent $event) : void{
		$this->logger->info("Script plugin detected world " . $event->getWorld()->getDisplayName() . " being loaded!");
	}
}

