<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

final class ArtifactRepository
{
	/** @var array<class-string, object> */
	private array $index;

	/** @param array<class-string, object> $index */
	private function __construct(array $index)
	{
		$this->index = $index;
	}

	public static function empty(): self
	{
		return new self([]);
	}

	/** @param array<class-string, object> $artifacts */
	public static function build(array $artifacts): self
	{
		return new self($artifacts);
	}

	/**
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T|null
	 */
	public function get(string $class): ?object
	{
		/** @var T|null */
		return $this->index[$class] ?? null;
	}

	/** @param class-string $class */
	public function has(string $class): bool
	{
		return isset($this->index[$class]);
	}
}
