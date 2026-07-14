<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Execution\ExecutionInterceptorInterface;
use Rokke\Runtime\Compiled\CompiledInterceptorPipeline;
use Rokke\Runtime\Contracts\OperationContextInterface;

// ── Fixtures ─────────────────────────────────────────────────────────────────

final class RecordingInterceptor implements ExecutionInterceptorInterface
{
	public bool $called = false;

	public function intercept(object $context, callable $next): mixed
	{
		$this->called = true;

		return $next();
	}
}

final class BlockingInterceptor implements ExecutionInterceptorInterface
{
	public function intercept(object $context, callable $next): mixed
	{
		throw new \RuntimeException('Interceptor blocked execution.');
	}
}

final class MutatingInterceptor implements ExecutionInterceptorInterface
{
	public function intercept(object $context, callable $next): mixed
	{
		return $next() . '+observed';
	}
}

final class OrderedInterceptor implements ExecutionInterceptorInterface
{
	/** @var list<string> */
	public array $log = [];

	public function __construct(private readonly string $label) {}

	public function intercept(object $context, callable $next): mixed
	{
		$this->log[] = $this->label . ':before';
		$result       = $next();
		$this->log[] = $this->label . ':after';

		return $result;
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class InterceptorPipelineTest extends TestCase
{
	private function makeCtx(): OperationContextInterface
	{
		return $this->createStub(OperationContextInterface::class);
	}

	// ── Empty pipeline ────────────────────────────────────────────────────────

	public function testEmptyPipelineCallsCoreDirectly(): void
	{
		$pipeline = CompiledInterceptorPipeline::empty();
		$result   = $pipeline->execute($this->makeCtx(), static fn (): string => 'core');

		$this->assertSame('core', $result);
	}

	// ── Single interceptor ────────────────────────────────────────────────────

	public function testInterceptorWrapsExecution(): void
	{
		$interceptor = new RecordingInterceptor();
		$pipeline    = new CompiledInterceptorPipeline([$interceptor]);

		$result = $pipeline->execute($this->makeCtx(), static fn (): string => 'ok');

		$this->assertTrue($interceptor->called);
		$this->assertSame('ok', $result);
	}

	public function testInterceptorCanHaltExecution(): void
	{
		$pipeline = new CompiledInterceptorPipeline([new BlockingInterceptor()]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Interceptor blocked execution.');

		$pipeline->execute($this->makeCtx(), static fn (): string => 'never');
	}

	public function testInterceptorReceivesContext(): void
	{
		$ctx      = $this->makeCtx();
		$received = null;

		$interceptor = new class ($received) implements ExecutionInterceptorInterface {
			public function __construct(private mixed &$received) {}

			public function intercept(object $context, callable $next): mixed
			{
				$this->received = $context;

				return $next();
			}
		};

		$pipeline = new CompiledInterceptorPipeline([$interceptor]);
		$pipeline->execute($ctx, static fn (): string => 'ok');

		$this->assertSame($ctx, $received);
	}

	// ── Multiple interceptors — ordering ──────────────────────────────────────

	public function testInterceptorsExecuteInDeclaredOrder(): void
	{
		$outer = new OrderedInterceptor('outer');
		$inner = new OrderedInterceptor('inner');

		$pipeline = new CompiledInterceptorPipeline([$outer, $inner]);
		$pipeline->execute($this->makeCtx(), static fn (): string => 'core');

		$this->assertSame(['outer:before', 'outer:after'], $outer->log);
		$this->assertSame(['inner:before', 'inner:after'], $inner->log);
	}

	public function testInterceptorCanMutateReturnValue(): void
	{
		$pipeline = new CompiledInterceptorPipeline([new MutatingInterceptor()]);
		$result   = $pipeline->execute($this->makeCtx(), static fn (): string => 'value');

		$this->assertSame('value+observed', $result);
	}

	// ── Does not affect functional behaviour ──────────────────────────────────

	public function testMultipleInterceptorsAllObserveExecution(): void
	{
		$a = new RecordingInterceptor();
		$b = new RecordingInterceptor();

		$pipeline = new CompiledInterceptorPipeline([$a, $b]);
		$pipeline->execute($this->makeCtx(), static fn (): string => 'core');

		$this->assertTrue($a->called);
		$this->assertTrue($b->called);
	}

	// ── Static constructor ────────────────────────────────────────────────────

	public function testEmptyStaticConstructorProducesNoOpPipeline(): void
	{
		$interceptor = new RecordingInterceptor();
		$empty       = CompiledInterceptorPipeline::empty();

		$result = $empty->execute($this->makeCtx(), static fn (): string => 'direct');

		$this->assertFalse($interceptor->called);
		$this->assertSame('direct', $result);
	}
}
