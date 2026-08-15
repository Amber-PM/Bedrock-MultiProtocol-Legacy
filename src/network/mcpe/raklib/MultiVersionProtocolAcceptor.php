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

namespace pocketmine\network\mcpe\raklib;

use raklib\server\ProtocolAcceptor;
use function in_array;

/**
 * ProtocolAcceptor implementation that allows more than one RakNet wire protocol version to connect.
 * This only affects the RakLib handshake (raklib\protocol\OpenConnectionRequest1::$protocol) - it does NOT
 * affect the Minecraft: Bedrock Edition protocol version (ProtocolInfo::CURRENT_PROTOCOL) which is negotiated
 * afterwards at the MCPE layer. Old RakNet clients allowed through here can still be disconnected later if
 * their Minecraft protocol doesn't match what this server understands.
 */
final class MultiVersionProtocolAcceptor implements ProtocolAcceptor{

	/** @var int[] */
	private array $acceptedVersions;

	/**
	 * @param int[] $extraAcceptedVersions Additional RakNet protocol versions to accept besides $primaryVersion.
	 */
	public function __construct(
		private int $primaryVersion,
		array $extraAcceptedVersions = []
	){
		$this->acceptedVersions = $extraAcceptedVersions;
		$this->acceptedVersions[] = $primaryVersion;
	}

	public function accepts(int $protocolVersion) : bool{
		return in_array($protocolVersion, $this->acceptedVersions, true);
	}

	public function getPrimaryVersion() : int{
		return $this->primaryVersion;
	}
}
