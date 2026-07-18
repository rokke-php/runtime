<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Compiled\CompiledOperation;
use Rokke\Runtime\Compiled\OperationRepository;

final class OperationRepositoryTest extends TestCase
{
	private function makeOp(string $id): CompiledOperation
	{
		return new CompiledOperation($id, 0, 0, 0, 0);
	}

	// ── empty() ───────────────────────────────────────────────────────────────

	public function testEmptyRepositoryHasNoOperations(): void
	{
		$repo = OperationRepository::empty();

		$this->assertFalse($repo->has('any'));
		$this->assertNull($repo->find('any'));
	}

	// ── find() ────────────────────────────────────────────────────────────────

	public function testFindReturnsNullForUnknownId(): void
	{
		$repo = OperationRepository::build([$this->makeOp('users.list')]);

		$this->assertNull($repo->find('users.show'));
	}

	public function testFindReturnsCompiledOperationForKnownId(): void
	{
		$op   = $this->makeOp('users.list');
		$repo = OperationRepository::build([$op]);

		$this->assertSame($op, $repo->find('users.list'));
	}

	public function testFindIsIndexedByOperationId(): void
	{
		$opA = $this->makeOp('a');
		$opB = $this->makeOp('b');
		$repo = OperationRepository::build([$opA, $opB]);

		$this->assertSame($opA, $repo->find('a'));
		$this->assertSame($opB, $repo->find('b'));
	}

	// ── has() ─────────────────────────────────────────────────────────────────

	public function testHasReturnsFalseForUnknownId(): void
	{
		$repo = OperationRepository::build([$this->makeOp('users.list')]);

		$this->assertFalse($repo->has('unknown'));
	}

	public function testHasReturnsTrueForKnownId(): void
	{
		$repo = OperationRepository::build([$this->makeOp('greet')]);

		$this->assertTrue($repo->has('greet'));
	}

	// ── build() ───────────────────────────────────────────────────────────────

	public function testBuildRegistersAllOperations(): void
	{
		$ops  = [$this->makeOp('a'), $this->makeOp('b'), $this->makeOp('c')];
		$repo = OperationRepository::build($ops);

		$this->assertTrue($repo->has('a'));
		$this->assertTrue($repo->has('b'));
		$this->assertTrue($repo->has('c'));
	}

	public function testBuildThrowsOnDuplicateId(): void
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("'greet'");

		OperationRepository::build([$this->makeOp('greet'), $this->makeOp('greet')]);
	}

	public function testBuildFromEmptyListProducesEmptyRepository(): void
	{
		$repo = OperationRepository::build([]);

		$this->assertFalse($repo->has('anything'));
	}

	// ── CompiledOperation.id ──────────────────────────────────────────────────

	public function testCompiledOperationExposesId(): void
	{
		$op = new CompiledOperation('my.op', 1, 2, 3, 4);

		$this->assertSame('my.op', $op->id);
	}

	public function testCompiledOperationPreservesAllFields(): void
	{
		$op = new CompiledOperation('op', 10, 20, 30, 40);

		$this->assertSame('op', $op->id);
		$this->assertSame(10, $op->pipelineId);
		$this->assertSame(20, $op->factoryId);
		$this->assertSame(30, $op->argumentPlanId);
		$this->assertSame(40, $op->resultPlanId);
	}
}
