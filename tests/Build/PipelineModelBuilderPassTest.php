<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\MiddlewareCapability;
use Rokke\Runtime\Build\MiddlewareDescriptor;
use Rokke\Runtime\Build\PipelineModelBuilderPass;
use Rokke\Runtime\Build\ServiceCapability;
use Rokke\Runtime\Contracts\MiddlewareInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class StubMiddlewareA implements MiddlewareInterface
{
	public function handle(OperationInterface $op, OperationContextInterface $ctx, callable $next): mixed
	{
		return $next();
	}
}

final class StubMiddlewareB implements MiddlewareInterface
{
	public function handle(OperationInterface $op, OperationContextInterface $ctx, callable $next): mixed
	{
		return $next();
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class PipelineModelBuilderPassTest extends TestCase
{
	private PipelineModelBuilderPass $pass;
	private ApplicationModel $model;

	protected function setUp(): void
	{
		$this->pass  = new PipelineModelBuilderPass();
		$this->model = new ApplicationModel();
	}

	public function testIgnoresNonMiddlewareCapabilities(): void
	{
		$this->pass->process([new ServiceCapability(\stdClass::class)], $this->model);

		$this->assertSame([], $this->model->definitions(MiddlewareDescriptor::class));
	}

	public function testAddsDescriptorForMiddlewareCapability(): void
	{
		$this->pass->process([new MiddlewareCapability(StubMiddlewareA::class)], $this->model);

		$definitions = $this->model->definitions(MiddlewareDescriptor::class);
		$this->assertCount(1, $definitions);
		$this->assertSame(StubMiddlewareA::class, $definitions[0]->class);
	}

	public function testPreservesPriorityFromCapability(): void
	{
		$this->pass->process([new MiddlewareCapability(StubMiddlewareA::class, priority: 42)], $this->model);

		$definitions = $this->model->definitions(MiddlewareDescriptor::class);
		$this->assertSame(42, $definitions[0]->priority);
	}

	public function testMultipleCapabilitiesAllAdded(): void
	{
		$this->pass->process([
			new MiddlewareCapability(StubMiddlewareA::class),
			new MiddlewareCapability(StubMiddlewareB::class),
		], $this->model);

		$this->assertCount(2, $this->model->definitions(MiddlewareDescriptor::class));
	}

	public function testMixedCapabilitiesOnlyAddsMiddlewares(): void
	{
		$this->pass->process([
			new MiddlewareCapability(StubMiddlewareA::class),
			new ServiceCapability(\stdClass::class),
		], $this->model);

		$this->assertCount(1, $this->model->definitions(MiddlewareDescriptor::class));
	}
}
