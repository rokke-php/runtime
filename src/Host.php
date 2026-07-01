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
	private Server $server;

	public function __construct(string $host = '127.0.0.1', int $port = 8000)
	{
		// Initializes a master process with SWOOLE_PROCESS mode and TCP socket
		$this->server = new Server($host, $port, SWOOLE_PROCESS, SWOOLE_TCP);
	}

	/**
	 * Retrieves the native Swoole Server instance so modules can hook into it
	 * (e.g., $server->on('request', ...), $server->addProcess(...)).
	 */
	public function getInternalServer(): Server
	{
		return $this->server;
	}

	public function start(RuntimeInterface $runtime): void
	{
		$this->server->start();
	}

	public function stop(): void
	{
		$this->server->shutdown();
	}
}
