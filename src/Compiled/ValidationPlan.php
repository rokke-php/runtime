<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

use Rokke\Runtime\Exception\ValidationException;

final readonly class ValidationPlan
{
	/** @param list<ParameterValidationPlan> $params */
	public function __construct(private array $params) {}

	/**
	 * @param list<mixed> $args Resolved handler arguments in positional order
	 * @throws ValidationException
	 */
	public function validate(array $args): void
	{
		foreach ($this->params as $param) {
			$param->validate($args[$param->index] ?? null);
		}
	}

	/** @return list<ParameterValidationPlan> */
	public function params(): array
	{
		return $this->params;
	}

	public function isEmpty(): bool
	{
		return $this->params === [];
	}

	public static function empty(): self
	{
		return new self([]);
	}
}
