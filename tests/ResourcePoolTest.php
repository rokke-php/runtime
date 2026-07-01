<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\ResourcePool;
use RuntimeException;

final class ResourcePoolTest extends TestCase
{
	protected function setUp(): void
	{
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension is required for ResourcePool tests.');
		}
	}

	private function makePool(int $min = 0, int $max = 3, int $timeout = 1000): ResourcePool
	{
		$counter = 0;

		return new ResourcePool(
			name: 'test',
			factory: function () use (&$counter): \stdClass {
				$obj      = new \stdClass();
				$obj->id  = ++$counter;
				return $obj;
			},
			min: $min,
			max: $max,
			timeout: $timeout,
		);
	}

	public function testGetReturnsResourceInsideCoroutine(): void
	{
		$resource = null;

		\Swoole\Coroutine\run(function () use (&$resource): void {
			$pool     = $this->makePool();
			$resource = $pool->get();
		});

		$this->assertInstanceOf(\stdClass::class, $resource);
	}

	public function testGetThrowsOnClosedPool(): void
	{
		$exception = null;

		\Swoole\Coroutine\run(function () use (&$exception): void {
			$pool = $this->makePool();
			$pool->close();

			try {
				$pool->get();
			} catch (RuntimeException $e) {
				$exception = $e;
			}
		});

		$this->assertInstanceOf(RuntimeException::class, $exception);
		$this->assertStringContainsString('closed', $exception->getMessage());
	}

	public function testReleaseReturnsResourceToPoolForReuse(): void
	{
		$first  = null;
		$second = null;

		\Swoole\Coroutine\run(function () use (&$first, &$second): void {
			$pool   = $this->makePool(max: 1);
			$first  = $pool->get();
			$pool->release($first);
			$second = $pool->get();
		});

		$this->assertSame($first, $second);
	}

	public function testGetTimesOutWhenPoolExhausted(): void
	{
		$exception = null;

		\Swoole\Coroutine\run(function () use (&$exception): void {
			$pool = $this->makePool(max: 1, timeout: 50);
			$pool->get(); // exhausts the pool

			try {
				$pool->get(); // should timeout
			} catch (RuntimeException $e) {
				$exception = $e;
			}
		});

		$this->assertInstanceOf(RuntimeException::class, $exception);
		$this->assertStringContainsString('Timeout', $exception->getMessage());
	}

	public function testStatsReflectsPoolState(): void
	{
		$stats = null;

		\Swoole\Coroutine\run(function () use (&$stats): void {
			$pool = $this->makePool(min: 2, max: 5);
			$stats = $pool->stats();
		});

		$this->assertSame('test', $stats['name']);
		$this->assertSame(5, $stats['max']);
		$this->assertSame(2, $stats['min']);
		$this->assertSame(2, $stats['current_total']);
		$this->assertSame(2, $stats['idle']);
	}

	public function testMinResourcesCreatedOnConstruction(): void
	{
		$stats = null;

		\Swoole\Coroutine\run(function () use (&$stats): void {
			$pool  = $this->makePool(min: 3, max: 5);
			$stats = $pool->stats();
		});

		$this->assertSame(3, $stats['current_total']);
	}

	public function testPoolNameIsPreserved(): void
	{
		$name = null;

		\Swoole\Coroutine\run(function () use (&$name): void {
			$pool = new ResourcePool('connections', fn () => new \stdClass(), 0, 5, 1000);
			$name = $pool->name;
		});

		$this->assertSame('connections', $name);
	}
}
