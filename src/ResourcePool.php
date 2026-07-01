<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use RuntimeException;
use Swoole\Atomic;
use Swoole\Coroutine\Channel;
use Throwable;

/**
 * Represents an individual group of resources of the same type (e.g. DB connections).
 * Handles concurrency internally using coroutine channels.
 *
 * @internal This class is used exclusively by the ResourceManager.
 */
final class ResourcePool
{
	private Channel $channel;
	private Atomic $currentTotal;
	private bool $closed = false;

	/** @var callable(): mixed */
	private $factory;

	public function __construct(
		public readonly string $name,
		callable $factory,
		public readonly int $min,
		public readonly int $max,
		public readonly int $timeout // ms
	) {
		$this->factory      = $factory;
		$this->channel      = new Channel($max);
		$this->currentTotal = new Atomic(0);

		for ($i = 0; $i < $this->min; $i++) {
			$this->channel->push($this->createResource());
		}
	}

	public function get(): mixed
	{
		if ($this->closed) {
			throw new RuntimeException("The pool [{$this->name}] is closed.");
		}

		$resource = $this->channel->pop(0.001);

		if ($resource !== false) {
			return $resource;
		}

		// Atomically reserve a slot. If the new total exceeds max, we overshot — revert.
		$newTotal = $this->currentTotal->add(1);

		if ($newTotal <= $this->max) {
			try {
				return ($this->factory)();
			} catch (Throwable $e) {
				$this->currentTotal->sub(1);
				throw $e;
			}
		}

		// Overshot the limit; revert and wait for a released resource.
		$this->currentTotal->sub(1);

		$timeoutSec = $this->timeout / 1000.0;
		$resource   = $this->channel->pop($timeoutSec);

		if ($resource === false) {
			throw new RuntimeException("Timeout waiting for available resource from pool [{$this->name}]. Connection limit reached.");
		}

		return $resource;
	}

	public function release(mixed $resource): void
	{
		if ($this->closed) {
			$this->currentTotal->sub(1);

			return;
		}

		if (!$this->channel->push($resource)) {
			// Channel closed between the check and the push — discard and correct the counter.
			$this->currentTotal->sub(1);
		}
	}

	/**
	 * @return array{name: string, max: int, min: int, current_total: int, idle: int, waiting_coroutines: int}
	 */
	public function stats(): array
	{
		/** @var array{queue_num?: int, consumer_num?: int} $channelStats */
		$channelStats = $this->channel->stats();

		return [
			'name'                => $this->name,
			'max'                 => $this->max,
			'min'                 => $this->min,
			'current_total'       => $this->currentTotal->get(),
			'idle'                => $channelStats['queue_num'] ?? 0,
			'waiting_coroutines'  => $channelStats['consumer_num'] ?? 0,
		];
	}

	public function close(): void
	{
		$this->closed = true;
		$this->channel->close();
	}

	private function createResource(): mixed
	{
		$this->currentTotal->add(1);

		try {
			return ($this->factory)();
		} catch (Throwable $e) {
			$this->currentTotal->sub(1);
			throw $e;
		}
	}
}
