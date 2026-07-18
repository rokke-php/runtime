<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Build\OperationDefinition;
use Rokke\Runtime\Builder\DefaultRuntimeBuilder;
use Rokke\Runtime\Compiled\Arguments\ArgumentResolutionPlan;
use Rokke\Runtime\Compiled\Arguments\ContextArgumentInstruction;
use Rokke\Runtime\Compiled\CompiledBehaviorPipeline;
use Rokke\Runtime\Compiled\CompiledExecutionPipeline;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\Results\ResultResolutionPlan;
use Rokke\Runtime\Compiled\Results\ScalarResultInstruction;
use Rokke\Runtime\Compiled\Results\VoidResultInstruction;
use Rokke\Runtime\Contracts\ExecutionBehaviorInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class PipelineHelloHandler
{
	public function __invoke(): string
	{
		return 'hello';
	}
}

final class PipelineWrongHandler
{
	public function __invoke(): string
	{
		return 'wrong';
	}
}

final class PipelineRightHandler
{
	public function __invoke(): string
	{
		return 'right';
	}
}

final class PipelineContextCaptureHandler
{
	public static ?OperationContextInterface $received = null;

	public function __invoke(OperationContextInterface $c): void
	{
		self::$received = $c;
	}
}

final class PipelineBehaviorHandler
{
	public function __invoke(): string
	{
		return 'result';
	}
}

// ── Test ──────────────────────────────────────────────────────────────────────

final class CompiledExecutionPipelineTest extends TestCase
{
	protected function setUp(): void
	{
		PipelineContextCaptureHandler::$received = null;
	}

	private function makeCtx(): OperationContextInterface
	{
		return $this->createStub(OperationContextInterface::class);
	}

	private function scalarResultPlan(): ResultResolutionPlan
	{
		return new ResultResolutionPlan(new ScalarResultInstruction('string'));
	}

	/** @param class-string $class */
	private function singleHandlerRepo(string $class): FactoryRepository
	{
		return FactoryRepository::fromDescriptors([new CompiledFactory($class)]);
	}

	/**
	 * @param class-string $handlerClass
	 */
	private function makePipeline(
		string $handlerClass,
		ArgumentResolutionPlan $argPlan,
		ResultResolutionPlan $resultPlan,
		?CompiledBehaviorPipeline $behaviors = null,
	): CompiledExecutionPipeline {
		return new CompiledExecutionPipeline(
			factories: $this->singleHandlerRepo($handlerClass),
			argumentPlans: [0 => $argPlan],
			resultPlans: [0 => $resultPlan],
			behaviorPipelines: $behaviors !== null ? [0 => $behaviors] : [],
			validationPlans: [],
		);
	}

	private function makeOp(
		int $factoryId = 0,
		int $argPlanId = 0,
		int $resultPlanId = 0,
		?int $behaviorPipelineId = null,
	): CompiledOperation {
		return new CompiledOperation(
			id: 'op',
			pipelineId: 0,
			factoryId: $factoryId,
			argumentPlanId: $argPlanId,
			resultPlanId: $resultPlanId,
			behaviorPipelineId: $behaviorPipelineId,
		);
	}

	// ── Invocation ────────────────────────────────────────────────────────────

	public function testHandlerIsCalledAndResultReturned(): void
	{
		$pipeline = $this->makePipeline(
			handlerClass: PipelineHelloHandler::class,
			argPlan: new ArgumentResolutionPlan([]),
			resultPlan: $this->scalarResultPlan(),
		);

		$this->assertSame('hello', $pipeline->execute($this->makeOp(), $this->makeCtx()));
	}

	public function testHandlerReceivesResolvedArguments(): void
	{
		$ctx      = $this->createStub(OperationContextInterface::class);
		$pipeline = $this->makePipeline(
			handlerClass: PipelineContextCaptureHandler::class,
			argPlan: new ArgumentResolutionPlan([new ContextArgumentInstruction()]),
			resultPlan: new ResultResolutionPlan(new VoidResultInstruction()),
		);

		$pipeline->execute($this->makeOp(), $ctx);

		$this->assertSame($ctx, PipelineContextCaptureHandler::$received);
	}

	// ── Multiple handlers / IDs ───────────────────────────────────────────────

	public function testCorrectHandlerSelectedByFactoryId(): void
	{
		$repo = FactoryRepository::fromDescriptors([
			new CompiledFactory(PipelineWrongHandler::class),  // id=0
			new CompiledFactory(PipelineRightHandler::class),  // id=1
		]);

		$pipeline = new CompiledExecutionPipeline(
			factories: $repo,
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

		$pipeline = new CompiledExecutionPipeline(
			factories: $this->singleHandlerRepo(PipelineHelloHandler::class),
			argumentPlans: [0 => new ArgumentResolutionPlan([])],
			resultPlans: [0 => $this->scalarResultPlan()],
			behaviorPipelines: [0 => new CompiledBehaviorPipeline([$behavior])],
			validationPlans: [],
		);

		$op     = new CompiledOperation('op', 0, 0, 0, 0, behaviorPipelineId: 0);
		$result = $pipeline->execute($op, $this->makeCtx());

		$this->assertSame(['behavior'], $log);
		$this->assertSame('hello', $result);
	}

	public function testNullBehaviorPipelineIdSkipsBehaviors(): void
	{
		$pipeline = $this->makePipeline(
			handlerClass: PipelineHelloHandler::class,
			argPlan: new ArgumentResolutionPlan([]),
			resultPlan: $this->scalarResultPlan(),
		);

		$this->assertSame('hello', $pipeline->execute($this->makeOp(behaviorPipelineId: null), $this->makeCtx()));
	}

	// ── Error propagation ─────────────────────────────────────────────────────

	public function testThrowsWhenFactoryIdMissing(): void
	{
		$pipeline = new CompiledExecutionPipeline(
			factories: FactoryRepository::fromDescriptors([]),
			argumentPlans: [0 => new ArgumentResolutionPlan([])],
			resultPlans: [0 => $this->scalarResultPlan()],
			behaviorPipelines: [],
			validationPlans: [],
		);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('No factory registered for ID 0');

		$pipeline->execute($this->makeOp(), $this->makeCtx());
	}

	public function testThrowsWhenArgumentPlanMissing(): void
	{
		$pipeline = new CompiledExecutionPipeline(
			factories: $this->singleHandlerRepo(PipelineHelloHandler::class),
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
			factories: $this->singleHandlerRepo(PipelineHelloHandler::class),
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
		$model->add(new OperationDefinition(id: 'greet', name: 'Greet', handler: PipelineHelloHandler::class));

		$runtime = (new DefaultRuntimeBuilder())->build($model);

		$op = $this->createStub(\Rokke\Runtime\Contracts\OperationInterface::class);
		$op->method('id')->willReturn('greet');

		$this->assertSame('hello', $runtime->execute($op, $this->makeCtx()));
	}
}
