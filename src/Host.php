<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Runtime\Contracts\HostInterface;
use Rokke\Runtime\Contracts\RuntimeInterface;
use Swoole\Server;

/**
 * Swoole TCP host. Receives a compiled RuntimeInterface at start time
 * so the Host layer has zero knowledge of the build phase.
 */
final class Host implements HostInterface
{
	private ?Server $server = null;

	public function __construct(private readonly string $host = '127.0.0.1', private readonly int $port = 8000) {}

	/**
	 * Returns the Swoole Server, creating it on first access.
	 * Deferred so that constructing a Host does not bind a socket.
	 */
	public function getInternalServer(): Server
	{
		if ($this->server === null) {
			$this->server = new Server($this->host, $this->port, SWOOLE_PROCESS, SWOOLE_TCP);
		}

		return $this->server;
	}

	public function start(RuntimeInterface $runtime): void
	{
		$this->getInternalServer()->start();
	}

	public function stop(): void
	{
		$this->getInternalServer()->shutdown();
	}
}
