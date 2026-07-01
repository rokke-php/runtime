<?php

// PHPStan stubs for Swoole classes and functions.
// These stubs are used only during static analysis.
// At runtime, the real ext-swoole extension provides these implementations.

namespace Swoole
{
	/** @phpstan-consistent-constructor */
	final class Server
	{
		public function __construct(string $host, int $port, int $mode = 3, int $sockType = 1) {}

		public function start(): bool {}

		public function shutdown(): void {}

		public function reload(): void {}

		public function on(string $event, callable $callback): bool {}

		/** @param array<string, mixed> $settings */
		public function set(array $settings): bool {}
	}

	final class Coroutine
	{
		public static function getCid(): int {}

		/** @return \ArrayObject<string, mixed> */
		public static function getContext(int $cid = -1): \ArrayObject {}

		public static function create(callable $callable, mixed ...$args): int|false {}

		public static function yield(): bool {}

		public static function cancel(int $coroutineId): bool {}
	}
}

namespace Swoole\Coroutine
{
	function run(callable $fn, mixed ...$params): void {}

	final class Channel
	{
		public function __construct(int $capacity = 1) {}

		public function push(mixed $data, float $timeout = -1.0): bool {}

		public function pop(float $timeout = -1.0): mixed {}

		/** @return array{queue_num: int, consumer_num: int} */
		public function stats(): array {}

		public function close(): bool {}
	}

	final class WaitGroup
	{
		public function add(int $delta = 1): void {}

		public function done(): void {}

		public function wait(float $timeout = -1.0): bool {}
	}

	final class System
	{
		public static function sleep(float $seconds): bool {}
	}
}
