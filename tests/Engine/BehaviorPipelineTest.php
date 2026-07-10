<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\BehaviorDescriptor;
use Rokke\Runtime\Build\OperationDefinition;
use Rokke\Runtime\Builder\DefaultRuntimeBuilder;
use Rokke\Runtime\Contracts\ExecutionBehaviorInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

// ── Fixtures ─────────────────────────────────────────────────────────────────

final class RecordingBehavior implements ExecutionBehaviorInterface
{
	public bool $called = false;

	public function handle(OperationContextInterface $context, callable $next): mixed
	{
		$this->called = true;

		return $next();
	}
}

final class BlockingBehavior implements ExecutionBehaviorInterface
{
	public function handle(OperationContextInterface $context, callable $next): mixed
	{
		throw new \RuntimeException('Access denied.');
	}
}

final class MutatingBehavior implements ExecutionBehaviorInterface
{
	public function handle(OperationContextInterface $context, callable $next): mixed
	{
		$result = $next();

		return $result . '+mutated';
	}
}

final class LoggingBehavior implements ExecutionBehaviorInterface
{
	/** @var list<string> */
	public array $log = [];

	public function __construct(private readonly string $label = 'behavior') {}

	public function handle(OperationContextInterface $context, callable $next): mixed
	{
		$this->log[] = $this->label . ':before';
		$result       = $next();
		$this->log[] = $this->label . ':after';

		return $result;
	}
}

final class ContextCapturingBehavior implements ExecutionBehaviorInterface
{
	public ?OperationContextInterface $captured = null;

	public function handle(OperationContextInterface $context, callable $next): mixed
	{
		$this->captured = $context;

		return $next();
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class BehaviorPipelineTest extends TestCase
{
	private function makeOp(string $id = 'op'): OperationInterface
	{
		$op = $this->createStub(OperationInterface::class);
		$op->method('id')->willReturn($id);

		return $op;
	}

	private function makeCtx(): OperationContextInterface
	{
		return $this->createStub(OperationContextInterface::class);
	}

	// ── Core pipeline mechanics ───────────────────────────────────────────────

	public function testBehaviorExecutesBeforeHandler(): void
	{
		$handlerLog = [];
		$behavior   = new LoggingBehavior();

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: static function () use (&$handlerLog): string {
				$handlerLog[] = 'handler';

				return 'result';
			},
			behaviors: [new BehaviorDescriptor($behavior)],
		));

		$runtime = (new DefaultRuntimeBuilder())->build($model);
		$result  = $runtime->execute($this->makeOp(), $this->makeCtx());

		$this->assertSame(['behavior:before', 'behavior:after'], $behavior->log);
		$this->assertSame(['handler'], $handlerLog);
		$this->assertSame('result', $result);
	}

	public function testBlockingBehaviorPreventsHandlerExecution(): void
	{
		$handlerCalled = false;

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: static function () use (&$handlerCalled): string {
				$handlerCalled = true;

				return 'should not reach';
			},
			behaviors: [new BehaviorDescriptor(new BlockingBehavior())],
		));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Access denied.');

		(new DefaultRuntimeBuilder())->build($model)->execute($this->makeOp(), $this->makeCtx());

		$this->assertFalse($handlerCalled);
	}

	public function testBehaviorsExecuteInDeclaredOrder(): void
	{
		$first  = new LoggingBehavior('first');
		$second = new LoggingBehavior('second');

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: static fn (): string => 'done',
			behaviors: [
				new BehaviorDescriptor($first),
				new BehaviorDescriptor($second),
			],
		));

		(new DefaultRuntimeBuilder())->build($model)->execute($this->makeOp(), $this->makeCtx());

		$this->assertSame(['first:before', 'first:after'], $first->log);
		$this->assertSame(['second:before', 'second:after'], $second->log);
	}

	public function testBehaviorCanMutateReturnValue(): void
	{
		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: static fn (): string => 'value',
			behaviors: [new BehaviorDescriptor(new MutatingBehavior())],
		));

		$result = (new DefaultRuntimeBuilder())->build($model)->execute($this->makeOp(), $this->makeCtx());

		$this->assertSame('value+mutated', $result);
	}

	// ── Operations without behaviors are unaffected ───────────────────────────

	public function testOperationWithoutBehaviorsExecutesDirectly(): void
	{
		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: static fn (): string => 'plain',
		));

		$result = (new DefaultRuntimeBuilder())->build($model)->execute($this->makeOp(), $this->makeCtx());

		$this->assertSame('plain', $result);
	}

	public function testOperationsWithAndWithoutBehaviorsCoexist(): void
	{
		$behavior = new RecordingBehavior();

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'guarded',
			name: 'Guarded',
			handler: static fn (): string => 'guarded-result',
			behaviors: [new BehaviorDescriptor($behavior)],
		));
		$model->add(new OperationDefinition(
			id: 'plain',
			name: 'Plain',
			handler: static fn (): string => 'plain-result',
		));

		$runtime = (new DefaultRuntimeBuilder())->build($model);

		$this->assertSame('guarded-result', $runtime->execute($this->makeOp('guarded'), $this->makeCtx()));
		$this->assertTrue($behavior->called);

		$behavior->called = false;
		$this->assertSame('plain-result', $runtime->execute($this->makeOp('plain'), $this->makeCtx()));
		$this->assertFalse($behavior->called);
	}

	// ── OperationContext flows through the behavior chain ─────────────────────

	public function testBehaviorReceivesOperationContext(): void
	{
		$ctx      = $this->makeCtx();
		$behavior = new ContextCapturingBehavior();

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: static fn (): string => 'ok',
			behaviors: [new BehaviorDescriptor($behavior)],
		));

		(new DefaultRuntimeBuilder())->build($model)->execute($this->makeOp(), $ctx);

		$this->assertSame($ctx, $behavior->captured);
	}

	// ── Ordering guarantee: first wraps second ────────────────────────────────

	public function testFirstBehaviorWrapsSecondAsMiddlewareChain(): void
	{
		$order  = [];
		$first  = new LoggingBehavior('outer');
		$second = new LoggingBehavior('inner');

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: static function () use (&$order): string {
				$order[] = 'handler';

				return 'done';
			},
			behaviors: [
				new BehaviorDescriptor($first),
				new BehaviorDescriptor($second),
			],
		));

		(new DefaultRuntimeBuilder())->build($model)->execute($this->makeOp(), $this->makeCtx());

		$this->assertSame(['outer:before', 'outer:after'], $first->log);
		$this->assertSame(['inner:before', 'inner:after'], $second->log);
		$this->assertSame(['handler'], $order);
	}
}
