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

		$argCompiler    = new ArgumentPlanCompiler();
		$resultCompiler = new ResultPlanCompiler();
		$handlers       = [];
		$argumentPlans  = [];
		$resultPlans    = [];
		$compiledOps    = [];

		foreach ($model->definitions(OperationDefinition::class) as $index => $definition) {
			$handlers[$index]      = $definition->handler;
			$argumentPlans[$index] = $argCompiler->compile($definition->handler, $factories);
			$resultPlans[$index]   = $resultCompiler->compile($definition->handler);
			$compiledOps[]         = new CompiledOperation($definition->id, 0, $index, $index, $index);
		}

		$compiled = new CompiledRuntime(
			[],
			$handlers,
			$argumentPlans,
			$resultPlans,
			OperationRepository::build($compiledOps),
			$factories,
		);
		$invoker  = new Invoker($compiled);

		return new ExecutionEngine($invoker);
	}
}
