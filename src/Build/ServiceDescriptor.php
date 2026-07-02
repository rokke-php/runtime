<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Build\DefinitionInterface;

final readonly class ServiceDescriptor implements DefinitionInterface
{
	/**
	 * @param class-string       $contract
	 * @param class-string       $implementation
	 * @param list<class-string> $aliases
	 */
	public function __construct(
		public string $contract,
		public string $implementation,
		public array $aliases,
	) {}
}
