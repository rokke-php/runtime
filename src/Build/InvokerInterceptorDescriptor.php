<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Build\DefinitionInterface;

final readonly class InvokerInterceptorDescriptor implements DefinitionInterface
{
	/**
	 * @param class-string         $class    Must implement InvokerInterceptorInterface
	 * @param array<array-key, mixed> $args  Constructor args forwarded to new $class(...$args)
	 */
	public function __construct(
		public string $class,
		public int $priority = 0,
		public array $args = [],
	) {}
}
