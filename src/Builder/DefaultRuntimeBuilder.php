<?php

declare(strict_types=1);

namespace Rokke\Runtime\Builder;

use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\ArgumentPlanCompiler;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\OperationDefinition;
use Rokke\Runtime\Build\ResultPlanCompiler;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Compiled\ArtifactRepository;
use Rokke\Runtime\Compiled\CompiledBehaviorPipeline;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Compiled\OperationRepository;
use Rokke\Runtime\Contracts\RuntimeInterface;
use Rokke\Runtime\Engine\ExecutionEngine;
use Rokke\Runtime\Engine\Invoker;

final class DefaultRuntimeBuilder
{
	public function build(ApplicationModel $model): RuntimeInterface
	{
		$factories = FactoryRepository::build(
			$model->definitions(ServiceDescriptor::class),
			new FactoryCompiler(),
		);

		$argCompiler      = new ArgumentPlanCompiler();
		$resultCompiler   = new ResultPlanCompiler();
		$handlers         = [];
		$argumentPlans    = [];
		$resultPlans      = [];
		$behaviorPipelines = [];
		$compiledOps      = [];

		foreach ($model->definitions(OperationDefinition::class) as $index => $definition) {
			$handlers[$index]      = $definition->handler;
			$argumentPlans[$index] = $argCompiler->compile($definition->handler, $factories);
			$resultPlans[$index]   = $resultCompiler->compile($definition->handler);

			$behaviorPipelineId = null;

			if ($definition->behaviors !== []) {
				$behaviorPipelineId                    = $index;
				$behaviorPipelines[$behaviorPipelineId] = new CompiledBehaviorPipeline(
					array_map(static fn ($d) => $d->behavior, $definition->behaviors),
				);
			}

			$compiledOps[] = new CompiledOperation(
				$definition->id,
				0,
				$index,
				$index,
				$index,
				behaviorPipelineId: $behaviorPipelineId,
			);
		}

		$compiled = new CompiledRuntime(
			pipelines: [],
			handlers: $handlers,
			argumentPlans: $argumentPlans,
			resultPlans: $resultPlans,
			operations: OperationRepository::build($compiledOps),
			factories: $factories,
			artifacts: ArtifactRepository::empty(),
			behaviorPipelines: $behaviorPipelines,
		);

		return new ExecutionEngine(new Invoker($compiled), runtime: $compiled);
	}
}
