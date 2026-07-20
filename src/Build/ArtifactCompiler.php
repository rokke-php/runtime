<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Runtime\Build\CodeGen\Node\ArrayNode;
use Rokke\Runtime\Build\CodeGen\Node\ClassReferenceNode;
use Rokke\Runtime\Build\CodeGen\Node\LiteralNode;
use Rokke\Runtime\Build\CodeGen\Node\NewObjectNode;
use Rokke\Runtime\Build\CodeGen\Node\StaticCallNode;
use Rokke\Runtime\Build\CodeGen\NodeInterface;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Compiled\Arguments\FactoryArgumentInstruction;
use Rokke\Runtime\Compiled\CompiledConfigurationRepository;
use Rokke\Runtime\Compiled\CompiledExecutionPipeline;
use Rokke\Runtime\Compiled\CompiledInterceptorPipeline;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\CompiledRuntime;
use Rokke\Runtime\Compiled\OperationRepository;
use Rokke\Runtime\Compiled\Results\NeverResultInstruction;
use Rokke\Runtime\Compiled\Results\ObjectResultInstruction;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;
use Rokke\Runtime\Compiled\Results\VoidResultInstruction;
use Rokke\Runtime\Compiled\ValidationPlan;

final class ArtifactCompiler
{
	public function compile(CompiledRuntime $runtime): NodeInterface
	{
		$repoNode = $this->emitFactoryRepository($runtime->factories);
		$pipeline = $runtime->executionPipeline;
		$ops      = $runtime->operations->all();

		$argPlanNodes        = [];
		$resultPlanNodes     = [];
		$validationPlanNodes = [];
		$compiledOpNodes     = [];

		foreach ($ops as $op) {
			$argPlanNodes[$op->argumentPlanId]  = $this->emitArgumentPlan($pipeline, $op->argumentPlanId, $repoNode);
			$resultPlanNodes[$op->resultPlanId] = $this->emitResultPlan($pipeline->resultPlan($op->resultPlanId));

			$vPlan                                       = $pipeline->validationPlan($op->validationPlanId);
			$validationPlanNodes[$op->validationPlanId]  = $vPlan !== null && !$vPlan->isEmpty()
				? $this->emitValidationPlan($vPlan)
				: new StaticCallNode(ValidationPlan::class, 'empty');

			$compiledOpNodes[] = $this->emitOperation($op);
		}

		$pipelineNode = new NewObjectNode(CompiledExecutionPipeline::class, [
			'factories'         => $repoNode,
			'argumentPlans'     => new ArrayNode($argPlanNodes),
			'resultPlans'       => new ArrayNode($resultPlanNodes),
			'behaviorPipelines' => new ArrayNode([]),
			'validationPlans'   => new ArrayNode($validationPlanNodes),
		]);

		$opsNode = new StaticCallNode(OperationRepository::class, 'build', [
			new ArrayNode($compiledOpNodes),
		]);

		return new NewObjectNode(CompiledRuntime::class, [
			'executionPipeline'   => $pipelineNode,
			'interceptorPipeline' => new StaticCallNode(CompiledInterceptorPipeline::class, 'empty'),
			'operations'          => $opsNode,
			'factories'           => $repoNode,
			'configurations'      => $this->emitConfigurationRepository($runtime->configurations()),
		]);
	}

	private function emitFactoryRepository(FactoryRepository $repo): NodeInterface
	{
		$descriptorNodes = [];

		foreach ($repo->descriptors() as $factory) {
			$depsNode = new ArrayNode(
				array_map(static fn (int $id): NodeInterface => new LiteralNode($id), $factory->dependencies),
			);

			$args = [
				'implementation' => new ClassReferenceNode($factory->implementation),
				'dependencies'   => $depsNode,
			];

			if ($factory->aliases !== []) {
				$args['aliases'] = new ArrayNode(
					array_map(static fn (string $a): NodeInterface => new LiteralNode($a), $factory->aliases),
				);
			}

			$descriptorNodes[] = new NewObjectNode(CompiledFactory::class, $args);
		}

		return new StaticCallNode(FactoryRepository::class, 'fromDescriptors', [
			new ArrayNode($descriptorNodes),
		]);
	}

	private function emitArgumentPlan(CompiledExecutionPipeline $pipeline, int $id, NodeInterface $repoNode): NodeInterface
	{
		$plan       = $pipeline->argumentPlan($id);
		$instrNodes = [];

		foreach ($plan->instructions as $instr) {
			$instrNodes[] = match (true) {
				$instr instanceof ContextArgumentInstruction => new NewObjectNode(ContextArgumentInstruction::class),
				$instr instanceof FactoryArgumentInstruction => new NewObjectNode(FactoryArgumentInstruction::class, [
					'factoryId' => new LiteralNode($instr->factoryId),
				]),
				default => throw new \RuntimeException('Unsupported argument instruction: ' . $instr::class),
			};
		}

		return new NewObjectNode(ArgumentResolutionPlan::class, [
			'instructions' => new ArrayNode($instrNodes),
		]);
	}

	private function emitResultPlan(ResultResolutionPlan $plan): NodeInterface
	{
		$instr     = $plan->instruction;
		$instrNode = match (true) {
			$instr instanceof ScalarResultInstruction => new NewObjectNode(ScalarResultInstruction::class, [
				'scalarType' => new LiteralNode($instr->scalarType),
			]),
			$instr instanceof VoidResultInstruction   => new NewObjectNode(VoidResultInstruction::class),
			$instr instanceof NeverResultInstruction  => new NewObjectNode(NeverResultInstruction::class),
			$instr instanceof ObjectResultInstruction => new NewObjectNode(ObjectResultInstruction::class, [
				'contract' => new ClassReferenceNode($instr->contract),
			]),
			default => throw new \RuntimeException('Unsupported result instruction: ' . $instr::class),
		};

		return new NewObjectNode(ResultResolutionPlan::class, [
			'instruction' => $instrNode,
		]);
	}

	private function emitValidationPlan(ValidationPlan $plan): NodeInterface
	{
		$paramNodes = [];

		foreach ($plan->params() as $param) {
			$instrNodes = [];

			foreach ($param->instructions as $instr) {
				$instrNodes[] = match (true) {
					$instr instanceof NotBlankValidationInstruction => new NewObjectNode(NotBlankValidationInstruction::class),
					$instr instanceof MaxValidationInstruction      => new NewObjectNode(MaxValidationInstruction::class, [
						'max' => new LiteralNode($instr->max),
					]),
					$instr instanceof MinValidationInstruction      => new NewObjectNode(MinValidationInstruction::class, [
						'min' => new LiteralNode($instr->min),
					]),
					default => throw new \RuntimeException('Unsupported validation instruction: ' . $instr::class),
				};
			}

			$paramNodes[] = new NewObjectNode(\Rokke\Runtime\Compiled\ParameterValidationPlan::class, [
				'index'        => new LiteralNode($param->index),
				'name'         => new LiteralNode($param->name),
				'instructions' => new ArrayNode($instrNodes),
			]);
		}

		return new NewObjectNode(ValidationPlan::class, [
			'params' => new ArrayNode($paramNodes),
		]);
	}

	private function emitConfigurationRepository(CompiledConfigurationRepository $repo): NodeInterface
	{
		$configNodes = array_map(
			fn (object $config): NodeInterface => $this->emitCompiledConfiguration($config),
			$repo->all(),
		);

		return new StaticCallNode(CompiledConfigurationRepository::class, 'build', [
			new ArrayNode($configNodes),
		]);
	}

	private function emitCompiledConfiguration(object $config): NodeInterface
	{
		$class = $config::class;
		$ref   = new \ReflectionClass($config);
		$ctor  = $ref->getConstructor();

		if ($ctor === null) {
			return new NewObjectNode($class, []);
		}

		$argNodes = [];

		foreach ($ctor->getParameters() as $param) {
			$name  = $param->getName();
			$prop  = $ref->getProperty($name);
			$value = $prop->getValue($config);

			if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value) && $value !== null) {
				throw new \RuntimeException(
					"Cannot emit configuration property '{$class}::\${$name}': " .
					'only string, int, float, bool, and null are supported in v0.23.0.',
				);
			}

			$argNodes[$name] = new LiteralNode($value);
		}

		return new NewObjectNode($class, $argNodes);
	}

	private function emitOperation(CompiledOperation $op): NodeInterface
	{
		$args = [
			'id'             => new LiteralNode($op->id),
			'pipelineId'     => new LiteralNode($op->pipelineId),
			'factoryId'      => new LiteralNode($op->factoryId),
			'argumentPlanId' => new LiteralNode($op->argumentPlanId),
			'resultPlanId'   => new LiteralNode($op->resultPlanId),
		];

		if ($op->interceptorChainId !== 0) {
			$args['interceptorChainId'] = new LiteralNode($op->interceptorChainId);
		}

		if ($op->validationPlanId !== 0) {
			$args['validationPlanId'] = new LiteralNode($op->validationPlanId);
		}

		if ($op->behaviorPipelineId !== null) {
			throw new \RuntimeException(
				"Operation '{$op->id}' has a behavior pipeline — behavior pipelines are not yet supported in artifact generation.",
			);
		}

		return new NewObjectNode(CompiledOperation::class, $args);
	}
}
