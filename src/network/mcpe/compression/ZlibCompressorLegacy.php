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

namespace pocketmine\network\mcpe\compression;

use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use function zlib_decode;
use function zlib_encode;
use const ZLIB_ENCODING_DEFLATE;

/**
 * Protocol 223 (1.2.13/1.2.16) clients compress/decompress batches using the classic zlib format
 * (2-byte header + adler32 trailer), not the headerless raw-deflate format used by modern clients.
 * Sending raw-deflate to these clients is silently discarded client-side: the client ACKs the RakNet
 * datagram (transport layer succeeds) but the zlib inflate fails, so no ResourcePackClientResponsePacket
 * (or any further packet) is ever produced, and the client eventually disconnects on its own.
 */
final class ZlibCompressorLegacy implements Compressor{
	use SingletonTrait;

	public const DEFAULT_LEVEL = 7;

	private static function make() : self{
		return new self(self::DEFAULT_LEVEL);
	}

	public function __construct(
		private int $level
	){}

	public function getCompressionThreshold() : ?int{
		return null;
	}

	public function decompress(string $payload) : string{
		$result = @zlib_decode($payload, 64 * 1024 * 1024);
		if($result === false){
			throw new DecompressionException("Failed to decompress data");
		}
		return $result;
	}

	public function compress(string $payload) : string{
		return Utils::assumeNotFalse(zlib_encode($payload, ZLIB_ENCODING_DEFLATE, $this->level), "ZLIB compression failed");
	}

	public function getNetworkId() : int{
		return CompressionAlgorithm::ZLIB;
	}
}
