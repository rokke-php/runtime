<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Context;
use Rokke\Runtime\ContextManager;
use RuntimeException;

final class ContextManagerTest extends TestCase
{
	private ContextManager $manager;

	protected function setUp(): void
	{
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension is required for ContextManager tests.');
		}

		$this->manager = new ContextManager();
	}

	public function testCurrentThrowsOutsideCoroutine(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Context can only be accessed inside an active coroutine.');

		$this->manager->current();
	}

	public function testCreateThrowsOutsideCoroutine(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Context must be initialized inside a coroutine.');

		$this->manager->create();
	}

	public function testDestroyCurrentSilentlyDoesNothingOutsideCoroutine(): void
	{
		$this->manager->destroyCurrent();

		$this->assertTrue(true); // No exception thrown
	}

	public function testCreateReturnsContextInsideCoroutine(): void
	{
		$context = null;

		\Swoole\Coroutine\run(function () use (&$context): void {
			$context = $this->manager->create();
		});

		$this->assertInstanceOf(Context::class, $context);
	}

	public function testCurrentReturnsContextAfterCreate(): void
	{
		$created  = null;
		$resolved = null;

		\Swoole\Coroutine\run(function () use (&$created, &$resolved): void {
			$created  = $this->manager->create();
			$resolved = $this->manager->current();
		});

		$this->assertSame($created, $resolved);
	}

	public function testCurrentThrowsWhenContextNotInitializedInCoroutine(): void
	{
		$exception = null;

		\Swoole\Coroutine\run(function () use (&$exception): void {
			try {
				$this->manager->current();
			} catch (RuntimeException $e) {
				$exception = $e;
			}
		});

		$this->assertInstanceOf(RuntimeException::class, $exception);
		$this->assertStringContainsString('Context has not been initialized', $exception->getMessage());
	}

	public function testDestroyCurrentClearsContext(): void
	{
		$exception = null;

		\Swoole\Coroutine\run(function () use (&$exception): void {
			$this->manager->create();
			$this->manager->destroyCurrent();

			try {
				$this->manager->current();
			} catch (RuntimeException $e) {
				$exception = $e;
			}
		});

		$this->assertInstanceOf(RuntimeException::class, $exception);
	}

	public function testIsInCoroutineReturnsFalseOutsideCoroutine(): void
	{
		$this->assertFalse($this->manager->isInCoroutine());
	}

	public function testIsInCoroutineReturnsTrueInsideCoroutine(): void
	{
		$result = null;

		\Swoole\Coroutine\run(function () use (&$result): void {
			$result = $this->manager->isInCoroutine();
		});

		$this->assertTrue($result);
	}

	public function testContextIdIsUnique(): void
	{
		$idA = null;
		$idB = null;

		\Swoole\Coroutine\run(function () use (&$idA): void {
			$idA = $this->manager->create()->id();
		});

		\Swoole\Coroutine\run(function () use (&$idB): void {
			$idB = $this->manager->create()->id();
		});

		$this->assertNotSame($idA, $idB);
	}
}
