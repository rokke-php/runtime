<?php

declare(strict_types=1);

namespace Rokke\Runtime\Builder;

use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\ArgumentPlanCompiler;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\HandlerCompiler;
use Rokke\Runtime\Build\OperationDefinition;
use Rokke\Runtime\Build\ResultPlanCompiler;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Compiled\ArtifactRepository;
use Rokke\Runtime\Compiled\CompiledBehaviorPipeline;
use Rokke\Runtime\Compiled\CompiledExecutionPipeline;
use Rokke\Runtime\Compiled\CompiledInterceptorPipeline;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Compiled\OperationRepository;
use Rokke\Runtime\Contracts\RuntimeInterface;
use Rokke\Runtime\Engine\ExecutionEngine;

final class DefaultRuntimeBuilder
{
	public function build(ApplicationModel $model): RuntimeInterface
	{
		$factories = FactoryRepository::build(
			$model->definitions(ServiceDescriptor::class),
			new FactoryCompiler(),
		);

		$handlerCompiler   = new HandlerCompiler();
		$argCompiler       = new ArgumentPlanCompiler();
		$resultCompiler    = new ResultPlanCompiler();
		$handlers          = [];
		$argumentPlans     = [];
		$resultPlans       = [];
		$behaviorPipelines = [];
		$compiledOps       = [];

		foreach ($model->definitions(OperationDefinition::class) as $index => $definition) {
			$handlers[$index]      = $handlerCompiler->compile($definition->handler, $factories);
			$argumentPlans[$index] = $argCompiler->compile($definition->handler, $factories);
			$resultPlans[$index]   = $resultCompiler->compile($definition->handler);

			$behaviorPipelineId = null;

			if ($definition->behaviors !== []) {
				$behaviorPipelineId                     = $index;
				$behaviorPipelines[$behaviorPipelineId] = new CompiledBehaviorPipeline(
					array_map(static fn ($d) => $d->behavior, $definition->behaviors),
				);
			}

			$compiledOps[] = new CompiledOperation(
				id: $definition->id,
				pipelineId: 0,
				factoryId: $index,
				argumentPlanId: $index,
				resultPlanId: $index,
				behaviorPipelineId: $behaviorPipelineId,
			);
		}

		$executionPipeline = new CompiledExecutionPipeline(
			handlers: $handlers,
			argumentPlans: $argumentPlans,
			resultPlans: $resultPlans,
			behaviorPipelines: $behaviorPipelines,
			validationPlans: [],
		);

		$compiled = new CompiledRuntime(
			executionPipeline: $executionPipeline,
			interceptorPipeline: CompiledInterceptorPipeline::empty(),
			operations: OperationRepository::build($compiledOps),
			factories: $factories,
			artifacts: ArtifactRepository::empty(),
		);

		return new ExecutionEngine($compiled);
	}
}
