<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Container\ServiceContainerInterface;
use Rokke\Contracts\Lifecycle\ApplicationState;
use Rokke\Runtime\Application;
use Rokke\Runtime\Contracts\HostInterface;
use Rokke\Runtime\Contracts\LifecycleManagerInterface;
use Rokke\Runtime\Contracts\RuntimeInterface;

final class ApplicationTest extends TestCase
{
	public function testStopCallsHostStop(): void
	{
		$container = $this->createStub(ServiceContainerInterface::class);
		$lifecycle = $this->createStub(LifecycleManagerInterface::class);
		$host      = $this->createMock(HostInterface::class);
		$runtime   = $this->createStub(RuntimeInterface::class);

		$host->expects($this->once())->method('stop');

		$app = new Application($container, $lifecycle, $host, $runtime);
		$app->stop();
	}

	public function testStopTransitionsToStoppingThenStopped(): void
	{
		$container = $this->createStub(ServiceContainerInterface::class);
		$lifecycle = $this->createMock(LifecycleManagerInterface::class);
		$host      = $this->createStub(HostInterface::class);
		$runtime   = $this->createStub(RuntimeInterface::class);

		$lifecycle->expects($this->exactly(2))
			->method('transitionTo')
			->with($this->logicalOr(
				$this->equalTo(ApplicationState::Stopping),
				$this->equalTo(ApplicationState::Stopped),
			));

		$app = new Application($container, $lifecycle, $host, $runtime);
		$app->stop();
	}
}
