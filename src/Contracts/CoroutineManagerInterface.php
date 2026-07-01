<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Manages concurrent execution.
 * Handles Coroutine Local Storage, Context propagation, cancellation, and timeouts.
 */
interface CoroutineManagerInterface
{
	public function go(callable $callable, mixed ...$args): int;

	/**
	 * @param array<callable(): mixed> $callables
	 * @return array<mixed>
	 */
	public function parallel(array $callables): array;

	public function wait(int $timeoutMs = -1): void;

	public function timeout(int $milliseconds, callable $callable): mixed;

	public function cancel(int $coroutineId): bool;
}
