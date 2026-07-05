<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Resource\PoolConfig;
use Rokke\Runtime\ResourceManager;
use RuntimeException;

final class ResourceManagerTest extends TestCase
{
	private ResourceManager $manager;

	protected function setUp(): void
	{
		$this->manager = new ResourceManager();
	}

	public function testAllStatsOnEmptyManagerReturnsEmptyArray(): void
	{
		$this->assertSame([], $this->manager->allStats());
	}

	public function testThrowsWhenRegisteringDuplicatePool(): void
	{
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension is required for pool tests.');
		}

		$config = new PoolConfig('db', max: 5);
		$this->manager->register($config, fn () => new \stdClass());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('pool [db] is already registered');

		$this->manager->register($config, fn () => new \stdClass());
	}

	public function testAcquireThrowsForUnknownPool(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('pool [unknown]');

		$this->manager->acquire('unknown');
	}

	public function testReleaseThrowsForUnknownPool(): void
	{
		$this->expectException(RuntimeException::class);

		$this->manager->release('ghost', new \stdClass());
	}

	public function testStatsForUnknownPoolThrows(): void
	{
		$this->expectException(RuntimeException::class);

		$this->manager->stats('missing');
	}

	public function testCloseAllEmptiesPoolRegistry(): void
	{
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension is required for pool tests.');
		}

		$this->manager->register(new PoolConfig('cache', max: 2), fn () => new \stdClass());

		$this->manager->closeAll();

		$this->expectException(RuntimeException::class);

		$this->manager->stats('cache');
	}
}
