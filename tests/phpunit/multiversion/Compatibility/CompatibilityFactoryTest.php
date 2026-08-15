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

use PHPUnit\Framework\TestCase;
use pocketmine\multiversion\Capability\Capability;
use pocketmine\network\mcpe\protocol\ProtocolInfo;

/**
 * Coverage for the per-protocol compatibility layer (isolating protocol differences behind Capability
 * checks instead of scattered protocol-ID comparisons).
 *
 * Run with `vendor/bin/phpunit`.
 */
final class CompatibilityFactoryTest extends TestCase{

	public function testProtocol223ResolvesToLegacy223Compatibility() : void{
		$compatibility = CompatibilityFactory::get(ProtocolInfo::PROTOCOL_1_2_13);

		self::assertInstanceOf(Legacy223Compatibility::class, $compatibility);
		self::assertSame(ProtocolInfo::PROTOCOL_1_2_13, $compatibility->getProtocolId());
	}

	public function testCurrentProtocolResolvesToModernCompatibility() : void{
		$compatibility = CompatibilityFactory::get(ProtocolInfo::CURRENT_PROTOCOL);

		self::assertInstanceOf(ModernCompatibility::class, $compatibility);
		self::assertSame(ProtocolInfo::CURRENT_PROTOCOL, $compatibility->getProtocolId());
	}

	public function testFactoryCachesInstancesPerProtocolId() : void{
		$first = CompatibilityFactory::get(ProtocolInfo::PROTOCOL_1_2_13);
		$second = CompatibilityFactory::get(ProtocolInfo::PROTOCOL_1_2_13);

		self::assertSame($first, $second);
	}

	public function testModernCompatibilitySupportsEveryKnownCapability() : void{
		$compatibility = CompatibilityFactory::get(ProtocolInfo::CURRENT_PROTOCOL);

		foreach(Capability::cases() as $capability){
			self::assertTrue(
				$compatibility->supports($capability),
				"Modern compatibility should support {$capability->name}"
			);
		}
	}

	/**
	 * Protocol 223 supports chat, movement, inventory, crafting and basic entities, but not modern
	 * blockstates, forms, item stack requests, or scoreboard.
	 */
	public function testLegacy223CompatibilityHasTheExpectedCapabilitySet() : void{
		$compatibility = CompatibilityFactory::get(ProtocolInfo::PROTOCOL_1_2_13);

		foreach([
			Capability::CHAT,
			Capability::MOVEMENT,
			Capability::INVENTORY,
			Capability::CRAFTING,
			Capability::ENTITIES,
		] as $capability){
			self::assertTrue($compatibility->supports($capability), "Legacy 223 should support {$capability->name}");
		}

		foreach([
			Capability::MODERN_BLOCKSTATES,
			Capability::FORMS,
			Capability::ITEM_STACK_REQUESTS,
			Capability::SCOREBOARD,
		] as $capability){
			self::assertFalse($compatibility->supports($capability), "Legacy 223 should NOT support {$capability->name}");
		}
	}
}
