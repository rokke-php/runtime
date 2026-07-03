<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

final class OperationRepository
{
	/** @var array<string, CompiledOperation> */
	private array $index = [];

	private function __construct() {}

	public static function empty(): self
	{
		return new self();
	}

	/** @param list<CompiledOperation> $operations */
	public static function build(array $operations): self
	{
		$repo = new self();

		foreach ($operations as $operation) {
			if (isset($repo->index[$operation->id])) {
				throw new \RuntimeException(
					"Duplicate operation id '{$operation->id}'. Each operation must have a unique identifier.",
				);
			}

			$repo->index[$operation->id] = $operation;
		}

		return $repo;
	}

	public function find(string $id): ?CompiledOperation
	{
		return $this->index[$id] ?? null;
	}

	public function has(string $id): bool
	{
		return isset($this->index[$id]);
	}
}
