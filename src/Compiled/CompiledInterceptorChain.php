<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

final readonly class CompiledInterceptorChain
{
	/**
	 * @param list<callable(OperationInterface, OperationContextInterface, list<mixed>, callable(list<mixed>): mixed): mixed> $stages
	 *        Ordered interceptor callables, instances bound at build time
	 */
	public function __construct(public array $stages) {}

	public function isEmpty(): bool
	{
		return $this->stages === [];
	}

	public static function empty(): self
	{
		return new self([]);
	}
}
