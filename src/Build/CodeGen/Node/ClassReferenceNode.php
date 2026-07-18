<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build\CodeGen\Node;

use Rokke\Runtime\Build\CodeGen\NodeInterface;

final readonly class ClassReferenceNode implements NodeInterface
{
	public function __construct(public string $class) {}
}
