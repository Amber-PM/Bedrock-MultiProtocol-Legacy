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

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use Ramsey\Uuid\Uuid;
use function json_encode;
use function str_repeat;

/**
 * Regression coverage for the freeze/crash of a real Minecraft Bedrock 1.2.13 client (protocol 223) shortly
 * after a modern (Xbox Persona) skin entered its render range via PLAYER_LIST_PACKET.
 *
 * Root cause: CommonTypes::putSkinLegacy() forwarded the modern skin's raw geometryData (a large,
 * format_version 1.14.0 Persona geometry JSON with bones/pieces the 1.2.13-1.2.16 client has never seen)
 * to the legacy client verbatim. The client has no code path for that geometry shape and locks up trying
 * to render it. This test asserts a Persona/incompatible skin is transparently swapped for the client's own
 * built-in classic humanoid model when serialized for protocol 223, while a modern client still receives the
 * original Persona data untouched.
 */
final class PlayerListPacketLegacySkinTest extends TestCase{

	private function makePersonaSkinData() : SkinData{
		//shape/size modelled on the real capture: a large modern geometry export with persona bones
		$geometryData = json_encode([
			"format_version" => "1.14.0",
			"minecraft:geometry" => [
				["description" => ["identifier" => "geometry.persona_91f90c287f3ca15c-4"], "bones" => ["belt", "armor_chest"]],
			],
		]);

		return new SkinData(
			skinId: "persona-91f90c287f3ca15c-4",
			playFabId: "",
			resourcePatch: json_encode(["geometry" => ["default" => "geometry.persona_91f90c287f3ca15c-4"]]),
			skinImage: new SkinImage(64, 64, str_repeat("\x00", 64 * 64 * 4)),
			geometryData: $geometryData,
			persona: true,
		);
	}

	private function makeClassicSkinData() : SkinData{
		return new SkinData(
			skinId: "Standard_Custom",
			playFabId: "",
			resourcePatch: json_encode(["geometry" => ["default" => "geometry.humanoid.customSlim"]]),
			skinImage: new SkinImage(64, 64, str_repeat("\x7f\x7f\x7f\xff", 64 * 64)),
			geometryData: json_encode(["format_version" => "1.8.0", "geometry.humanoid.customSlim" => ["bones" => []]]),
		);
	}

	private function roundTrip(SkinData $skin, int $protocolId) : SkinData{
		$entry = PlayerListEntry::createAdditionEntry(Uuid::uuid4(), 3, "SomePlayer", $skin, "1234567890");

		$out = new ByteBufferWriter();
		PlayerListPacket::add([$entry])->encode($out, $protocolId);

		$in = new ByteBufferReader($out->getData());
		$decoded = new PlayerListPacket();
		$decoded->decode($in, $protocolId);

		self::assertSame(0, $in->getUnreadLength(), "No bytes should be left unread after decoding");
		self::assertCount(1, $decoded->entries);
		return $decoded->entries[0]->skinData;
	}

	public function testPersonaSkinIsConvertedToClassicFallbackForProtocol223() : void{
		$result = $this->roundTrip($this->makePersonaSkinData(), ProtocolInfo::PROTOCOL_1_2_13);

		self::assertSame("geometry.humanoid.custom", $this->extractGeometryName($result));
		self::assertSame("", $result->getGeometryData(), "No modern geometry JSON must reach the legacy client");
		//identity fields untouched by the skin fallback
		self::assertSame("persona-91f90c287f3ca15c-4", $result->getSkinId());
	}

	public function testClassicSkinIsPreservedForProtocol223() : void{
		$result = $this->roundTrip($this->makeClassicSkinData(), ProtocolInfo::PROTOCOL_1_2_13);

		self::assertSame("geometry.humanoid.customSlim", $this->extractGeometryName($result));
		self::assertNotSame("", $result->getGeometryData(), "A legitimate small classic geometry must not be stripped");
	}

	public function testPersonaSkinIsUnaffectedForModernProtocol() : void{
		$original = $this->makePersonaSkinData();
		$result = $this->roundTrip($original, ProtocolInfo::CURRENT_PROTOCOL);

		self::assertTrue($result->isPersona());
		self::assertSame($original->getGeometryData(), $result->getGeometryData(), "Modern clients must still receive the original Persona geometry");
	}

	private function extractGeometryName(SkinData $skin) : string{
		//for protocol 223 the geometry name travels as resourcePatch (see CommonTypes::getSkinLegacy())
		return $skin->getResourcePatch();
	}
}
