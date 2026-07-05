<?php

declare(strict_types=1);

namespace Rokke\Runtime\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Min
{
	public function __construct(public int|float $value) {}
}
