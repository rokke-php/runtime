<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Build\DefinitionInterface;

final class ApplicationModel
{
	/** @var array<class-string, list<DefinitionInterface>> */
	private array $store = [];

	public function add(DefinitionInterface $definition): void
	{
		$this->store[$definition::class][] = $definition;
	}

	/**
	 * @template T of DefinitionInterface
	 * @param class-string<T> $type
	 * @return list<T>
	 */
	public function definitions(string $type): array
	{
		/** @var list<T> */
		return $this->store[$type] ?? [];
	}
}
