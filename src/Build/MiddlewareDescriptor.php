<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Build\DefinitionInterface;

final readonly class MiddlewareDescriptor implements DefinitionInterface
{
	/**
	 * @param class-string         $class    Must implement MiddlewareInterface
	 * @param array<string, mixed> $args     Constructor args forwarded to new $class(...$args)
	 */
	public function __construct(
		public string $class,
		public int $priority = 0,
		public array $args = [],
	) {}
}
