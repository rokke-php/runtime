<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Runtime\Contracts\CoroutineManagerInterface;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\System;
use Swoole\Coroutine\WaitGroup;
use Throwable;

/**
 * Abstracts the underlying coroutine engine and provides a clean API
 * for managing concurrency, timeouts, and parallel execution.
 */
final class CoroutineManager implements CoroutineManagerInterface
{
	public function go(callable $callable, mixed ...$args): int
	{
		$coroutineId = Coroutine::create($callable, ...$args);

		if ($coroutineId === false) {
			throw new RuntimeException('Failed to spawn a new coroutine.');
		}

		return (int) $coroutineId;
	}

	/**
	 * @param array<callable(): mixed> $callables
	 * @return array<mixed>
	 */
	public function parallel(array $callables): array
	{
		$waitGroup = new WaitGroup();
		/** @var array<mixed> $results */
		$results = [];

		foreach ($callables as $key => $callable) {
			$waitGroup->add();

			$this->go(function () use ($waitGroup, $callable, $key, &$results): void {
				try {
					/** @var callable(): mixed $callable */
					$results[$key] = $callable();
				} finally {
					$waitGroup->done();
				}
			});
		}

		$waitGroup->wait();

		return $results;
	}

	public function wait(int $timeoutMs = -1): void
	{
		if ($timeoutMs > 0) {
			System::sleep($timeoutMs / 1000);

			return;
		}

		Coroutine::yield();
	}

	public function timeout(int $milliseconds, callable $callable): mixed
	{
		$channel = new Channel(1);

		$coroutineId = $this->go(function () use ($callable, $channel): void {
			try {
				$result = $callable();
				$channel->push(['success' => true, 'result' => $result]);
			} catch (Throwable $e) {
				$channel->push(['success' => false, 'error' => $e]);
			}
		});

		/** @var array{success: bool, result?: mixed, error?: Throwable}|false $response */
		$response = $channel->pop($milliseconds / 1000);

		if ($response === false) {
			Coroutine::cancel($coroutineId);
			throw new RuntimeException(sprintf('Execution timed out after %d ms.', $milliseconds));
		}

		if ($response['success'] === false) {
			throw $response['error']; // @phpstan-ignore-line
		}

		return $response['result'] ?? null;
	}

	public function cancel(int $coroutineId): bool
	{
		return (bool) Coroutine::cancel($coroutineId);
	}
}
