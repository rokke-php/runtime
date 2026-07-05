<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Resource;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Resource\PoolConfig;
use Rokke\Runtime\Resource\ResourceValidatorInterface;
use Rokke\Runtime\ResourcePool;
use RuntimeException;

/**
 * Tests for Resource Pool v2 features:
 *   - ResourceValidatorInterface health-check
 *   - maxAge eviction (clock-injected, deterministic)
 *   - Rich PoolStats counters (acquired, created, errors, validationFails, evicted)
 *   - drain()
 *
 * All tests require the Swoole extension.
 */
final class ResourcePoolV2Test extends TestCase
{
	protected function setUp(): void
	{
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension is required for ResourcePool tests.');
		}
	}

	// ── helpers ───────────────────────────────────────────────────────────────

	private function makePool(
		PoolConfig $config,
		?callable $clock = null,
	): ResourcePool {
		$counter = 0;

		return new ResourcePool(
			config: $config,
			factory: function () use (&$counter): \stdClass {
				$obj     = new \stdClass();
				$obj->id = ++$counter;

				return $obj;
			},
			clock: $clock,
		);
	}

	/** @return array{pool: ResourcePool, clock: float} */
	private function makePoolWithClock(PoolConfig $config): array
	{
		$now  = 1_000_000.0;
		$pool = $this->makePool($config, fn (): float => $now);

		return ['pool' => $pool, 'clock' => &$now];
	}

	// ── validation ────────────────────────────────────────────────────────────

	public function testValidResourceIsReusedWhenValidatorPasses(): void
	{
		$validator = $this->createMock(ResourceValidatorInterface::class);
		$validator->method('validate')->willReturn(true);

		$config = new PoolConfig('test', max: 3, validator: $validator);

		$first  = null;
		$second = null;

		\Swoole\Coroutine\run(function () use ($config, &$first, &$second): void {
			$pool  = $this->makePool($config);
			$first = $pool->get();
			$pool->release($first);
			$second = $pool->get();
		});

		$this->assertSame($first, $second, 'Valid resource must be reused from pool');
	}

	public function testInvalidResourceIsDiscardedAndNewOneCreated(): void
	{
		$calls = 0;

		$validator = $this->createMock(ResourceValidatorInterface::class);
		$validator->method('validate')->willReturnCallback(function () use (&$calls): bool {
			return ++$calls > 1; // First call fails (resource #1), second succeeds (resource #2)
		});

		$config = new PoolConfig('test', max: 3, validator: $validator);

		$first  = null;
		$second = null;

		\Swoole\Coroutine\run(function () use ($config, &$first, &$second): void {
			$pool  = $this->makePool($config);
			$first = $pool->get();
			$pool->release($first);
			$second = $pool->get();
		});

		$this->assertNotSame($first, $second, 'Invalid resource must be replaced with a new one');
	}

	public function testValidationFailsIncrementsStat(): void
	{
		$calls = 0;

		$validator = $this->createMock(ResourceValidatorInterface::class);
		$validator->method('validate')->willReturnCallback(function () use (&$calls): bool {
			return ++$calls > 1;
		});

		$config = new PoolConfig('test', max: 3, validator: $validator);
		$stats  = null;

		\Swoole\Coroutine\run(function () use ($config, &$stats): void {
			$pool = $this->makePool($config);
			$r    = $pool->get();
			$pool->release($r);
			$pool->get(); // validation fails for released resource
			$stats = $pool->stats();
		});

		$this->assertSame(1, $stats->validationFails);
	}

	// ── maxAge eviction ───────────────────────────────────────────────────────

	public function testResourceWithinMaxAgeIsReused(): void
	{
		$config = new PoolConfig('test', max: 3, maxAge: 60);

		$first  = null;
		$second = null;

		\Swoole\Coroutine\run(function () use ($config, &$first, &$second): void {
			$now  = 1_000_000.0;
			$pool = new ResourcePool(
				config: $config,
				factory: function (): \stdClass {
					return new \stdClass();
				},
				clock: fn (): float => $now,
			);

			$first = $pool->get();
			$pool->release($first);

			$now += 30.0; // 30s — within maxAge of 60

			$second = $pool->get();
		});

		$this->assertSame($first, $second);
	}

	public function testResourceOlderThanMaxAgeIsEvicted(): void
	{
		$config = new PoolConfig('test', max: 3, maxAge: 60);

		$first  = null;
		$second = null;

		\Swoole\Coroutine\run(function () use ($config, &$first, &$second): void {
			$now  = 1_000_000.0;
			$pool = new ResourcePool(
				config: $config,
				factory: function (): \stdClass {
					return new \stdClass();
				},
				clock: fn (): float => $now,
			);

			$first = $pool->get();
			$pool->release($first);

			$now += 61.0; // 61s — past maxAge of 60

			$second = $pool->get();
		});

		$this->assertNotSame($first, $second, 'Expired resource must be replaced');
	}

	public function testMaxAgeEvictionIncrementsStat(): void
	{
		$config = new PoolConfig('test', max: 3, maxAge: 60);
		$stats  = null;

		\Swoole\Coroutine\run(function () use ($config, &$stats): void {
			$now  = 1_000_000.0;
			$pool = new ResourcePool(
				config: $config,
				factory: function (): \stdClass {
					return new \stdClass();
				},
				clock: fn (): float => $now,
			);

			$r = $pool->get();
			$pool->release($r);

			$now += 61.0;

			$pool->get(); // old resource evicted, new one created
			$stats = $pool->stats();
		});

		$this->assertSame(1, $stats->evicted);
	}

	// ── stats counters ────────────────────────────────────────────────────────

	public function testStatsTracksAcquiredCount(): void
	{
		$config = new PoolConfig('test', max: 5);
		$stats  = null;

		\Swoole\Coroutine\run(function () use ($config, &$stats): void {
			$pool = $this->makePool($config);

			$r1 = $pool->get();
			$r2 = $pool->get();
			$pool->release($r1);
			$pool->release($r2);
			$pool->get();

			$stats = $pool->stats();
		});

		$this->assertSame(3, $stats->acquired);
	}

	public function testStatsTracksCreatedCount(): void
	{
		$config = new PoolConfig('test', min: 2, max: 5);
		$stats  = null;

		\Swoole\Coroutine\run(function () use ($config, &$stats): void {
			$pool = $this->makePool($config);
			$pool->get();
			$stats = $pool->stats();
		});

		// min=2 created on construction + 1 from get() = 3
		$this->assertSame(3, $stats->created);
	}

	public function testStatsTracksErrors(): void
	{
		$fail   = true;
		$config = new PoolConfig('test', max: 5);
		$stats  = null;

		\Swoole\Coroutine\run(function () use ($config, &$fail, &$stats): void {
			$pool = new ResourcePool(
				config: $config,
				factory: function () use (&$fail): \stdClass {
					if ($fail) {
						$fail = false;
						throw new \RuntimeException('factory error');
					}

					return new \stdClass();
				},
			);

			try {
				$pool->get();
			} catch (\RuntimeException) {
				// expected
			}

			$pool->get(); // second call succeeds
			$stats = $pool->stats();
		});

		$this->assertSame(1, $stats->errors);
	}

	// ── drain ─────────────────────────────────────────────────────────────────

	public function testDrainReturnsWhenAllResourcesAreIdle(): void
	{
		$config    = new PoolConfig('test', max: 3);
		$completed = false;

		\Swoole\Coroutine\run(function () use ($config, &$completed): void {
			$pool = $this->makePool($config);

			$r1 = $pool->get();
			$r2 = $pool->get();

			\Swoole\Coroutine::create(function () use ($pool, $r1, $r2): void {
				\Swoole\Coroutine::sleep(0.02);
				$pool->release($r1);
				$pool->release($r2);
			});

			$pool->drain(timeout: 2.0);
			$completed = true;
		});

		$this->assertTrue($completed);
	}

	public function testDrainThrowsWhenTimeoutExceeded(): void
	{
		$config    = new PoolConfig('test', max: 3);
		$exception = null;

		\Swoole\Coroutine\run(function () use ($config, &$exception): void {
			$pool = $this->makePool($config);
			$pool->get(); // never released

			try {
				$pool->drain(timeout: 0.05);
			} catch (RuntimeException $e) {
				$exception = $e;
			}
		});

		$this->assertInstanceOf(RuntimeException::class, $exception);
		$this->assertStringContainsString('drain', strtolower($exception->getMessage()));
	}

	// ── PoolManagerInterface integration ─────────────────────────────────────

	public function testStatsReturnedFromPoolUsesTypedObject(): void
	{
		$config = new PoolConfig('test', min: 1, max: 5);
		$stats  = null;

		\Swoole\Coroutine\run(function () use ($config, &$stats): void {
			$pool  = $this->makePool($config);
			$stats = $pool->stats();
		});

		$this->assertSame('test', $stats->name);
		$this->assertSame(5, $stats->max);
		$this->assertSame(1, $stats->min);
		$this->assertSame(1, $stats->currentTotal);
		$this->assertSame(1, $stats->idle);
		$this->assertSame(0, $stats->waitingCoroutines);
	}
}
