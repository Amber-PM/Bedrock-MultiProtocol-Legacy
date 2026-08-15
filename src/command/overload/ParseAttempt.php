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

namespace pocketmine\command\overload;

use pocketmine\lang\Translatable;

final class ParseAttempt{

	private function __construct(
		private bool $success,
		private array $values,
		private Translatable|string|null $error
	){}

	public static function ok(array $values) : self{
		return new self(true, $values, null);
	}

	public static function fail(Translatable|string $error) : self{
		return new self(false, [], $error);
	}

	public function isOk() : bool{
		return $this->success;
	}

	public function getValues() : array{
		return $this->values;
	}

	public function getError() : Translatable|string{
		return $this->error ?? "";
	}
}
