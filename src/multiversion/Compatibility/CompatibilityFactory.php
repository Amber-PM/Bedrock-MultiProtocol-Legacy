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

namespace pocketmine\multiversion\Compatibility;

use pocketmine\network\mcpe\protocol\ProtocolInfo;

/**
 * Resolves the ProtocolCompatibility for a given protocol ID. This is the only place in the codebase that
 * should need to know which protocol IDs map to which compatibility profile - everything else should go
 * through the resulting ProtocolCompatibility object (usually via NetworkSession::getCompatibility()).
 *
 * Adding support for another legacy protocol means adding one branch here and one new
 * ProtocolCompatibility implementation, not touching every call site that currently checks getProtocolId().
 */
final class CompatibilityFactory{

	/** @var array<int, ProtocolCompatibility> */
	private static array $cache = [];

	private function __construct(){
		//no instances
	}

	public static function get(int $protocolId) : ProtocolCompatibility{
		return self::$cache[$protocolId] ??= self::create($protocolId);
	}

	private static function create(int $protocolId) : ProtocolCompatibility{
		if($protocolId === ProtocolInfo::PROTOCOL_1_2_13){
			return new Legacy223Compatibility();
		}

		return new ModernCompatibility($protocolId);
	}
}
