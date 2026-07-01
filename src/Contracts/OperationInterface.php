<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * A compiled, transport-agnostic unit of work.
 * Operations carry no ephemeral state — they are resolved once at build time.
 */
interface OperationInterface
{
	/**
	 * Internal runtime identifier for this operation.
	 */
	public function id(): string;

	/**
	 * Semantic name of the operation (e.g. 'users.create').
	 */
	public function name(): string;

	/**
	 * Metadata associated with this operation.
	 */
	public function metadata(string $key, mixed $default = null): mixed;
}
