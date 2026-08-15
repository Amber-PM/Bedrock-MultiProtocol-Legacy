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
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\entity\ByteMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;

/**
 * Regression coverage: on protocol 223 (1.2.13-1.2.16), a survival/adventure player appeared to have
 * no gravity, ice-skated around with no collision (and couldn't jump properly), and showed air bubbles outside
 * of water, despite AdventureSettingsPacket/abilities being sent correctly.
 *
 * Root cause: DATA_FLAGS (the generic entity flags long, metadata key 0) is built everywhere in this codebase
 * using the *modern* EntityMetadataFlags bit numbering (e.g. AFFECTED_BY_GRAVITY=49, HAS_COLLISION=48,
 * BREATHING=35). Protocol 223 predates several flags that were later inserted into the middle of that bit
 * layout (ORPHANED=29, CAN_DASH=46, and everything from SHOW_TRIDENT_ROPE=53 onward), so its own numbering is
 * shifted down (AFFECTED_BY_GRAVITY=47, HAS_COLLISION=46, BREATHING=34 in the pre-1.16 PocketMine-MP
 * `entity/Entity.php` DATA_FLAG_* constants). Sending the modern bit positions verbatim means the 223 client
 * reads its own bits 47/46/34, which are never set - so it thinks it has no gravity, no collision, and isn't
 * breathing.
 *
 * The fix remaps DATA_FLAGS bit-for-bit in CommonTypes::putEntityMetadata()/getEntityMetadata(), gated to
 * PROTOCOL_1_2_13 only, and drops DATA_FLAGS2 (key 92, extended flags) outright since it doesn't exist on this
 * protocol.
 */
final class EntityMetadataLegacy223Test extends TestCase{

	/**
	 * A protocol used only by the raw-wire-inspection helper below: modern enough to skip the 223 remap (so we
	 * see the bits exactly as written), but below PROTOCOL_1_26_40 so it doesn't expect the extra
	 * "legacy type sent twice" byte that CURRENT_PROTOCOL would - putEntityMetadata() was never asked to write
	 * that byte for a PROTOCOL_1_2_13 buffer, so decoding it back with CURRENT_PROTOCOL would desync.
	 */
	private const RAW_READ_PROTOCOL = ProtocolInfo::PROTOCOL_1_21_130;

	/**
	 * Survival/adventure player: gravity on, collision on, name tag visible. Modern bits 49/48/14 must arrive
	 * on the wire as legacy bits 47/46/14 for a 223 client - anything else reproduces the "no gravity, ice
	 * skating" bug.
	 */
	public function testSurvivalPlayerFlagsAreRemappedToLegacyBitPositions() : void{
		$modernFlags = 0;
		$modernFlags |= (1 << EntityMetadataFlags::AFFECTED_BY_GRAVITY); //49
		$modernFlags |= (1 << EntityMetadataFlags::HAS_COLLISION); //48
		$modernFlags |= (1 << EntityMetadataFlags::CAN_SHOW_NAMETAG); //14

		$metadata = [
			EntityMetadataProperties::FLAGS => new LongMetadataProperty($modernFlags),
		];

		$writer = new ByteBufferWriter();
		CommonTypes::putEntityMetadata($writer, ProtocolInfo::PROTOCOL_1_2_13, $metadata);

		$reader = new ByteBufferReader($writer->getData());
		$decoded = self::readRawEntityMetadata($reader);

		self::assertArrayHasKey(EntityMetadataProperties::FLAGS, $decoded);
		$wireFlags = $decoded[EntityMetadataProperties::FLAGS]->getValue();

		self::assertSame(1, ($wireFlags >> 47) & 1, "AFFECTED_BY_GRAVITY must be on legacy bit 47");
		self::assertSame(1, ($wireFlags >> 46) & 1, "HAS_COLLISION must be on legacy bit 46");
		self::assertSame(1, ($wireFlags >> 14) & 1, "CAN_SHOW_NAMETAG is unchanged (bit 14 in both eras)");
		//the modern bit positions themselves must NOT be set - this is exactly the pre-fix bug
		self::assertSame(0, ($wireFlags >> 49) & 1);
		self::assertSame(0, ($wireFlags >> 48) & 1);
	}

	/**
	 * BREATHING (modern bit 35) must land on legacy bit 34, or the 223 client shows air bubbles outside of
	 * water because it reads its own (unset) bit 34 as "not breathing".
	 */
	public function testBreathingFlagIsRemappedToLegacyBitPosition() : void{
		$metadata = [
			EntityMetadataProperties::FLAGS => new LongMetadataProperty(1 << EntityMetadataFlags::BREATHING),
		];

		$writer = new ByteBufferWriter();
		CommonTypes::putEntityMetadata($writer, ProtocolInfo::PROTOCOL_1_2_13, $metadata);

		$reader = new ByteBufferReader($writer->getData());
		$decoded = self::readRawEntityMetadata($reader);

		$wireFlags = $decoded[EntityMetadataProperties::FLAGS]->getValue();
		self::assertSame(1, ($wireFlags >> 34) & 1);
		self::assertSame(0, ($wireFlags >> 35) & 1);
	}

	/**
	 * SWIMMING (modern bit 57) postdates 1.2.13 entirely - there is no legacy bit for it. It must be dropped,
	 * not silently corrupt some unrelated legacy flag that happens to share the low bits of 57.
	 */
	public function testFlagsWithNoLegacyEquivalentAreDropped() : void{
		$metadata = [
			EntityMetadataProperties::FLAGS => new LongMetadataProperty(1 << EntityMetadataFlags::SWIMMING),
		];

		$writer = new ByteBufferWriter();
		CommonTypes::putEntityMetadata($writer, ProtocolInfo::PROTOCOL_1_2_13, $metadata);

		$reader = new ByteBufferReader($writer->getData());
		$decoded = self::readRawEntityMetadata($reader);

		self::assertSame(0, $decoded[EntityMetadataProperties::FLAGS]->getValue());
	}

	/**
	 * DATA_FLAGS2 (key 92, extended/"second" flags) does not exist on protocol 223 at all and must never be
	 * written on the wire for a 223 client - the legacy client's fixed-format decoder has no slot for it.
	 */
	public function testFlags2IsDroppedEntirelyForLegacyProtocol() : void{
		$metadata = [
			EntityMetadataProperties::FLAGS => new LongMetadataProperty(1 << EntityMetadataFlags::AFFECTED_BY_GRAVITY),
			EntityMetadataProperties::FLAGS2 => new LongMetadataProperty(1),
		];

		$writer = new ByteBufferWriter();
		CommonTypes::putEntityMetadata($writer, ProtocolInfo::PROTOCOL_1_2_13, $metadata);

		$reader = new ByteBufferReader($writer->getData());
		$decoded = self::readRawEntityMetadata($reader);

		self::assertArrayNotHasKey(EntityMetadataProperties::FLAGS2, $decoded);
		self::assertCount(1, $decoded, "count() on the wire must match the actually-written entries, not the input array");
	}

	/**
	 * The modern path (anything newer than protocol 223) must be byte-for-byte unaffected by this change:
	 * flags pass straight through with no remapping.
	 */
	public function testModernProtocolFlagsAreUnaffected() : void{
		$modernFlags = (1 << EntityMetadataFlags::AFFECTED_BY_GRAVITY) | (1 << EntityMetadataFlags::HAS_COLLISION);

		$metadata = [
			EntityMetadataProperties::FLAGS => new LongMetadataProperty($modernFlags),
		];

		$writer = new ByteBufferWriter();
		CommonTypes::putEntityMetadata($writer, self::RAW_READ_PROTOCOL, $metadata);

		$reader = new ByteBufferReader($writer->getData());
		$decoded = self::readRawEntityMetadata($reader);

		self::assertSame($modernFlags, $decoded[EntityMetadataProperties::FLAGS]->getValue());
	}

	/**
	 * Full round trip through CommonTypes::getEntityMetadata() (as used by SetActorDataPacket::decodePayload())
	 * for a 223-tagged buffer: what goes out remapped to legacy bits must come back remapped to modern bits.
	 */
	public function testRoundTripThroughGetEntityMetadataRestoresModernBits() : void{
		$modernFlags = (1 << EntityMetadataFlags::AFFECTED_BY_GRAVITY) | (1 << EntityMetadataFlags::BREATHING);

		$writer = new ByteBufferWriter();
		CommonTypes::putEntityMetadata($writer, ProtocolInfo::PROTOCOL_1_2_13, [
			EntityMetadataProperties::FLAGS => new LongMetadataProperty($modernFlags),
		]);

		$reader = new ByteBufferReader($writer->getData());
		$decoded = CommonTypes::getEntityMetadata($reader, ProtocolInfo::PROTOCOL_1_2_13);

		self::assertSame($modernFlags, $decoded[EntityMetadataProperties::FLAGS]->getValue());
	}

	/**
	 * Reads metadata back without going through getEntityMetadata()'s own 223 remap, so the tests above can
	 * inspect the raw wire bit positions rather than the already-restored modern ones.
	 *
	 * @return \pocketmine\network\mcpe\protocol\types\entity\MetadataProperty[]
	 * @phpstan-return array<int, \pocketmine\network\mcpe\protocol\types\entity\MetadataProperty>
	 */
	private static function readRawEntityMetadata(ByteBufferReader $in) : array{
		//deliberately use a modern protocol ID here so getEntityMetadata() does NOT undo the remap we're
		//trying to observe - we want the bytes exactly as they'd sit on the wire for a 223 client.
		return CommonTypes::getEntityMetadata($in, ProtocolInfo::CURRENT_PROTOCOL);
	}
}
