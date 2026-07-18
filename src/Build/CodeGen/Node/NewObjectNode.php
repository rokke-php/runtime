<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build\CodeGen\Node;

use Rokke\Runtime\Build\CodeGen\NodeInterface;

final readonly class NewObjectNode implements NodeInterface
{
	/**
	 * @param array<string, NodeInterface> $arguments named arguments
	 */
	public function __construct(public string $class, public array $arguments = []) {}
}
