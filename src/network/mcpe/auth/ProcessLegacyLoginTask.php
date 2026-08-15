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

namespace pocketmine\network\mcpe\auth;

use pocketmine\lang\KnownTranslationFactory;
use pocketmine\lang\Translatable;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\scheduler\AsyncTask;
use pocketmine\thread\NonThreadSafeValue;
use pocketmine\utils\AssumptionFailedError;
use function base64_decode;
use function base64_encode;
use function igbinary_serialize;
use function igbinary_unserialize;
use function is_string;
use function json_encode;

class ProcessLegacyLoginTask extends AsyncTask{
	private const TLS_KEY_ON_COMPLETION = "completion";

	/**
	 * New Mojang root auth key. Mojang notified third-party developers of this change prior to the release of 1.20.0.
	 * Expectations were that this would be used starting a "couple of weeks" after the release, but as of 2023-07-01,
	 * it has not yet been deployed.
	 */
	public const LEGACY_MOJANG_ROOT_PUBLIC_KEY = "MHYwEAYHKoZIzj0CAQYFK4EEACIDYgAECRXueJeTDqNRRgJi/vlRufByu/2G0i2Ebt6YMar5QX/R0DIIyrJMcUpruK4QveTfJSTp3Shlq4Gk34cD/4GUWwkv0DVuzeuB+tXija7HBxii03NHDbPAD0AKnLr2wdAp";

	private string $chain;

	/**
	 * Whether the keychain signatures were validated correctly. This will be set to an error message if any link in the
	 * keychain is invalid for whatever reason (bad signature, not in nbf-exp window, etc). If this is non-null, the
	 * keychain might have been tampered with. The player will always be disconnected if this is non-null.
	 *
	 * @phpstan-var NonThreadSafeValue<Translatable>|string|null
	 */
	private NonThreadSafeValue|string|null $error = "Unknown";
	/** Whether the player has a certificate chain link signed by the given root public key. */
	private bool $authenticated = false;
	private ?string $clientPublicKeyDer = null;

	/**
	 * @param string[] $chainJwts
	 * @phpstan-param \Closure(bool $isAuthenticated, bool $authRequired, Translatable|string|null $error, ?string $clientPublicKey) : void $onCompletion
	 */
	public function __construct(
		array $chainJwts,
		private string $clientDataJwt,
		private ?string $rootAuthKeyDer,
		private bool $authRequired,
		\Closure $onCompletion
	){
		$this->storeLocal(self::TLS_KEY_ON_COMPLETION, $onCompletion);
		$this->chain = igbinary_serialize($chainJwts) ?? throw new AssumptionFailedError("This should never fail");
	}

	public function onRun() : void{
		try{
			$this->clientPublicKeyDer = $this->validateChain();
			AuthJwtHelper::validateSelfSignedToken($this->clientDataJwt, $this->clientPublicKeyDer);
			$this->error = null;
		}catch(VerifyLoginException $e){
			$disconnectMessage = $e->getDisconnectMessage();
			$this->error = $disconnectMessage instanceof Translatable ? new NonThreadSafeValue($disconnectMessage) : $disconnectMessage;
		}
	}

	private function validateChain() : string{
		/** @var string[] $chain */
		$chain = igbinary_unserialize($this->chain);

		$identityPublicKeyDer = null;
		$linkCount = count($chain);
		$rootKeyMatched = false;

		//TEMPORARY DIAGNOSTIC: dump the hardcoded root key so it can be diffed byte-for-byte against whatever a
		//real client's chain actually claims below. Remove once the correct root key value has been confirmed.
		\GlobalLogger::get()->debug("Legacy auth: hardcoded root key (base64) = " . ($this->rootAuthKeyDer !== null ? base64_encode($this->rootAuthKeyDer) : "null"));

		$linkIndex = 0;
		foreach($chain as $jwt){
			//The key that verifies THIS link: for the first link in the chain there is nothing to compare it
			//against yet, so it's the link's own self-claimed header key; for every subsequent link, it's the
			//identityPublicKey claimed by the previous (already-verified) link. A real Xbox Live chain has its
			//first link signed directly by Mojang's root key, so the root-key match must be tested against this
			//value - comparing anything else (e.g. an intermediate key from a later link, or a not-yet-assigned
			//null) can never match a genuine XBL chain and would leave every authenticated player undetected.
			$signingKeyDer = $identityPublicKeyDer ?? $this->extractSelfClaimedKey($jwt);

			//TEMPORARY DIAGNOSTIC: log this link's raw header/body (minus signature) plus the key that was just
			//computed above, so a real client's chain content can be inspected directly instead of guessed at.
			//Remove once the correct root key value has been confirmed.
			try{
				[$headersArray, $claimsArray, ] = JwtUtils::parse($jwt);
				\GlobalLogger::get()->debug(
					"Legacy auth chain link #$linkIndex: header=" . json_encode($headersArray)
					. " claims=" . json_encode($claimsArray)
					. " signingKey(base64)=" . base64_encode($signingKeyDer)
					. " matchesHardcodedRoot=" . ($this->rootAuthKeyDer !== null && $signingKeyDer === $this->rootAuthKeyDer ? "yes" : "no")
				);
			}catch(JwtException $e){
				\GlobalLogger::get()->debug("Legacy auth chain link #$linkIndex: failed to parse for diagnostics: " . $e->getMessage());
			}
			$linkIndex++;

			$claims = AuthJwtHelper::validateLegacyAuthToken($jwt, $identityPublicKeyDer);
			if($this->rootAuthKeyDer !== null && $signingKeyDer === $this->rootAuthKeyDer){
				$this->authenticated = true; //we're signed into xbox live, according to this root key
				$rootKeyMatched = true;
			}
			if(!isset($claims->identityPublicKey)){
				throw new VerifyLoginException("Missing identityPublicKey in chain link", KnownTranslationFactory::pocketmine_disconnect_invalidSession_missingKey());
			}
			$identityPublicKey = base64_decode($claims->identityPublicKey, true);
			if($identityPublicKey === false){
				throw new VerifyLoginException("Invalid identityPublicKey: base64 error decoding");
			}
			$identityPublicKeyDer = $identityPublicKey;
		}

		if($identityPublicKeyDer === null){
			throw new VerifyLoginException("No authentication chain links provided");
		}

		//diagnostic only: does the chain's link count and root-key match look like a real XBL chain
		//or a purely offline self-signed one? Remove once auth-required testing is done.
		\GlobalLogger::get()->debug("Legacy auth chain: $linkCount link(s), matched hardcoded root key: " . ($rootKeyMatched ? "yes" : "no"));

		return $identityPublicKeyDer;
	}

	/**
	 * Reads the "x5u" (self-claimed signing key) field out of a chain link's JWT header, without verifying the
	 * signature. Used only to determine which key was actually used to sign a link, for the root-key comparison
	 * in {@link self::validateChain()}.
	 *
	 * @throws VerifyLoginException
	 */
	private function extractSelfClaimedKey(string $jwt) : string{
		try{
			[$headersArray, ] = JwtUtils::parse($jwt);
		}catch(JwtException $e){
			throw new VerifyLoginException("Failed to parse JWT: " . $e->getMessage(), null, 0, $e);
		}
		$x5u = $headersArray["x5u"] ?? null;
		if(!is_string($x5u)){
			throw new VerifyLoginException("Missing x5u in JWT header");
		}
		$key = base64_decode($x5u, true);
		if($key === false){
			throw new VerifyLoginException("Invalid x5u: base64 error decoding");
		}
		return $key;
	}

	public function onCompletion() : void{
		/**
		 * @var \Closure $callback
		 * @phpstan-var \Closure(bool, bool, Translatable|string|null, ?string) : void $callback
		 */
		$callback = $this->fetchLocal(self::TLS_KEY_ON_COMPLETION);
		$callback($this->authenticated, $this->authRequired, $this->error instanceof NonThreadSafeValue ? $this->error->deserialize() : $this->error, $this->clientPublicKeyDer);
	}
}
