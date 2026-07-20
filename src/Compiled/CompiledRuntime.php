<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

use Rokke\Runtime\Build\FactoryRepository;

/**
 * Immutable snapshot of the fully compiled application.
 *
 * Holds only the artefacts the ExecutionEngine needs to dispatch a request:
 *   - operations          — maps operation IDs to their compiled metadata
 *   - executionPipeline   — the single fixed Argument→Behavior→Invocation→Result pipeline
 *   - interceptorPipeline — the global observability wrapper (telemetry, logging, metrics)
 *   - factories / artifacts / configurations — service, artefact, and configuration registries
 */
final class CompiledRuntime
{
	public readonly FactoryRepository $factories;
	public readonly OperationRepository $operations;
	public readonly ArtifactRepository $artifacts;
	private readonly CompiledConfigurationRepository $configurationsRepo;

	public function __construct(
		public readonly CompiledExecutionPipeline $executionPipeline,
		public readonly CompiledInterceptorPipeline $interceptorPipeline,
		?OperationRepository $operations = null,
		?FactoryRepository $factories = null,
		?ArtifactRepository $artifacts = null,
		?CompiledConfigurationRepository $configurations = null,
	) {
		$this->operations         = $operations ?? OperationRepository::empty();
		$this->factories          = $factories ?? FactoryRepository::empty();
		$this->artifacts          = $artifacts ?? ArtifactRepository::empty();
		$this->configurationsRepo = $configurations ?? CompiledConfigurationRepository::empty();
	}

	public function configurations(): CompiledConfigurationRepository
	{
		return $this->configurationsRepo;
	}

	public function getService(string $alias): ?object
	{
		$id = $this->factories->id($alias);

		return $id !== null ? $this->factories->create($id) : null;
	}
}
