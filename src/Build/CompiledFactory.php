<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

final readonly class CompiledFactory
{
	/** @var callable(): object */
	private mixed $creator;

	public function __construct(callable $creator)
	{
		$this->creator = $creator;
	}

	public function create(): object
	{
		return ($this->creator)();
	}
}
