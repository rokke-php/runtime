<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build\CodeGen\Node;

use Rokke\Runtime\Build\CodeGen\NodeInterface;

final readonly class StaticCallNode implements NodeInterface
{
	/**
	 * @param list<NodeInterface> $arguments
	 */
	public function __construct(
		public string $class,
		public string $method,
		public array $arguments = [],
	) {}
}
