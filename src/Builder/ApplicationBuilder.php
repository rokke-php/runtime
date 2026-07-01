<?php

declare(strict_types=1);

namespace Rokke\Runtime\Builder;

use Rokke\Contracts\Lifecycle\LifecycleEventsInterface;
use Rokke\Runtime\Application;
use Rokke\Runtime\Build\ModelBuilder;
use Rokke\Runtime\Build\OperationModelBuilderPass;
use Rokke\Runtime\ContextManager;
use Rokke\Runtime\Contracts\ContextManagerInterface;
use Rokke\Runtime\Contracts\HostInterface;
use Rokke\Runtime\Contracts\LifecycleManagerInterface;
use Rokke\Runtime\Contracts\ModuleSystemInterface;
use Rokke\Runtime\Contracts\PoolManagerInterface;
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

		// Discovery phase
		$moduleBuilder = new ModuleBuilder();
		$moduleSystem->buildAll($moduleBuilder);

		// Modeling phase
		$modelBuilder = new ModelBuilder([new OperationModelBuilderPass()]);
		$model        = $modelBuilder->build($moduleBuilder->getCapabilities());

		// Assembly phase
		$runtimeBuilder = new DefaultRuntimeBuilder();
		$runtime        = $runtimeBuilder->build($model);

		return new Application($container, $lifecycle, $serverHost, $runtime);
	}
}
