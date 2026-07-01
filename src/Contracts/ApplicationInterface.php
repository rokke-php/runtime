<?php

declare(strict_types=1);

namespace Rokke\Runtime\Contracts;

/**
 * Represents the assembled application with all its modules registered
 * and ready to be executed by the Host.
 */
interface ApplicationInterface
{
	public function run(): void;

	public function stop(): void;

	public function host(): HostInterface;
}
