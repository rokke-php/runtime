<?php

declare(strict_types=1);

namespace Rokke\Runtime\Context;

use Rokke\Runtime\Contracts\OperationContextInterface;

/**
 * Per-request execution context. Supports cooperative cancellation:
 * the Host calls cancel() when the client disconnects; handlers call throwIfCancelled().
 */
final class OperationContext implements OperationContextInterface
{
	private bool $cancelled = false;

	/** @param array<string, mixed> $metadata */
	public function __construct(
		private readonly string $id,
		private readonly array $metadata = [],
	) {}

	public function id(): string
	{
		return $this->id;
	}

	public function isCancelled(): bool
	{
		return $this->cancelled;
	}

	public function throwIfCancelled(): void
	{
		if ($this->cancelled) {
			throw new \RuntimeException("Operation {$this->id} was cancelled.");
		}
	}

	public function metadata(string $key, mixed $default = null): mixed
	{
		return $this->metadata[$key] ?? $default;
	}

	public function cancel(): void
	{
		$this->cancelled = true;
	}
}
