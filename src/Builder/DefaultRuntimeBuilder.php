<?php

declare(strict_types=1);

namespace Rokke\Runtime\Builder;

use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\FactoryCompiler;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\OperationDefinition;
use Rokke\Runtime\Build\ServiceDescriptor;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Contracts\RuntimeInterface;
use Rokke\Runtime\Engine\ExecutionEngine;
use Rokke\Runtime\Engine\Invoker;

final class DefaultRuntimeBuilder
{
	public function build(ApplicationModel $model): RuntimeInterface
	{
		$handlers   = [];
		$operations = [];

		foreach ($model->definitions(OperationDefinition::class) as $index => $definition) {
			$handlers[$index]            = $definition->handler;
			$operations[$definition->id] = new CompiledOperation(0, $index, 0, 0);
		}

		$factories = FactoryRepository::build(
			$model->definitions(ServiceDescriptor::class),
			new FactoryCompiler(),
		);

		$compiled = new CompiledRuntime([], $handlers, [], [], $operations, $factories);
		$invoker  = new Invoker($compiled);

		return new ExecutionEngine($invoker);
	}
}
