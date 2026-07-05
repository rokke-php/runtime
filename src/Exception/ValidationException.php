<?php

declare(strict_types=1);

namespace Rokke\Runtime\Exception;

final class ValidationException extends \RuntimeException
{
	public function __construct(
		public readonly string $param,
		string $message,
	) {
		parent::__construct($message);
	}
}
