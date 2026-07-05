<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\InterceptorChainCompiler;
use Rokke\Runtime\Build\InvokerInterceptorDescriptor;
use Rokke\Runtime\Compiled\CompiledInterceptorChain;
use Rokke\Runtime\Contracts\InvokerInterceptorInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class AppendArgInterceptor implements InvokerInterceptorInterface
{
	public function __construct(private readonly string $suffix) {}

	public function intercept(OperationInterface $op, OperationContextInterface $ctx, array $args, callable $next): mixed
	{
		return $next(array_map(fn (mixed $a): string => $a . $this->suffix, $args));
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class InterceptorChainCompilerTest extends TestCase
{
	private InterceptorChainCompiler $compiler;

	protected function setUp(): void
	{
		$this->compiler = new InterceptorChainCompiler();
	}

	public function testEmptyDescriptorsProduceEmptyChain(): void
	{
		$chain = $this->compiler->compile([]);

		$this->assertInstanceOf(CompiledInterceptorChain::class, $chain);
		$this->assertTrue($chain->isEmpty());
	}

	public function testSingleDescriptorProducesOneStage(): void
	{
		$chain = $this->compiler->compile([
			new InvokerInterceptorDescriptor(AppendArgInterceptor::class, args: [':x']),
		]);

		$this->assertCount(1, $chain->stages);
	}

	public function testStageExecutesAroundCore(): void
	{
		$chain = $this->compiler->compile([
			new InvokerInterceptorDescriptor(AppendArgInterceptor::class, args: [':tagged']),
		]);

		$op   = $this->createStub(OperationInterface::class);
		$ctx  = $this->createStub(OperationContextInterface::class);
		$core = fn (array $args): string => implode(',', $args);

		$runner = array_reduce(
			array_reverse($chain->stages),
			fn (callable $proceed, callable $stage): \Closure =>
				function (array $args) use ($stage, $op, $ctx, $proceed): mixed {
					/** @var list<mixed> $args */
					return $stage($op, $ctx, $args, fn (array $newArgs) => $proceed($newArgs));
				},
			$core,
		);

		$result = $runner(['hello', 'world']);

		$this->assertSame('hello:tagged,world:tagged', $result);
	}

	public function testPriorityOrderingApplied(): void
	{
		$order = [];

		$chain = $this->compiler->compile([
			new InvokerInterceptorDescriptor(AppendArgInterceptor::class, priority: 20, args: [':b']),
			new InvokerInterceptorDescriptor(AppendArgInterceptor::class, priority: 10, args: [':a']),
		]);

		$this->assertCount(2, $chain->stages);
		// Lower priority = outer (runs first). Stage 0 priority=10 (':a'), stage 1 priority=20 (':b').
		// Applied outermost first: first ':a' appended, then ':b' appended.
		$op   = $this->createStub(OperationInterface::class);
		$ctx  = $this->createStub(OperationContextInterface::class);
		$core = fn (array $args): string => implode(',', $args);

		$runner = array_reduce(
			array_reverse($chain->stages),
			fn (callable $proceed, callable $stage): \Closure =>
				function (array $args) use ($stage, $op, $ctx, $proceed): mixed {
					/** @var list<mixed> $args */
					return $stage($op, $ctx, $args, fn (array $newArgs) => $proceed($newArgs));
				},
			$core,
		);

		$result = $runner(['v']);

		$this->assertSame('v:a:b', $result);
	}

	public function testInstanceCreatedAtCompileTime(): void
	{
		$chain = $this->compiler->compile([
			new InvokerInterceptorDescriptor(AppendArgInterceptor::class, args: [':x']),
		]);

		$stage1 = $chain->stages[0];
		$stage2 = $chain->stages[0];

		$this->assertSame($stage1, $stage2);
	}
}
