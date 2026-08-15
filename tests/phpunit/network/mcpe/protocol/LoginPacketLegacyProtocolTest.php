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
use pmmp\encoding\BE;
use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use function base64_decode;
use function hex2bin;
use function str_repeat;
use function strlen;
use function substr;

/**
 * Regression coverage for the header-framing bug that made a real Minecraft Bedrock 1.2.13 client (protocol 223)
 * fail to connect with "Need at least 4 bytes, but only have 0 bytes" while decoding LoginPacket.
 *
 * Root cause: protocol 223 predates split-screen support, so its LoginPacket header is three separate fields -
 * an unsigned VarInt packet ID, then one raw byte each for senderSubId and recipientSubId - whereas every
 * protocol from 1.16 onward packs the packet ID and both subclient IDs into a single VarInt. Since the client's
 * protocol version isn't known until the LoginPacket itself has been decoded, the server used to always decode
 * the first packet with the modern packed-header format, silently consuming 2 bytes fewer than the 223 client
 * actually sent and shifting every field after them by 2 bytes.
 */
final class LoginPacketLegacyProtocolTest extends TestCase{

	/**
	 * The first 20 bytes of the real LoginPacket captured in the crash log (base64, truncated by the server's
	 * own debug logging at 1024 bytes - the header and protocol field are intact within that prefix).
	 *
	 * Verified byte-for-byte offline:
	 *  - byte 0            : 0x01 -> packet ID VarInt (LOGIN_PACKET), identical under both header formats
	 *  - bytes 1-2         : 0x00 0x00 -> legacy senderSubId / recipientSubId raw bytes
	 *  - bytes 3-6 (BE)    : 0x000000DF = 223 -> the real protocol version
	 *
	 * Decoding this buffer with the OLD (modern-only) DataPacket::decodeHeader() reads bytes 1-4 as the protocol
	 * field instead, which is 0x00000000 = 0 - exactly the "protocolo 0" symptom reported against this server.
	 */
	private const REAL_CRASH_LOGIN_PACKET_PREFIX_B64 = "AQAAAAAA357iAkMDAAB7ImNoYWlu";

	public function testRealCrashPayloadIsSniffedAsProtocol223() : void{
		$buffer = base64_decode(self::REAL_CRASH_LOGIN_PACKET_PREFIX_B64, true);
		self::assertNotFalse($buffer, "Test fixture is not valid base64");

		self::assertSame(
			ProtocolInfo::PROTOCOL_1_2_13,
			LoginPacket::sniffLegacyProtocolId($buffer),
			"The real captured 1.2.13 client payload must be recognised as protocol 223 before decoding"
		);
	}

	public function testRealCrashPayloadHeaderDecodesToProtocol223NotZero() : void{
		$buffer = base64_decode(self::REAL_CRASH_LOGIN_PACKET_PREFIX_B64, true);
		self::assertNotFalse($buffer, "Test fixture is not valid base64");

		$in = new ByteBufferReader($buffer);
		$packet = new LoginPacket();

		//decodeHeader() alone, using the detected protocol - this is the exact call NetworkSession now makes
		self::invokeDecodeHeader($packet, $in, ProtocolInfo::PROTOCOL_1_2_13);

		self::assertSame(0, $packet->senderSubId);
		self::assertSame(0, $packet->recipientSubId);

		$protocol = BE::readUnsignedInt($in);
		self::assertSame(223, $protocol, "Protocol field must decode to 223, not 0");
	}

	public function testModernFormatPacketIsNotMisdetectedAsLegacy() : void{
		//a modern (packed-header) LoginPacket for some ordinary current protocol. Chosen so its first 2
		//big-endian bytes are also 0x00 0x00 (current protocol IDs are all well under 65536), which is the
		//exact scenario that could cause a false-positive legacy match if the sniff didn't also check the
		//full 4-byte protocol value.
		$out = new ByteBufferWriter();
		VarInt::writeUnsignedInt($out, LoginPacket::NETWORK_ID); //packed header, subIds = 0 -> same 1 byte
		BE::writeUnsignedInt($out, 800); //modern protocol field, top 2 bytes are 0x00 0x00
		$out->writeByteArray(str_repeat("\x00", 8));

		self::assertNull(
			LoginPacket::sniffLegacyProtocolId($out->getData()),
			"A modern-format packet must never be misdetected as legacy protocol 223"
		);
	}

	public function testNonLoginPacketIsNotSniffed() : void{
		$out = new ByteBufferWriter();
		VarInt::writeUnsignedInt($out, ProtocolInfo::REQUEST_NETWORK_SETTINGS_PACKET);
		Byte::writeUnsigned($out, 0);
		Byte::writeUnsigned($out, 0);
		BE::writeUnsignedInt($out, 223);

		self::assertNull(LoginPacket::sniffLegacyProtocolId($out->getData()));
	}

	public function testTruncatedBufferIsRejectedCleanlyNotByThrowing() : void{
		self::assertNull(LoginPacket::sniffLegacyProtocolId(""));
		self::assertNull(LoginPacket::sniffLegacyProtocolId("\x01"));
		self::assertNull(LoginPacket::sniffLegacyProtocolId("\x01\x00\x00"));
	}

	public function testLegacyProtocol223RoundTrip() : void{
		$authInfoJson = '{"chain":["fake.jwt.one"]}';
		$clientDataJwt = "fake.client.jwt";

		$out = new ByteBufferWriter();
		VarInt::writeUnsignedInt($out, LoginPacket::NETWORK_ID);
		Byte::writeUnsigned($out, 0);
		Byte::writeUnsigned($out, 0);
		BE::writeUnsignedInt($out, ProtocolInfo::PROTOCOL_1_2_13);

		$connReq = new ByteBufferWriter();
		LE::writeUnsignedInt($connReq, strlen($authInfoJson));
		$connReq->writeByteArray($authInfoJson);
		LE::writeUnsignedInt($connReq, strlen($clientDataJwt));
		$connReq->writeByteArray($clientDataJwt);
		CommonTypes::putString($out, $connReq->getData());

		$buffer = $out->getData();

		self::assertSame(ProtocolInfo::PROTOCOL_1_2_13, LoginPacket::sniffLegacyProtocolId($buffer));

		$in = new ByteBufferReader($buffer);
		$packet = new LoginPacket();
		$packet->decode($in, ProtocolInfo::PROTOCOL_1_2_13);

		self::assertSame(ProtocolInfo::PROTOCOL_1_2_13, $packet->protocol);
		self::assertSame($authInfoJson, $packet->authInfoJson);
		self::assertSame($clientDataJwt, $packet->clientDataJwt);
		self::assertSame(0, $in->getUnreadLength(), "No bytes should be left unread for a well-formed legacy packet");
	}

	public function testModernProtocolRoundTripIsUnaffected() : void{
		$packet = LoginPacket::create(ProtocolInfo::CURRENT_PROTOCOL, '{"chain":[]}', "jwt");

		$out = new ByteBufferWriter();
		$packet->encode($out, ProtocolInfo::CURRENT_PROTOCOL);
		$buffer = $out->getData();

		//modern header for a packet with subId 0 is exactly 1 byte (the packed VarInt) - confirms the legacy
		//branch introduced by this fix does not affect the modern wire format at all
		self::assertSame("\x01", substr($buffer, 0, 1));

		$in = new ByteBufferReader($buffer);
		$decoded = new LoginPacket();
		$decoded->decode($in, ProtocolInfo::CURRENT_PROTOCOL);

		self::assertSame(ProtocolInfo::CURRENT_PROTOCOL, $decoded->protocol);
		self::assertSame('{"chain":[]}', $decoded->authInfoJson);
		self::assertSame("jwt", $decoded->clientDataJwt);
	}

	private static function invokeDecodeHeader(LoginPacket $packet, ByteBufferReader $in, int $protocolId) : void{
		$method = new \ReflectionMethod($packet, "decodeHeader");
		$method->setAccessible(true);
		$method->invoke($packet, $in, $protocolId);
	}
}
