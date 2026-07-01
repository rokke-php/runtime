<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * A native Runtime scheduler (not external crontab).
 * Internally should use the same Pipeline.
 */
interface SchedulerInterface
{
	/**
	 * @return mixed Objeto para encadenar: every(5)->seconds(callable)
	 */
	public function every(int $value): mixed;

	public function cron(string $expression, callable|string $handler): void;

	public function start(): void;

	public function stop(): void;
}
