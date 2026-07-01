<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Contracts\Context\ContextInterface;

final class Context implements ContextInterface
{
	/** @var list<callable> */
	private array $destroyCallbacks = [];

	/** @param array<string, mixed> $storage */
	public function __construct(
		private readonly string $id,
		private array $storage = []
	) {}

	public function id(): string
	{
		return $this->id;
	}

	public function set(string $key, mixed $value): void
	{
		$this->storage[$key] = $value;
	}

	public function get(string $key, mixed $default = null): mixed
	{
		return $this->storage[$key] ?? $default;
	}

	public function has(string $key): bool
	{
		return array_key_exists($key, $this->storage);
	}

	public function onDestroy(callable $callback): void
	{
		$this->destroyCallbacks[] = $callback;
	}

	public function destroy(): void
	{
		$callbacks              = $this->destroyCallbacks;
		$this->destroyCallbacks = [];
		$this->storage          = [];

		foreach ($callbacks as $callback) {
			try {
				$callback();
			} catch (\Throwable) {
				// Best-effort cleanup; don't let one failure abort others
			}
		}
	}
}
