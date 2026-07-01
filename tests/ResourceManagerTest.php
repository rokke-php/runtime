<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\ResourceManager;
use RuntimeException;

final class ResourceManagerTest extends TestCase
{
	private ResourceManager $manager;

	protected function setUp(): void
	{
		$this->manager = new ResourceManager();
	}

	public function testStatsOnEmptyManagerReturnsEmptyArray(): void
	{
		$this->assertSame([], $this->manager->stats());
	}

	public function testThrowsWhenRegisteringDuplicatePool(): void
	{
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension is required for pool tests.');
		}

		$this->manager->registerPool('db', fn () => new \stdClass(), 0, 5, 1000);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('pool [db] is already registered');

		$this->manager->registerPool('db', fn () => new \stdClass(), 0, 5, 1000);
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

		$this->manager->registerPool('cache', fn () => new \stdClass(), 0, 2, 1000);

		$this->manager->closeAll();

		$this->expectException(RuntimeException::class);

		$this->manager->stats('cache');
	}
}
