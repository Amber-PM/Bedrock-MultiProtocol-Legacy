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

namespace pocketmine\player;

enum InputLockFlags : int {

	case MOVEMENT = 1 << 0;
	case ROTATION = 1 << 1;
	case JUMP     = 1 << 2;
	case SNEAK    = 1 << 3;
	case MOUNT    = 1 << 4;
	case DISMOUNT = 1 << 5;
	case HOTBAR   = 1 << 6;
	case ATTACK   = 1 << 7;

	public static function all() : int{
		$mask = 0;
		foreach(self::cases() as $case){
			$mask |= $case->value;
		}
		return $mask;
	}
}
