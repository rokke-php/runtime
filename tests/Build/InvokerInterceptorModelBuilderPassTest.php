<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\InvokerInterceptorCapability;
use Rokke\Runtime\Build\InvokerInterceptorDescriptor;
use Rokke\Runtime\Build\InvokerInterceptorModelBuilderPass;
use Rokke\Runtime\Build\ServiceCapability;
use Rokke\Runtime\Contracts\InvokerInterceptorInterface;
use Rokke\Runtime\Contracts\OperationContextInterface;
use Rokke\Runtime\Contracts\OperationInterface;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class StubInterceptorA implements InvokerInterceptorInterface
{
	public function intercept(OperationInterface $op, OperationContextInterface $ctx, array $args, callable $next): mixed
	{
		return $next($args);
	}
}

final class StubInterceptorB implements InvokerInterceptorInterface
{
	public function intercept(OperationInterface $op, OperationContextInterface $ctx, array $args, callable $next): mixed
	{
		return $next($args);
	}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class InvokerInterceptorModelBuilderPassTest extends TestCase
{
	private InvokerInterceptorModelBuilderPass $pass;
	private ApplicationModel $model;

	protected function setUp(): void
	{
		$this->pass  = new InvokerInterceptorModelBuilderPass();
		$this->model = new ApplicationModel();
	}

	public function testIgnoresNonInterceptorCapabilities(): void
	{
		$this->pass->process([new ServiceCapability(\stdClass::class)], $this->model);

		$this->assertSame([], $this->model->definitions(InvokerInterceptorDescriptor::class));
	}

	public function testAddsDescriptorForInterceptorCapability(): void
	{
		$this->pass->process([new InvokerInterceptorCapability(StubInterceptorA::class)], $this->model);

		$definitions = $this->model->definitions(InvokerInterceptorDescriptor::class);
		$this->assertCount(1, $definitions);
		$this->assertSame(StubInterceptorA::class, $definitions[0]->class);
	}

	public function testPreservesPriorityFromCapability(): void
	{
		$this->pass->process([new InvokerInterceptorCapability(StubInterceptorA::class, priority: 99)], $this->model);

		$definitions = $this->model->definitions(InvokerInterceptorDescriptor::class);
		$this->assertSame(99, $definitions[0]->priority);
	}

	public function testMultipleCapabilitiesAllAdded(): void
	{
		$this->pass->process([
			new InvokerInterceptorCapability(StubInterceptorA::class),
			new InvokerInterceptorCapability(StubInterceptorB::class),
		], $this->model);

		$this->assertCount(2, $this->model->definitions(InvokerInterceptorDescriptor::class));
	}

	public function testMixedCapabilitiesOnlyAddsInterceptors(): void
	{
		$this->pass->process([
			new InvokerInterceptorCapability(StubInterceptorA::class),
			new ServiceCapability(\stdClass::class),
		], $this->model);

		$this->assertCount(1, $this->model->definitions(InvokerInterceptorDescriptor::class));
	}
}
