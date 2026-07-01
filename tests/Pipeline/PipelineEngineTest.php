<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Pipeline;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Contracts\HandlerInterface;
use Rokke\Runtime\Pipeline\PipelineEngine;
use Rokke\Runtime\ServiceContainer;
use RuntimeException;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class PipelineUpperCaseMiddleware
{
	public function process(mixed $input, callable $next): mixed
	{
		assert(is_scalar($input));
		return $next(strtoupper((string) $input));
	}
}

final class PipelineTrimMiddleware
{
	public function process(mixed $input, callable $next): mixed
	{
		assert(is_scalar($input));
		return $next(trim((string) $input));
	}
}

final class PipelineFinalHandler implements HandlerInterface
{
	public function handle(mixed $input): mixed
	{
		assert(is_scalar($input));
		return 'handled:' . (string) $input;
	}
}

// ── Tests ────────────────────────────────────────────────────────────────────

final class PipelineEngineTest extends TestCase
{
	private PipelineEngine $pipeline;
	private ServiceContainer $container;

	protected function setUp(): void
	{
		$this->container = new ServiceContainer();
		$this->pipeline  = new PipelineEngine($this->container);
	}

	public function testReachesHandlerWithNoMiddlewares(): void
	{
		$result = $this->pipeline
			->send('hello')
			->through([])
			->then(new PipelineFinalHandler());

		$this->assertSame('handled:hello', $result);
	}

	public function testReachesHandlerViaCallable(): void
	{
		$result = $this->pipeline
			->send(42)
			->through([])
			->then(function (mixed $input): int {
				assert(is_int($input));
				return $input * 2;
			});

		$this->assertSame(84, $result);
	}

	public function testMiddlewareObjectTransformsInput(): void
	{
		$result = $this->pipeline
			->send('hello')
			->through([new PipelineUpperCaseMiddleware()])
			->then(new PipelineFinalHandler());

		$this->assertSame('handled:HELLO', $result);
	}

	public function testMultipleMiddlewaresExecuteInOrder(): void
	{
		$result = $this->pipeline
			->send('  hello  ')
			->through([
				new PipelineTrimMiddleware(),
				new PipelineUpperCaseMiddleware(),
			])
			->then(new PipelineFinalHandler());

		$this->assertSame('handled:HELLO', $result);
	}

	public function testMiddlewareResolvedByClassNameFromContainer(): void
	{
		$result = $this->pipeline
			->send('world')
			->through([PipelineUpperCaseMiddleware::class])
			->then(new PipelineFinalHandler());

		$this->assertSame('handled:WORLD', $result);
	}

	public function testCallableMiddlewareIsSupported(): void
	{
		$middleware = function (mixed $input, callable $next): mixed {
			assert(is_scalar($input));
			return $next('[' . (string) $input . ']');
		};

		$result = $this->pipeline
			->send('rokke')
			->through([$middleware])
			->then(new PipelineFinalHandler());

		$this->assertSame('handled:[rokke]', $result);
	}

	public function testThrowsForInvalidMiddleware(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('process()');

		$this->pipeline
			->send('x')
			->through([new \stdClass()])
			->then(fn ($input) => $input);
	}

	public function testMiddlewareCanShortCircuitPipeline(): void
	{
		$shortCircuit = fn ($input, callable $next) => 'short-circuit';

		$result = $this->pipeline
			->send('ignored')
			->through([$shortCircuit])
			->then(function (mixed $input): string {
				assert(is_scalar($input));
				return 'should-not-reach:' . (string) $input;
			});

		$this->assertSame('short-circuit', $result);
	}

	public function testPipelineIsReusableAfterSend(): void
	{
		$pipeline = new PipelineEngine($this->container);

		$first = $pipeline->send('a')->through([])->then(function (mixed $v): string {
			assert(is_scalar($v));
			return 'got:' . (string) $v;
		});
		$pipeline->send('b');
		$second = $pipeline->through([])->then(function (mixed $v): string {
			assert(is_scalar($v));
			return 'got:' . (string) $v;
		});

		$this->assertSame('got:a', $first);
		$this->assertSame('got:b', $second);
	}
}
