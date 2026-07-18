<?php

declare(strict_types=1);

namespace Rokke\Runtime;

use Rokke\Contracts\Extension\ExtensionInterface;
use Rokke\Runtime\Build\DiscoveryEngine;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Engine\ExecutionEngine;
use Rokke\Runtime\Build\ModelBuilder;
use Rokke\Runtime\Build\OperationModelBuilderPass;
use Rokke\Runtime\Build\ServiceModelBuilderPass;
use Rokke\Runtime\Builder\DefaultRuntimeBuilder;
use Rokke\Runtime\Context\OperationContext;
use Rokke\Runtime\Contracts\RuntimeInterface;
use Rokke\Runtime\Extension\ExtensionBuilder;
use Rokke\Runtime\Extension\ExtensionRegistry;

final class ApplicationKernel
{
	private ExtensionRegistry $extensions;
	private ?RuntimeInterface $runtime = null;

	public function __construct()
	{
		$this->extensions = new ExtensionRegistry();
	}

	public function register(ExtensionInterface $extension): void
	{
		$this->extensions->register($extension);
	}

	public function build(): void
	{
		$builder = new ExtensionBuilder();
		$this->extensions->buildAll($builder);

		$discovered = (new DiscoveryEngine())->run($builder->getDiscoveryProviders());

		$allCapabilities = [...$builder->getCapabilities(), ...$discovered];

		$model         = (new ModelBuilder([new OperationModelBuilderPass(), new ServiceModelBuilderPass()]))->build($allCapabilities);
		$this->runtime = (new DefaultRuntimeBuilder())->build($model);
	}

	public function compiledRuntime(): CompiledRuntime
	{
		if ($this->runtime === null) {
			throw new \RuntimeException('Call build() before compiledRuntime().');
		}

		assert($this->runtime instanceof ExecutionEngine);

		return $this->runtime->compiledRuntime();
	}

	public function loadCompiledRuntime(CompiledRuntime $runtime): void
	{
		$this->runtime = new ExecutionEngine($runtime);
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
