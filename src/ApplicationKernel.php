<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Contracts\Module\ModuleInterface;
use Rokke\Runtime\Build\ModelBuilder;
use Rokke\Runtime\Build\OperationModelBuilderPass;
use Rokke\Runtime\Builder\DefaultRuntimeBuilder;
use Rokke\Runtime\Context\OperationContext;
use Rokke\Runtime\Contracts\RuntimeInterface;
use Rokke\Runtime\Module\ModuleBuilder;
use Rokke\Runtime\Module\ModuleSystem;

final class ApplicationKernel
{
	private ModuleSystem $modules;
	private ?RuntimeInterface $runtime = null;

	public function __construct()
	{
		$this->modules = new ModuleSystem();
	}

	public function register(ModuleInterface $module): void
	{
		$this->modules->register($module);
	}

	public function build(): void
	{
		$moduleBuilder = new ModuleBuilder();
		$this->modules->buildAll($moduleBuilder);

		$model         = new ModelBuilder([new OperationModelBuilderPass()])->build($moduleBuilder->getCapabilities());
		$this->runtime = new DefaultRuntimeBuilder()->build($model);
	}

	/** @param array<string, mixed> $metadata */
	public function run(string $operationId, array $metadata = []): mixed
	{
		if ($this->runtime === null) {
			throw new \RuntimeException('Call build() before run().');
		}

		$op  = new SimpleOperation($operationId);
		$ctx = new OperationContext(uniqid('ctx-', true), $metadata);

		return $this->runtime->execute($op, $ctx);
	}
}
