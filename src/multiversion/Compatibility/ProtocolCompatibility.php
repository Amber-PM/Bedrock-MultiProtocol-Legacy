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

use pocketmine\multiversion\Capability\Capability;

/**
 * A ProtocolCompatibility answers "what can this client do?" for one connected protocol. It is the single
 * object core server code should consult instead of comparing ProtocolInfo constants directly, which keeps
 * per-version differences isolated in one place rather than scattered across the codebase.
 *
 * Access it via NetworkSession::getCompatibility(), not by constructing an implementation directly.
 */
interface ProtocolCompatibility{

	public function getProtocolId() : int;

	/**
	 * A short human-readable name for logging/diagnostics (e.g. "1.2.13 (protocol 223)", "Modern").
	 */
	public function getLabel() : string;

	public function supports(Capability $capability) : bool;
}
