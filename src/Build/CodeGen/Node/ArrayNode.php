<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build\CodeGen\Node;

use Rokke\Runtime\Build\CodeGen\NodeInterface;

final readonly class ArrayNode implements NodeInterface
{
	/**
	 * @param array<int|string, NodeInterface> $items
	 */
	public function __construct(public array $items) {}
}
