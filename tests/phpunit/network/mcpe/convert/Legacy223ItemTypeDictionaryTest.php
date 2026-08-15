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

namespace pocketmine\network\mcpe\convert;

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use function count;

/**
 * Regression coverage for the legacy item type dictionary.
 *
 * Root cause under test: protocol 223 (MCPE 1.2.13) must not share an item schema ID or item type
 * dictionary file with a modern protocol like 1.20.0. 1.2.13 predates the item-type-dictionary
 * handshake entirely and uses raw legacy numeric IDs on the wire, so sharing a schema would cause
 * legacy clients to receive modern (1.20.0-era) arbitrary dictionary runtime IDs instead of the
 * numeric IDs their compiled item table actually understands.
 *
 * Run with `vendor/bin/phpunit`.
 */
final class Legacy223ItemTypeDictionaryTest extends TestCase{

	public function testProtocol223UsesADedicatedSchemaIdDistinctFromModernProtocols() : void{
		$legacySchemaId = ItemTranslator::getItemSchemaId(ProtocolInfo::PROTOCOL_1_2_13);
		$modernSchemaId = ItemTranslator::getItemSchemaId(ProtocolInfo::PROTOCOL_1_20_0);

		self::assertNotSame(
			$modernSchemaId,
			$legacySchemaId,
			"Protocol 223 must not share its item downgrade schema baseline with a modern protocol."
		);
	}

	public function testLegacyDictionaryContainsEraAppropriateItems() : void{
		$dictionary = Legacy223ItemTypeDictionary::get();

		//Present since the very first flat ID table (block/item IDs 1 and 276 respectively).
		self::assertSame(1, $dictionary->fromStringId("minecraft:stone"));
		self::assertSame(276, $dictionary->fromStringId("minecraft:diamond_sword"));

		//Elytra (id 444) existed with a legacy numeric ID before the flattening, so it must be present
		//here too.
		self::assertSame(444, $dictionary->fromStringId("minecraft:elytra"));
	}

	public function testLegacyDictionaryExcludesItemsThatDidNotExistInThatEra() : void{
		$dictionary = Legacy223ItemTypeDictionary::get();

		//Shields were added to Bedrock after 1.2.13. Even though PMMP's own historical
		//item_legacy_id_map.json assigns minecraft:shield a "legacy" numeric ID (513) for
		//world-save compatibility purposes, that ID is not part of a genuine 1.2.13 client's
		//compiled item table (it is absent from the attached era-accurate reference
		//implementation), so it must NOT be present in the protocol-223 dictionary.
		$this->expectException(\InvalidArgumentException::class);
		$dictionary->fromStringId("minecraft:shield");
	}

	public function testLegacyDictionaryHasNoDuplicateNumericIds() : void{
		$dictionary = Legacy223ItemTypeDictionary::get();

		$seen = [];
		foreach($dictionary->getEntries() as $entry){
			$numericId = $entry->getNumericId();
			self::assertArrayNotHasKey(
				$numericId,
				$seen,
				"Legacy numeric ID $numericId is mapped from more than one string ID (" .
				($seen[$numericId] ?? "?") . " and " . $entry->getStringId() . "); a real 1.2.13 ".
				"client cannot distinguish these two items."
			);
			$seen[$numericId] = $entry->getStringId();
		}

		self::assertGreaterThan(0, count($seen));
	}

	public function testTypeConverterCanBeConstructedForProtocol223WithoutThrowing() : void{
		//Regression check for the shieldRuntimeId lookup, which previously called
		//ItemTypeDictionary::fromStringId("minecraft:shield") unconditionally in the constructor -
		//this would throw \InvalidArgumentException for protocol 223 once shield was correctly
		//removed from the legacy dictionary, crashing session setup for every 1.2.13 client.
		$converter = TypeConverter::getInstance(ProtocolInfo::PROTOCOL_1_2_13);
		self::assertSame(ProtocolInfo::PROTOCOL_1_2_13, $converter->getProtocolId());
	}
}
