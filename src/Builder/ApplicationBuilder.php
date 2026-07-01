<?php

declare(strict_types=1);

namespace Rokke\Runtime\Builder;

use Rokke\Contracts\Lifecycle\LifecycleEventsInterface;
use Rokke\Runtime\Application;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\ContextManager;
use Rokke\Runtime\Contracts\ContextManagerInterface;
use Rokke\Runtime\Contracts\HostInterface;
use Rokke\Runtime\Contracts\LifecycleManagerInterface;
use Rokke\Runtime\Contracts\ModuleSystemInterface;
use Rokke\Runtime\Contracts\PoolManagerInterface;
use Rokke\Runtime\Engine\ExecutionEngine;
use Rokke\Runtime\Engine\Invoker;
use Rokke\Runtime\Host;
use Rokke\Runtime\Lifecycle;
use Rokke\Runtime\Module\ModuleBuilder;
use Rokke\Runtime\Module\ModuleSystem;
use Rokke\Runtime\ResourceManager;
use Rokke\Runtime\ServiceContainer;

final class ApplicationBuilder
{
	public static function create(string $host = '127.0.0.1', int $port = 8000): Application
	{
		$contextManager  = new ContextManager();
		$resourceManager = new ResourceManager();

		$container    = new ServiceContainer($contextManager, $resourceManager);
		$lifecycle    = new Lifecycle();
		$moduleSystem = new ModuleSystem();
		$serverHost   = new Host($host, $port);

		$container->singleton(ContextManagerInterface::class, $contextManager);
		$container->singleton(PoolManagerInterface::class, $resourceManager);
		$container->singleton(LifecycleEventsInterface::class, $lifecycle);
		$container->singleton(LifecycleManagerInterface::class, $lifecycle);
		$container->singleton(ModuleSystemInterface::class, $moduleSystem);
		$container->singleton(HostInterface::class, $serverHost);

		// Run the build phase: collect capabilities from all registered modules
		$moduleBuilder = new ModuleBuilder();
		$moduleSystem->buildAll($moduleBuilder);

		// Compile the application graph into an executable runtime
		$compiledRuntime = new CompiledRuntime([], [], [], [], []);
		$invoker         = new Invoker($compiledRuntime);
		$runtime         = new ExecutionEngine($invoker);

		return new Application($container, $lifecycle, $serverHost, $runtime);
	}
}
