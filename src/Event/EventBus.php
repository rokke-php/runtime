<?php

declare(strict_types=1);

namespace Rokke\Runtime\Event;

use Rokke\Contracts\Container\ServiceContainerInterface;
use Rokke\Contracts\Events\EventBusInterface;
use Rokke\Runtime\Contracts\CoroutineManagerInterface;
use RuntimeException;

/**
 * Centralized Event Bus handling internal and domain events.
 */
final class EventBus implements EventBusInterface
{
	/** @var array<string, array<int, callable|string>> */
	private array $listeners = [];

	public function __construct(
		private readonly ServiceContainerInterface $container,
		private readonly CoroutineManagerInterface $coroutineManager
	) {}

	public function listen(string $eventClass, callable|string $listener): void
	{
		if (!isset($this->listeners[$eventClass])) {
			$this->listeners[$eventClass] = [];
		}

		$this->listeners[$eventClass][] = $listener;
	}

	public function dispatchSync(object $event): void
	{
		foreach ($this->getListenersFor($event) as $listener) {
			$this->executeListener($listener, $event);
		}
	}

	public function dispatchCoroutine(object $event): void
	{
		foreach ($this->getListenersFor($event) as $listener) {
			// Spawn a new isolated coroutine for each listener.
			// This prevents slow listeners from blocking the current request flow.
			$this->coroutineManager->go(function () use ($listener, $event): void {
				$this->executeListener($listener, $event);
			});
		}
	}

	public function dispatchBackground(object $event): void
	{
		throw new \BadMethodCallException(
			'Background dispatch is not implemented. Configure a Task Worker or Queue module before calling dispatchBackground().',
		);
	}

	public function dispatchDistributed(object $event): void
	{
		// TODO: Delegate to a distributed pub/sub component (e.g. Redis, Kafka).
		throw new RuntimeException('Distributed dispatching is not configured yet.');
	}

	/**
	 * @return array<int, callable|string>
	 */
	private function getListenersFor(object $event): array
	{
		return $this->listeners[$event::class] ?? [];
	}

	private function executeListener(callable|string $listener, object $event): void
	{
		if (is_string($listener)) {
			$listener = $this->container->make($listener);
		}

		if (is_callable($listener)) {
			$listener($event);

			return;
		}

		if (is_object($listener) && method_exists($listener, 'handle')) {
			$listener->handle($event);

			return;
		}

		throw new RuntimeException('Event listener must be a callable or have a handle() method.');
	}
}
