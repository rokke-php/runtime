<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Runtime\Contracts\OperationInterface;

final readonly class SimpleOperation implements OperationInterface
{
	public function __construct(
		private string $id,
		private string $name = '',
	) {}

	public function id(): string
	{
		return $this->id;
	}

	public function name(): string
	{
		return $this->name !== '' ? $this->name : $this->id;
	}

	public function metadata(string $key, mixed $default = null): mixed
	{
		return $default;
	}
}
