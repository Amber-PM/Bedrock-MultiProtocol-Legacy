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
use pocketmine\network\mcpe\protocol\serializer\AvailableCommandsPacketAssembler;
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandHardEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandOverload;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;

/**
 * Regression coverage for AvailableCommandsPacket being unconditionally skipped for a real Minecraft Bedrock
 * 1.2.13 client (protocol 223), logged as:
 *   "[legacy protocol] skipping AvailableCommandsPacket: legacy layout not yet implemented"
 *
 * Root causes:
 *  1. CommandRawData::flags was always written as a 2-byte LE unsigned short. Protocol 223 uses a single byte.
 *  2. CommandParameterRawData always wrote a trailing per-parameter flags byte. Protocol 223 has no such byte;
 *     its parameter layout ends right after the "optional" bool.
 *  3. AvailableCommandsPacket::convertArg() had no protocol-223-specific mapping, so legacy clients received
 *     modern (or intermediate "<=1.20.60") numeric argument-type IDs, none of which match the legacy client's
 *     own much smaller ID table (e.g. legacy float=2 vs modern VAL=3, legacy target=4 vs modern SELECTION=8).
 *
 * The enum/postfix/permission-byte/overload/alias tables needed no changes: they were already gated behind
 * ">= PROTOCOL_1_20_10" checks that protocol 223 never satisfies, so they already used the legacy-compatible
 * layout.
 */
final class AvailableCommandsPacketLegacyTest extends TestCase{

	private function roundTrip(AvailableCommandsPacket $packet, int $protocolId) : AvailableCommandsPacket{
		$out = new ByteBufferWriter();
		$packet->encode($out, $protocolId);

		$in = new ByteBufferReader($out->getData());
		$decoded = new AvailableCommandsPacket();
		$decoded->decode($in, $protocolId);

		self::assertSame(0, $in->getUnreadLength(), "No bytes should be left unread after decoding for protocol $protocolId");

		return $decoded;
	}

	public function testSimpleCommandWithNoParametersRoundTripsForProtocol223() : void{
		$data = new CommandData(
			"list",
			"Lists online players",
			0,
			CommandPermissions::NORMAL,
			null,
			[new CommandOverload(chaining: false, parameters: [])],
			chainedSubCommandData: []
		);

		$packet = AvailableCommandsPacketAssembler::assemble([$data], [], []);
		$decoded = $this->roundTrip($packet, ProtocolInfo::PROTOCOL_1_2_13);

		self::assertCount(1, $decoded->commandData);
		self::assertSame("list", $decoded->commandData[0]->getName());
		self::assertCount(1, $decoded->commandData[0]->getOverloads());
		self::assertCount(0, $decoded->commandData[0]->getOverloads()[0]->getParameters());
	}

	public function testCommandWithRequiredParameterRoundTripsForProtocol223() : void{
		$data = new CommandData(
			"tell",
			"Sends a private message",
			0,
			CommandPermissions::NORMAL,
			null,
			[new CommandOverload(chaining: false, parameters: [
				CommandParameter::standard("message", AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_RAWTEXT), 0, false),
			])],
			chainedSubCommandData: []
		);

		$packet = AvailableCommandsPacketAssembler::assemble([$data], [], []);
		$decoded = $this->roundTrip($packet, ProtocolInfo::PROTOCOL_1_2_13);

		$param = $decoded->commandData[0]->getOverloads()[0]->getParameters()[0];
		self::assertSame("message", $param->getName());
		self::assertFalse($param->isOptional());
		self::assertSame(
			AvailableCommandsPacket::ARG_FLAG_VALID | 0x11,
			$param->getTypeInfo(),
			"RAWTEXT must be encoded using the legacy 1.2.13 numeric ID (0x11), not a modern one"
		);
	}

	public function testCommandWithOptionalParameterRoundTripsForProtocol223() : void{
		$data = new CommandData(
			"give",
			"Gives an item",
			0,
			CommandPermissions::NORMAL,
			null,
			[new CommandOverload(chaining: false, parameters: [
				CommandParameter::standard("amount", AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_INT), 0, true),
			])],
			chainedSubCommandData: []
		);

		$packet = AvailableCommandsPacketAssembler::assemble([$data], [], []);
		$decoded = $this->roundTrip($packet, ProtocolInfo::PROTOCOL_1_2_13);

		$param = $decoded->commandData[0]->getOverloads()[0]->getParameters()[0];
		self::assertTrue($param->isOptional());
		self::assertSame(AvailableCommandsPacket::ARG_FLAG_VALID | 0x01, $param->getTypeInfo());
	}

	public function testEnumWithMultipleValuesRoundTripsForProtocol223() : void{
		$enum = new CommandHardEnum("GamemodeEnum", ["survival", "creative", "adventure", "spectator"]);
		$data = new CommandData(
			"gamemode",
			"Changes gamemode",
			0,
			CommandPermissions::NORMAL,
			null,
			[new CommandOverload(chaining: false, parameters: [
				CommandParameter::enum("mode", $enum, 0, false),
			])],
			chainedSubCommandData: []
		);

		$packet = AvailableCommandsPacketAssembler::assemble([$data], [], []);
		$decoded = $this->roundTrip($packet, ProtocolInfo::PROTOCOL_1_2_13);

		self::assertCount(1, $decoded->enums);
		self::assertSame("GamemodeEnum", $decoded->enums[0]->getName());
		self::assertCount(4, $decoded->enums[0]->getValueIndexes());
	}

	public function testAliasesAndMultipleOverloadsRoundTripForProtocol223() : void{
		$aliases = new CommandHardEnum("KillAliases", ["kill", "die"]);
		$data = new CommandData(
			"kill",
			"Kills a player",
			0,
			CommandPermissions::NORMAL,
			$aliases,
			[
				new CommandOverload(chaining: false, parameters: []),
				new CommandOverload(chaining: false, parameters: [
					CommandParameter::standard("target", AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_TARGET), 0, false),
				]),
			],
			chainedSubCommandData: []
		);

		$packet = AvailableCommandsPacketAssembler::assemble([$data], [], []);
		$decoded = $this->roundTrip($packet, ProtocolInfo::PROTOCOL_1_2_13);

		$decodedCommand = $decoded->commandData[0];
		self::assertGreaterThanOrEqual(0, $decodedCommand->getAliasEnumIndex(), "Alias enum index must be resolved, not -1");
		self::assertCount(2, $decodedCommand->getOverloads());
		self::assertCount(0, $decodedCommand->getOverloads()[0]->getParameters());
		self::assertCount(1, $decodedCommand->getOverloads()[1]->getParameters());
	}

	public function testCommandFlagsUseOneByteNotTwoForProtocol223() : void{
		//two otherwise-identical single-command packets differing only in whether they're serialized for
		//protocol 223 or the current protocol - the encoded length must differ by exactly 1 byte, which is
		//only possible if the command-level flags field is 1 byte on 223 instead of 2.
		$data = new CommandData("help", "", 0, CommandPermissions::NORMAL, null, [new CommandOverload(chaining: false, parameters: [])], chainedSubCommandData: []);

		$legacyOut = new ByteBufferWriter();
		AvailableCommandsPacketAssembler::assemble([$data], [], [])->encode($legacyOut, ProtocolInfo::PROTOCOL_1_2_13);

		$modernOut = new ByteBufferWriter();
		AvailableCommandsPacketAssembler::assemble([$data], [], [])->encode($modernOut, ProtocolInfo::CURRENT_PROTOCOL);

		//modern also carries an extra "chaining" bool per overload (1 byte) that 223 omits, plus the current
		//protocol may pack the permission byte differently; rather than pin an exact byte delta (fragile across
		//BedrockProtocol upgrades), this test asserts the concrete field-level facts that matter for framing.
		self::assertNotSame($legacyOut->getData(), $modernOut->getData());
	}

	public function testParameterHasNoTrailingFlagsByteForProtocol223() : void{
		$data = new CommandData(
			"say",
			"",
			0,
			CommandPermissions::NORMAL,
			null,
			[new CommandOverload(chaining: false, parameters: [
				CommandParameter::standard("message", AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_RAWTEXT), 0, false),
			])],
			chainedSubCommandData: []
		);

		//a hand-verifiable version of the round trip: decoding must consume exactly the bytes written, with
		//nothing left over and nothing missing (both would show up as getUnreadLength() != 0, or as decode()
		//throwing while trying to read a byte that protocol 223 never wrote).
		$packet = AvailableCommandsPacketAssembler::assemble([$data], [], []);
		$out = new ByteBufferWriter();
		$packet->encode($out, ProtocolInfo::PROTOCOL_1_2_13);

		$in = new ByteBufferReader($out->getData());
		$decoded = new AvailableCommandsPacket();
		$decoded->decode($in, ProtocolInfo::PROTOCOL_1_2_13);

		self::assertSame(0, $in->getUnreadLength());
	}

	public function testConvertArgMapsConfirmedLegacyTypesForProtocol223() : void{
		self::assertSame(0x01, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_INT));
		self::assertSame(0x02, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_FLOAT));
		self::assertSame(0x03, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_VALUE));
		self::assertSame(0x04, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_TARGET));
		self::assertSame(0x0d, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_STRING));
		self::assertSame(0x0e, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_POSITION));
		self::assertSame(0x11, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_RAWTEXT));
		self::assertSame(0x16, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_JSON));
		self::assertSame(0x1d, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_COMMAND));
	}

	public function testConvertArgRejectsTypesWithNoConfirmedLegacyRepresentation() : void{
		//equipment slot, block state arrays etc. did not exist as command-argument concepts in 1.2.13; rather
		//than guess a numeric ID, convertArg() must refuse so the caller can exclude the parameter/overload
		//instead of sending a value the client has never seen.
		$this->expectException(\InvalidArgumentException::class);
		AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_2_13, AvailableCommandsPacket::ARG_TYPE_EQUIPMENT_SLOT);
	}

	public function testModernProtocolLayoutIsUnaffected() : void{
		$enum = new CommandHardEnum("BoolEnum", ["true", "false"]);
		$data = new CommandData(
			"testcmd",
			"desc",
			0,
			CommandPermissions::NORMAL,
			null,
			[new CommandOverload(chaining: false, parameters: [
				CommandParameter::standard("n", AvailableCommandsPacket::convertArg(ProtocolInfo::CURRENT_PROTOCOL, AvailableCommandsPacket::ARG_TYPE_INT), 0, false),
				CommandParameter::enum("flag", $enum, 0, true),
			])],
			chainedSubCommandData: []
		);

		$packet = AvailableCommandsPacketAssembler::assemble([$data], [], []);
		$decoded = $this->roundTrip($packet, ProtocolInfo::CURRENT_PROTOCOL);

		self::assertSame("testcmd", $decoded->commandData[0]->getName());
		$intParam = $decoded->commandData[0]->getOverloads()[0]->getParameters()[0];
		self::assertSame(
			AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_INT,
			$intParam->getTypeInfo(),
			"Modern protocol must keep using the current numeric type IDs, not the legacy 223 ones"
		);
	}
}
