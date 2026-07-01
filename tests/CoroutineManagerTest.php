<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\CoroutineManager;
use RuntimeException;

final class CoroutineManagerTest extends TestCase
{
	private CoroutineManager $manager;

	protected function setUp(): void
	{
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension is required for CoroutineManager tests.');
		}

		$this->manager = new CoroutineManager();
	}

	public function testGoSpawnsCoroutineAndReturnsIntId(): void
	{
		$coroutineId = null;

		\Swoole\Coroutine\run(function () use (&$coroutineId): void {
			$channel = new \Swoole\Coroutine\Channel(1);

			$coroutineId = $this->manager->go(function () use ($channel): void {
				$channel->push(true);
			});

			$channel->pop(1.0);
		});

		$this->assertIsInt($coroutineId);
		$this->assertGreaterThan(0, $coroutineId);
	}

	public function testParallelRunsAllCallablesAndReturnsIndexedResults(): void
	{
		$results = null;

		\Swoole\Coroutine\run(function () use (&$results): void {
			$results = $this->manager->parallel([
				'a' => fn () => 'result-a',
				'b' => fn () => 'result-b',
				'c' => fn () => 'result-c',
			]);
		});

		$this->assertSame(['a' => 'result-a', 'b' => 'result-b', 'c' => 'result-c'], $results);
	}

	public function testParallelWithEmptyArrayReturnsEmptyArray(): void
	{
		$results = null;

		\Swoole\Coroutine\run(function () use (&$results): void {
			$results = $this->manager->parallel([]);
		});

		$this->assertSame([], $results);
	}

	public function testTimeoutReturnsCallableResultWhenCompleteInTime(): void
	{
		$result = null;

		\Swoole\Coroutine\run(function () use (&$result): void {
			$result = $this->manager->timeout(1000, fn () => 'done');
		});

		$this->assertSame('done', $result);
	}

	public function testTimeoutThrowsRuntimeExceptionOnExpiry(): void
	{
		$exception = null;

		\Swoole\Coroutine\run(function () use (&$exception): void {
			try {
				$this->manager->timeout(50, function (): void {
					\Swoole\Coroutine\System::sleep(1.0);
				});
			} catch (RuntimeException $e) {
				$exception = $e;
			}
		});

		$this->assertInstanceOf(RuntimeException::class, $exception);
		$this->assertStringContainsString('timed out', $exception->getMessage());
	}

	public function testTimeoutRethrowsExceptionThrownByCallable(): void
	{
		$exception = null;

		\Swoole\Coroutine\run(function () use (&$exception): void {
			try {
				$this->manager->timeout(1000, function (): void {
					throw new \InvalidArgumentException('callable error');
				});
			} catch (\InvalidArgumentException $e) {
				$exception = $e;
			}
		});

		$this->assertInstanceOf(\InvalidArgumentException::class, $exception);
		$this->assertSame('callable error', $exception->getMessage());
	}

	public function testCancelReturnsBooleanResult(): void
	{
		$result = null;

		\Swoole\Coroutine\run(function () use (&$result): void {
			$channel = new \Swoole\Coroutine\Channel(1);

			$id = $this->manager->go(function () use ($channel): void {
				\Swoole\Coroutine\System::sleep(10.0);
				$channel->push(true);
			});

			$result = $this->manager->cancel($id);
		});

		$this->assertIsBool($result);
	}

	public function testWaitWithTimeoutSleeps(): void
	{
		$elapsed = null;

		\Swoole\Coroutine\run(function () use (&$elapsed): void {
			$start   = microtime(true);
			$this->manager->wait(50);
			$elapsed = (microtime(true) - $start) * 1000;
		});

		$this->assertGreaterThanOrEqual(40, $elapsed);
	}
}
