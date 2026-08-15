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

namespace pocketmine\multiversion\Capability;

use function in_array;

/**
 * An immutable set of Capability values supported by a given protocol. Each ProtocolCompatibility
 * implementation owns one of these instead of hand-rolling its own supports() switch statement, so that
 * the list of what a protocol can do lives in one declarative place.
 */
final class CapabilityRegistry{

	/** @var Capability[] */
	private array $capabilities;

	/**
	 * @param Capability[] $capabilities
	 */
	public function __construct(array $capabilities){
		$this->capabilities = $capabilities;
	}

	public function supports(Capability $capability) : bool{
		return in_array($capability, $this->capabilities, true);
	}

	/**
	 * @return Capability[]
	 */
	public function all() : array{
		return $this->capabilities;
	}
}
