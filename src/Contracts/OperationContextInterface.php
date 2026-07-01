<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Unified context for a single platform execution.
 * Carries transport data, cooperative cancellation, and traceability.
 */
interface OperationContextInterface
{
	/**
	 * Unique identifier for this execution (Request ID / Trace ID).
	 */
	public function id(): string;

	/**
	 * Whether the operation has been cancelled by the Host (e.g. client disconnected).
	 */
	public function isCancelled(): bool;

	/**
	 * Throws if the operation has been cancelled.
	 * Use inside coroutines to release CPU/DB resources immediately.
	 */
	public function throwIfCancelled(): void;

	/**
	 * Transport or context metadata (headers, tags, etc.).
	 */
	public function metadata(string $key, mixed $default = null): mixed;
}
