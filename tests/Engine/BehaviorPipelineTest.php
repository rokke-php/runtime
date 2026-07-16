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

// ── Behavior fixtures ─────────────────────────────────────────────────────────

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

// ── Handler fixtures ──────────────────────────────────────────────────────────

final class BehaviorLogHandler
{
	/** @var list<string> */
	public static array $log = [];

	public function __invoke(): string
	{
		self::$log[] = 'handler';

		return 'result';
	}
}

final class BehaviorBlockedHandler
{
	public static bool $called = false;

	public function __invoke(): string
	{
		self::$called = true;

		return 'should not reach';
	}
}

final class BehaviorDoneHandler
{
	public function __invoke(): string
	{
		return 'done';
	}
}

final class BehaviorValueHandler
{
	public function __invoke(): string
	{
		return 'value';
	}
}

final class BehaviorPlainHandler
{
	public function __invoke(): string
	{
		return 'plain';
	}
}

final class BehaviorGuardedResultHandler
{
	public function __invoke(): string
	{
		return 'guarded-result';
	}
}

final class BehaviorPlainResultHandler
{
	public function __invoke(): string
	{
		return 'plain-result';
	}
}

final class BehaviorOkHandler
{
	public function __invoke(): string
	{
		return 'ok';
	}
}

final class BehaviorOrderHandler
{
	/** @var list<string> */
	public static array $order = [];

	public function __invoke(): string
	{
		self::$order[] = 'handler';

		return 'done';
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class BehaviorPipelineTest extends TestCase
{
	protected function setUp(): void
	{
		BehaviorLogHandler::$log       = [];
		BehaviorBlockedHandler::$called = false;
		BehaviorOrderHandler::$order   = [];
	}

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
		$behavior = new LoggingBehavior();

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: BehaviorLogHandler::class,
			behaviors: [new BehaviorDescriptor($behavior)],
		));

		$runtime = (new DefaultRuntimeBuilder())->build($model);
		$result  = $runtime->execute($this->makeOp(), $this->makeCtx());

		$this->assertSame(['behavior:before', 'behavior:after'], $behavior->log);
		$this->assertSame(['handler'], BehaviorLogHandler::$log);
		$this->assertSame('result', $result);
	}

	public function testBlockingBehaviorPreventsHandlerExecution(): void
	{
		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: BehaviorBlockedHandler::class,
			behaviors: [new BehaviorDescriptor(new BlockingBehavior())],
		));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Access denied.');

		(new DefaultRuntimeBuilder())->build($model)->execute($this->makeOp(), $this->makeCtx());

		$this->assertFalse(BehaviorBlockedHandler::$called);
	}

	public function testBehaviorsExecuteInDeclaredOrder(): void
	{
		$first  = new LoggingBehavior('first');
		$second = new LoggingBehavior('second');

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: BehaviorDoneHandler::class,
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
			handler: BehaviorValueHandler::class,
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
			handler: BehaviorPlainHandler::class,
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
			handler: BehaviorGuardedResultHandler::class,
			behaviors: [new BehaviorDescriptor($behavior)],
		));
		$model->add(new OperationDefinition(
			id: 'plain',
			name: 'Plain',
			handler: BehaviorPlainResultHandler::class,
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
			handler: BehaviorOkHandler::class,
			behaviors: [new BehaviorDescriptor($behavior)],
		));

		(new DefaultRuntimeBuilder())->build($model)->execute($this->makeOp(), $ctx);

		$this->assertSame($ctx, $behavior->captured);
	}

	// ── Ordering guarantee: first wraps second ────────────────────────────────

	public function testFirstBehaviorWrapsSecondAsMiddlewareChain(): void
	{
		$first  = new LoggingBehavior('outer');
		$second = new LoggingBehavior('inner');

		$model = new ApplicationModel();
		$model->add(new OperationDefinition(
			id: 'op',
			name: 'Op',
			handler: BehaviorOrderHandler::class,
			behaviors: [
				new BehaviorDescriptor($first),
				new BehaviorDescriptor($second),
			],
		));

		(new DefaultRuntimeBuilder())->build($model)->execute($this->makeOp(), $this->makeCtx());

		$this->assertSame(['outer:before', 'outer:after'], $first->log);
		$this->assertSame(['inner:before', 'inner:after'], $second->log);
		$this->assertSame(['handler'], BehaviorOrderHandler::$order);
	}
}
