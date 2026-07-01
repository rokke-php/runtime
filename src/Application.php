<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Contracts\Container\ServiceContainerInterface;
use Rokke\Contracts\Lifecycle\ApplicationState;
use Rokke\Runtime\Contracts\ApplicationInterface;
use Rokke\Runtime\Contracts\HostInterface;
use Rokke\Runtime\Contracts\LifecycleManagerInterface;
use Rokke\Runtime\Contracts\RuntimeInterface;

final class Application implements ApplicationInterface
{
	public function __construct(
		private readonly ServiceContainerInterface $container,
		private readonly LifecycleManagerInterface $lifecycle,
		private readonly HostInterface $host,
		private readonly RuntimeInterface $runtime,
	) {}

	public function run(): void
	{
		$this->lifecycle->transitionTo(ApplicationState::Bootstrapping);
		$this->lifecycle->transitionTo(ApplicationState::Starting);
		$this->lifecycle->transitionTo(ApplicationState::Running);

		$this->host->start($this->runtime);
	}

	public function stop(): void
	{
		$this->lifecycle->transitionTo(ApplicationState::Stopping);

		try {
			$this->host->stop();
		} finally {
			$this->lifecycle->transitionTo(ApplicationState::Stopped);
		}
	}

	public function host(): HostInterface
	{
		return $this->host;
	}

	public function container(): ServiceContainerInterface
	{
		return $this->container;
	}
}
