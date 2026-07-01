<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Event;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Contracts\CoroutineManagerInterface;
use Rokke\Runtime\Event\EventBus;
use Rokke\Runtime\ServiceContainer;
use RuntimeException;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class EventBusUserRegistered
{
	public function __construct(public readonly string $email) {}
}

final class EventBusOrderPlaced
{
	public function __construct(public readonly int $orderId) {}
}

final class EventBusSyncListenerClass
{
	/** @var list<object> */
	public array $received = [];

	public function handle(object $event): void
	{
		$this->received[] = $event;
	}
}

// ── Stub CoroutineManager ─────────────────────────────────────────────────────

final class EventBusSyncCoroutineManager implements CoroutineManagerInterface
{
	/** @var list<callable> */
	public array $spawnedCallables = [];

	public function go(callable $callable, mixed ...$args): int
	{
		// Run immediately (synchronously) so tests don't need a Swoole runtime
		$callable(...$args);

		return 1;
	}

	/**
	 * @param array<callable(): mixed> $callables
	 * @return array<mixed>
	 */
	public function parallel(array $callables): array
	{
		$results = [];

		foreach ($callables as $key => $callable) {
			$results[$key] = $callable();
		}

		return $results;
	}

	public function wait(int $timeoutMs = -1): void {}

	public function timeout(int $milliseconds, callable $callable): mixed
	{
		return $callable();
	}

	public function cancel(int $coroutineId): bool
	{
		return true;
	}
}

// ── Tests ────────────────────────────────────────────────────────────────────

final class EventBusTest extends TestCase
{
	private EventBus $bus;
	private ServiceContainer $container;
	private EventBusSyncCoroutineManager $coroutineManager;

	protected function setUp(): void
	{
		$this->container        = new ServiceContainer();
		$this->coroutineManager = new EventBusSyncCoroutineManager();
		$this->bus              = new EventBus($this->container, $this->coroutineManager);
	}

	public function testDispatchSyncFiresCallableListener(): void
	{
		$received = null;

		$this->bus->listen(EventBusUserRegistered::class, function (object $event) use (&$received): void {
			$received = $event;
		});

		$event = new EventBusUserRegistered('dev@rokke.dev');
		$this->bus->dispatchSync($event);

		$this->assertSame($event, $received);
	}

	public function testDispatchSyncFiresMultipleListeners(): void
	{
		$count = 0;

		$this->bus->listen(EventBusUserRegistered::class, function () use (&$count): void {
			$count++;
		});
		$this->bus->listen(EventBusUserRegistered::class, function () use (&$count): void {
			$count++;
		});

		$this->bus->dispatchSync(new EventBusUserRegistered('a@b.com'));

		$this->assertSame(2, $count);
	}

	public function testDispatchSyncDoesNothingWithNoListeners(): void
	{
		// Must not throw
		$this->bus->dispatchSync(new EventBusOrderPlaced(99));

		$this->assertTrue(true);
	}

	public function testDispatchSyncResolvesListenerClassFromContainer(): void
	{
		$listenerInstance = new EventBusSyncListenerClass();
		$this->container->singleton(EventBusSyncListenerClass::class, fn () => $listenerInstance);

		$this->bus->listen(EventBusUserRegistered::class, EventBusSyncListenerClass::class);

		$event = new EventBusUserRegistered('test@test.com');
		$this->bus->dispatchSync($event);

		$this->assertCount(1, $listenerInstance->received);
		$this->assertSame($event, $listenerInstance->received[0]);
	}

	public function testDispatchSyncOnlyFiresListenersForMatchingEvent(): void
	{
		$userFired  = false;
		$orderFired = false;

		$this->bus->listen(EventBusUserRegistered::class, function () use (&$userFired): void {
			$userFired = true;
		});
		$this->bus->listen(EventBusOrderPlaced::class, function () use (&$orderFired): void {
			$orderFired = true;
		});

		$this->bus->dispatchSync(new EventBusUserRegistered('x@y.com'));

		$this->assertTrue($userFired);
		$this->assertFalse($orderFired);
	}

	public function testDispatchCoroutineFiresListenerViaCoroutineManager(): void
	{
		$received = null;

		$this->bus->listen(EventBusUserRegistered::class, function (object $event) use (&$received): void {
			$received = $event;
		});

		$event = new EventBusUserRegistered('async@rokke.dev');
		$this->bus->dispatchCoroutine($event);

		$this->assertSame($event, $received);
	}

	public function testDispatchBackgroundThrowsBadMethodCallException(): void
	{
		$this->expectException(\BadMethodCallException::class);

		$this->bus->dispatchBackground(new EventBusUserRegistered('x@y.com'));
	}

	public function testDispatchDistributedThrowsNotConfigured(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Distributed dispatching is not configured');

		$this->bus->dispatchDistributed(new EventBusUserRegistered('x@y.com'));
	}

	public function testListenerWithInvalidTargetThrows(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('handle()');

		$this->container->singleton('InvalidListener', fn () => new \stdClass());
		$this->bus->listen(EventBusUserRegistered::class, 'InvalidListener');

		$this->bus->dispatchSync(new EventBusUserRegistered('x@y.com'));
	}
}
