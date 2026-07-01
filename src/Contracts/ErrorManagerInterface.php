<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

use Throwable;

/**
 * Not just exceptions. Knows about Recoverable, Fatal, Coroutine, Shutdown, Signals, Timeout errors.
 * Defines policies for handling each error.
 */
interface ErrorManagerInterface
{
	public function handle(Throwable $exception): void;

	public function isRecoverable(Throwable $exception): bool;

	public function registerPolicy(string $exceptionClass, callable $policy): void;

	public function reportFatalError(string $message): void;
}
