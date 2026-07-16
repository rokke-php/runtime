<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Module\CapabilityInterface;
use Rokke\Runtime\Build\ApplicationModel;
use Rokke\Runtime\Build\OperationCapability;
use Rokke\Runtime\Build\OperationDefinition;
use Rokke\Runtime\Build\OperationModelBuilderPass;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class PassTestStringHandler
{
	public function __invoke(): string
	{
		return 'result';
	}
}

final class PassTestVoidHandler
{
	public function __invoke(): void {}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class OperationModelBuilderPassTest extends TestCase
{
	private OperationModelBuilderPass $pass;
	private ApplicationModel $model;

	protected function setUp(): void
	{
		$this->pass  = new OperationModelBuilderPass();
		$this->model = new ApplicationModel();
	}

	public function testIgnoresCapabilitiesThatAreNotOperationCapability(): void
	{
		/** @var CapabilityInterface $other */
		$other = $this->createStub(CapabilityInterface::class);

		$this->pass->process([$other], $this->model);

		$this->assertSame([], $this->model->definitions(OperationDefinition::class));
	}

	public function testAddsOneDefinitionPerOperationCapability(): void
	{
		$cap = new OperationCapability('users.show', 'Show User', PassTestStringHandler::class);

		$this->pass->process([$cap], $this->model);

		$defs = $this->model->definitions(OperationDefinition::class);
		$this->assertCount(1, $defs);
	}

	public function testDefinitionPreservesId(): void
	{
		$cap = new OperationCapability('orders.create', 'Create Order', PassTestVoidHandler::class);

		$this->pass->process([$cap], $this->model);

		$this->assertSame('orders.create', $this->model->definitions(OperationDefinition::class)[0]->id);
	}

	public function testDefinitionPreservesName(): void
	{
		$cap = new OperationCapability('orders.create', 'Create Order', PassTestVoidHandler::class);

		$this->pass->process([$cap], $this->model);

		$this->assertSame('Create Order', $this->model->definitions(OperationDefinition::class)[0]->name);
	}

	public function testDefinitionPreservesHandler(): void
	{
		$cap = new OperationCapability('op', 'Op', PassTestStringHandler::class);

		$this->pass->process([$cap], $this->model);

		$def = $this->model->definitions(OperationDefinition::class)[0];
		$this->assertSame(PassTestStringHandler::class, $def->handler);
	}

	public function testProcessesMixedCapabilitiesSkippingNonOperation(): void
	{
		/** @var CapabilityInterface $other */
		$other = $this->createStub(CapabilityInterface::class);
		$op    = new OperationCapability('x', 'X', PassTestVoidHandler::class);

		$this->pass->process([$other, $op, $other], $this->model);

		$this->assertCount(1, $this->model->definitions(OperationDefinition::class));
	}

	public function testMultipleOperationsAreAllAdded(): void
	{
		$a = new OperationCapability('op.a', 'A', PassTestVoidHandler::class);
		$b = new OperationCapability('op.b', 'B', PassTestVoidHandler::class);

		$this->pass->process([$a, $b], $this->model);

		$defs = $this->model->definitions(OperationDefinition::class);
		$this->assertCount(2, $defs);
		$this->assertSame('op.a', $defs[0]->id);
		$this->assertSame('op.b', $defs[1]->id);
	}
}
