<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\OperationDefinition;
use Rokke\Runtime\Builder\DefaultRuntimeBuilder;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Compiled\CompiledBehaviorPipeline;
use Rokke\Runtime\Compiled\CompiledExecutionPipeline;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;
use Rokke\Runtime\Contracts\ExecutionBehaviorInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;

final class CompiledExecutionPipelineTest extends TestCase
{
	private function makeCtx(): OperationContextInterface
	{
		return $this->createStub(OperationContextInterface::class);
	}

	private function scalarResultPlan(): ResultResolutionPlan
	{
		return new ResultResolutionPlan(new ScalarResultInstruction('string'));
	}

	private function makePipeline(
		callable $handler,
		ArgumentResolutionPlan $argPlan,
		ResultResolutionPlan $resultPlan,
		?CompiledBehaviorPipeline $behaviors = null,
	): CompiledExecutionPipeline {
		return new CompiledExecutionPipeline(
			handlers: [0 => $handler],
			argumentPlans: [0 => $argPlan],
			resultPlans: [0 => $resultPlan],
			behaviorPipelines: $behaviors !== null ? [0 => $behaviors] : [],
			validationPlans: [],
		);
	}

	private function makeOp(
		int $handlerId = 0,
		int $argPlanId = 0,
		int $resultPlanId = 0,
		?int $behaviorPipelineId = null,
	): CompiledOperation {
		return new CompiledOperation(
			id: 'op',
			pipelineId: 0,
			handlerId: $handlerId,
			argumentPlanId: $argPlanId,
			resultPlanId: $resultPlanId,
			behaviorPipelineId: $behaviorPipelineId,
		);
	}

	// ── Invocation ────────────────────────────────────────────────────────────

	public function testHandlerIsCalledAndResultReturned(): void
	{
		$pipeline = $this->makePipeline(
			handler: static fn (): string => 'hello',
			argPlan: new ArgumentResolutionPlan([]),
			resultPlan: $this->scalarResultPlan(),
		);

		$result = $pipeline->execute($this->makeOp(), $this->makeCtx());

		$this->assertSame('hello', $result);
	}

	public function testHandlerReceivesResolvedArguments(): void
	{
		$ctx      = $this->createStub(OperationContextInterface::class);
		$received = null;

		$pipeline = $this->makePipeline(
			handler: function (OperationContextInterface $c) use (&$received): void {
				$received = $c;
			},
			argPlan: new ArgumentResolutionPlan([new ContextArgumentInstruction()]),
			resultPlan: new ResultResolutionPlan(new \Rokke\Runtime\Compiled\Results\VoidResultInstruction()),
		);

		$op = new CompiledOperation('op', 0, 0, 0, 0);
		$pipeline->execute($op, $ctx);

		$this->assertSame($ctx, $received);
	}

	// ── Multiple handlers / IDs ───────────────────────────────────────────────

	public function testCorrectHandlerSelectedByHandlerId(): void
	{
		$pipeline = new CompiledExecutionPipeline(
			handlers: [0 => static fn (): string => 'wrong', 1 => static fn (): string => 'right'],
			argumentPlans: [0 => new ArgumentResolutionPlan([]), 1 => new ArgumentResolutionPlan([])],
			resultPlans: [0 => $this->scalarResultPlan(), 1 => $this->scalarResultPlan()],
			behaviorPipelines: [],
			validationPlans: [],
		);

		$op     = new CompiledOperation('op', 0, 1, 1, 1);
		$result = $pipeline->execute($op, $this->makeCtx());

		$this->assertSame('right', $result);
	}

	// ── BehaviorPipeline integration ──────────────────────────────────────────

	public function testBehaviorPipelineExecutesAroundHandler(): void
	{
		$log      = [];
		$behavior = new class ($log) implements ExecutionBehaviorInterface {
			/** @param list<string> $log */
			public function __construct(private array &$log) {}

			public function handle(OperationContextInterface $context, callable $next): mixed
			{
				$this->log[] = 'behavior';

				return $next();
			}
		};

		$behaviorPipeline = new CompiledBehaviorPipeline([$behavior]);
		$pipeline         = $this->makePipeline(
			handler: static function () use (&$log): string {
				$log[] = 'handler';
				return 'result';
			},
			argPlan: new ArgumentResolutionPlan([]),
			resultPlan: $this->scalarResultPlan(),
			behaviors: $behaviorPipeline,
		);

		$op     = new CompiledOperation('op', 0, 0, 0, 0, behaviorPipelineId: 0);
		$result = $pipeline->execute($op, $this->makeCtx());

		$this->assertSame(['behavior', 'handler'], $log);
		$this->assertSame('result', $result);
	}

	public function testNullBehaviorPipelineIdSkipsBehaviors(): void
	{
		$pipeline = $this->makePipeline(
			handler: static fn (): string => 'direct',
			argPlan: new ArgumentResolutionPlan([]),
			resultPlan: $this->scalarResultPlan(),
		);

		$op     = $this->makeOp(behaviorPipelineId: null);
		$result = $pipeline->execute($op, $this->makeCtx());

		$this->assertSame('direct', $result);
	}

	// ── Error propagation ─────────────────────────────────────────────────────

	public function testThrowsWhenHandlerIdMissing(): void
	{
		$pipeline = new CompiledExecutionPipeline(
			handlers: [],
			argumentPlans: [0 => new ArgumentResolutionPlan([])],
			resultPlans: [0 => $this->scalarResultPlan()],
			behaviorPipelines: [],
			validationPlans: [],
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Handler #0 not found');

		$pipeline->execute($this->makeOp(), $this->makeCtx());
	}

	public function testThrowsWhenArgumentPlanMissing(): void
	{
		$pipeline = new CompiledExecutionPipeline(
			handlers: [0 => static fn (): string => 'ok'],
			argumentPlans: [],
			resultPlans: [0 => $this->scalarResultPlan()],
			behaviorPipelines: [],
			validationPlans: [],
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('ArgumentResolutionPlan #0 not found');

		$pipeline->execute($this->makeOp(), $this->makeCtx());
	}

	public function testThrowsWhenResultPlanMissing(): void
	{
		$pipeline = new CompiledExecutionPipeline(
			handlers: [0 => static fn (): string => 'ok'],
			argumentPlans: [0 => new ArgumentResolutionPlan([])],
			resultPlans: [],
			behaviorPipelines: [],
			validationPlans: [],
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('ResultResolutionPlan #0 not found');

		$pipeline->execute($this->makeOp(), $this->makeCtx());
	}

	// ── End-to-end via DefaultRuntimeBuilder ─────────────────────────────────

	public function testBuilderProducesWorkingPipelineEndToEnd(): void
	{
		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'greet',
			name: 'Greet',
			handler: static fn (): string => 'hello',
		));

		$runtime = (new DefaultRuntimeBuilder())->build($model);

		$op  = $this->createStub(\Rokke\Runtime\Contracts\OperationInterface::class);
		$op->method('id')->willReturn('greet');

		$result = $runtime->execute($op, $this->makeCtx());

		$this->assertSame('hello', $result);
	}
}
