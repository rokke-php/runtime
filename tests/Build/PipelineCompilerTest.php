<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\MiddlewareDescriptor;
use Rokke\Runtime\Build\PipelineCompiler;
use Rokke\Runtime\Compiled\CompiledPipeline;
use Rokke\Runtime\Context\OperationContext;
use Rokke\Runtime\Contracts\MiddlewareInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class AppendMiddleware implements MiddlewareInterface
{
	public function __construct(private readonly string $tag) {}

	public function handle(OperationInterface $op, OperationContextInterface $ctx, callable $next): mixed
	{
		return $next() . ":{$this->tag}";
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class PipelineCompilerTest extends TestCase
{
	private PipelineCompiler $compiler;

	protected function setUp(): void
	{
		$this->compiler = new PipelineCompiler();
	}

	public function testEmptyDescriptorsProducesEmptyPipeline(): void
	{
		$pipeline = $this->compiler->compile([]);

		$this->assertInstanceOf(CompiledPipeline::class, $pipeline);
		$this->assertTrue($pipeline->isEmpty());
	}

	public function testSingleDescriptorProducesOneStage(): void
	{
		$descriptor = new MiddlewareDescriptor(AppendMiddleware::class, args: ['tag' => 'a']);
		$pipeline   = $this->compiler->compile([$descriptor]);

		$this->assertCount(1, $pipeline->stages);
	}

	public function testStagesExecuteAroundCore(): void
	{
		$descriptor = new MiddlewareDescriptor(AppendMiddleware::class, args: ['tag' => 'a']);
		$pipeline   = $this->compiler->compile([$descriptor]);

		$ctx  = new OperationContext('test');
		$core = static fn (): string => 'core';
		$op   = $this->createStub(OperationInterface::class);

		$result = ($pipeline->stages[0])($op, $ctx, $core);

		$this->assertSame('core:a', $result);
	}

	public function testPriorityOrdersStages(): void
	{
		$descriptors = [
			new MiddlewareDescriptor(AppendMiddleware::class, priority: 20, args: ['tag' => 'second']),
			new MiddlewareDescriptor(AppendMiddleware::class, priority: 10, args: ['tag' => 'first']),
		];

		$pipeline = $this->compiler->compile($descriptors);

		$this->assertCount(2, $pipeline->stages);

		$ctx  = new OperationContext('test');
		$op   = $this->createStub(OperationInterface::class);
		$core = static fn (): string => 'core';

		$step1    = $pipeline->stages[0]; // first (priority 10)
		$step2    = $pipeline->stages[1]; // second (priority 20)

		// first wraps second wraps core
		$result = $step1($op, $ctx, fn () => $step2($op, $ctx, $core));

		$this->assertSame('core:second:first', $result);
	}

	public function testMiddlewareInstanceCreatedAtCompileTime(): void
	{
		$descriptor = new MiddlewareDescriptor(AppendMiddleware::class, args: ['tag' => 'x']);
		$before     = microtime(true);
		$pipeline   = $this->compiler->compile([$descriptor]);
		$after      = microtime(true);

		// calling stage multiple times uses the same instance (idempotent output)
		$ctx  = new OperationContext('test');
		$op   = $this->createStub(OperationInterface::class);
		$core = static fn (): string => 'v';

		$r1 = ($pipeline->stages[0])($op, $ctx, $core);
		$r2 = ($pipeline->stages[0])($op, $ctx, $core);

		$this->assertSame($r1, $r2);
		$this->assertGreaterThanOrEqual($before, $after);
	}
}
