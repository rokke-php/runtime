<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

use Rokke\Contracts\Context\ContextInterface;

/**
 * Critical component: Manages the context of each coroutine.
 * Handles the flow: Coroutine -> Context -> Request -> Response ...
 */
interface ContextManagerInterface
{
	/**
	 * Returns the context of the current coroutine.
	 */
	public function current(): ContextInterface;

	/**
	 * Creates a new isolated context for a coroutine.
	 */
	public function create(): ContextInterface;

	/**
	 * Destroys and cleans up the context of the current coroutine.
	 */
	public function destroyCurrent(): void;

	/**
	 * Returns true when called from inside an active Swoole coroutine.
	 */
	public function isInCoroutine(): bool;
}
