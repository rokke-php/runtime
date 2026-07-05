<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Runtime\Resource\PoolConfig;
use Rokke\Runtime\Resource\PoolStats;
use Rokke\Runtime\Resource\ResourceSlot;
use RuntimeException;
use Swoole\Atomic;
use Swoole\Coroutine\Channel;
use Throwable;

/**
 * Coroutine-safe resource pool backed by a Swoole Channel.
 *
 * v2 additions over the original pool:
 *  - PoolConfig                   — structured configuration
 *  - ResourceSlot                 — per-resource createdAt for age-based eviction
 *  - ResourceValidatorInterface   — health check applied on every acquire
 *  - Rich PoolStats               — acquired / created / errors / validationFails / evicted
 *  - drain()                      — wait for all active resources to be released
 *
 * SplObjectStorage tracks the active (acquired) resource → slot mapping so that
 * on release() the original slot (and its createdAt) is restored to the channel.
 *
 * @internal Used exclusively by ResourceManager.
 */
final class ResourcePool
{
	/** Pool identifier — mirrors PoolConfig::name for external access. */
	public readonly string $name;

	private Channel $channel;
	private Atomic $currentTotal;
	private Atomic $acquired;
	private Atomic $created;
	private Atomic $errors;
	private Atomic $validationFails;
	private Atomic $evicted;

	/** @var \SplObjectStorage<object, ResourceSlot> */
	private \SplObjectStorage $activeSlots;

	private bool $closed = false;

	/** @var callable(): mixed */
	private $factory;

	/** @var callable(): float */
	private $clock;

	public function __construct(
		private readonly PoolConfig $config,
		callable $factory,
		?callable $clock = null,
	) {
		$this->name    = $config->name;
		$this->factory = $factory;
		$this->clock   = $clock ?? static fn (): float => microtime(true);

		$this->channel        = new Channel($config->max);
		$this->currentTotal   = new Atomic(0);
		$this->acquired       = new Atomic(0);
		$this->created        = new Atomic(0);
		$this->errors         = new Atomic(0);
		$this->validationFails = new Atomic(0);
		$this->evicted        = new Atomic(0);
		$this->activeSlots    = new \SplObjectStorage();

		for ($i = 0; $i < $config->min; $i++) {
			$this->channel->push($this->newSlot());
		}
	}

	public function get(): mixed
	{
		if ($this->closed) {
			throw new RuntimeException("The pool [{$this->config->name}] is closed.");
		}

		// 1. Drain idle slots — skip expired or invalid resources.
		while (true) {
			$slot = $this->channel->pop(0.001);

			if (!$slot instanceof ResourceSlot) {
				break;
			}

			if ($this->isSlotUsable($slot)) {
				$this->trackActive($slot);
				$this->acquired->add(1);

				return $slot->resource;
			}

			$this->currentTotal->sub(1);
			$this->evicted->add(1);
		}

		// 2. Try to create a new resource if below max.
		$newTotal = $this->currentTotal->add(1);

		if ($newTotal <= $this->config->max) {
			return $this->doCreate();
		}

		// 3. Overshot the limit — undo and wait for a release.
		$this->currentTotal->sub(1);

		$timeoutSec = $this->config->acquireTimeout > 0
			? $this->config->acquireTimeout / 1000.0
			: -1;

		$slot = $this->channel->pop($timeoutSec);

		if (!$slot instanceof ResourceSlot) {
			throw new RuntimeException(
				"Timeout waiting for available resource from pool [{$this->config->name}]. "
				. 'All connections are in use.',
			);
		}

		// Validate waited slot; if unusable, discard and create a replacement.
		if (!$this->isSlotUsable($slot)) {
			$this->currentTotal->sub(1);
			$this->evicted->add(1);
			$this->currentTotal->add(1); // reserve slot for replacement

			return $this->doCreate();
		}

		$this->trackActive($slot);
		$this->acquired->add(1);

		return $slot->resource;
	}

	public function release(mixed $resource): void
	{
		if ($this->closed) {
			$this->currentTotal->sub(1);

			return;
		}

		// Recover the original slot so its createdAt is preserved for age eviction.
		$slot = $this->recoverSlot($resource);

		if (!$this->channel->push($slot)) {
			// Channel closed between the check and the push.
			$this->currentTotal->sub(1);
		}
	}

	public function stats(): PoolStats
	{
		/** @var array{queue_num?: int, consumer_num?: int} $ch */
		$ch = $this->channel->stats();

		return new PoolStats(
			name: $this->config->name,
			max: $this->config->max,
			min: $this->config->min,
			currentTotal: $this->currentTotal->get(),
			idle: $ch['queue_num'] ?? 0,
			waitingCoroutines: $ch['consumer_num'] ?? 0,
			acquired: $this->acquired->get(),
			created: $this->created->get(),
			errors: $this->errors->get(),
			validationFails: $this->validationFails->get(),
			evicted: $this->evicted->get(),
		);
	}

	/**
	 * Block (coroutine yield) until every acquired resource has been released,
	 * or until $timeout seconds elapse.
	 *
	 * @throws RuntimeException on timeout
	 */
	public function drain(float $timeout = 30.0): void
	{
		$deadline = microtime(true) + $timeout;

		while (microtime(true) < $deadline) {
			if ($this->currentTotal->get() === $this->stats()->idle) {
				return;
			}

			\Swoole\Coroutine::sleep(0.05);
		}

		throw new RuntimeException(
			"Drain timeout exceeded for pool [{$this->config->name}]. "
			. 'Some resources are still active.',
		);
	}

	public function close(): void
	{
		$this->closed = true;
		$this->channel->close();
	}

	// ── private ───────────────────────────────────────────────────────────────

	private function newSlot(): ResourceSlot
	{
		$this->currentTotal->add(1);

		try {
			$slot = new ResourceSlot(($this->factory)(), ($this->clock)());
			$this->created->add(1);

			return $slot;
		} catch (Throwable $e) {
			$this->currentTotal->sub(1);
			throw $e;
		}
	}

	private function doCreate(): mixed
	{
		try {
			$slot = new ResourceSlot(($this->factory)(), ($this->clock)());
			$this->created->add(1);
			$this->acquired->add(1);
			$this->trackActive($slot);

			return $slot->resource;
		} catch (Throwable $e) {
			$this->currentTotal->sub(1);
			$this->errors->add(1);
			throw $e;
		}
	}

	private function isSlotUsable(ResourceSlot $slot): bool
	{
		if ($this->config->maxAge > 0) {
			$age = ($this->clock)() - $slot->createdAt;

			if ($age > $this->config->maxAge) {
				return false;
			}
		}

		if ($this->config->validator !== null) {
			$valid = $this->config->validator->validate($slot->resource);

			if (!$valid) {
				$this->validationFails->add(1);

				return false;
			}
		}

		return true;
	}

	private function trackActive(ResourceSlot $slot): void
	{
		if (is_object($slot->resource)) {
			$this->activeSlots->attach($slot->resource, $slot);
		}
	}

	private function recoverSlot(mixed $resource): ResourceSlot
	{
		if (is_object($resource) && $this->activeSlots->contains($resource)) {
			$slot = $this->activeSlots[$resource];
			$this->activeSlots->detach($resource);

			return $slot;
		}

		// Non-object resource or untracked (edge case) — wrap with current timestamp.
		return new ResourceSlot($resource, ($this->clock)());
	}
}
