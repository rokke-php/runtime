<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Context;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Context\OperationContext;

final class OperationContextTest extends TestCase
{
	public function testIdReturnsConstructedId(): void
	{
		$ctx = new OperationContext('req-123');

		$this->assertSame('req-123', $ctx->id());
	}

	public function testIsNotCancelledByDefault(): void
	{
		$ctx = new OperationContext('req-1');

		$this->assertFalse($ctx->isCancelled());
	}

	public function testCancelSetsCancelledState(): void
	{
		$ctx = new OperationContext('req-1');
		$ctx->cancel();

		$this->assertTrue($ctx->isCancelled());
	}

	public function testThrowIfCancelledDoesNothingWhenNotCancelled(): void
	{
		$ctx = new OperationContext('req-1');
		$ctx->throwIfCancelled();

		$this->assertFalse($ctx->isCancelled());
	}

	public function testThrowIfCancelledThrowsAfterCancel(): void
	{
		$ctx = new OperationContext('req-1');
		$ctx->cancel();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('req-1');

		$ctx->throwIfCancelled();
	}

	public function testMetadataReturnsValueByKey(): void
	{
		$ctx = new OperationContext('req-1', ['x-trace' => 'abc-xyz']);

		$this->assertSame('abc-xyz', $ctx->metadata('x-trace'));
	}

	public function testMetadataReturnsNullForMissingKey(): void
	{
		$ctx = new OperationContext('req-1');

		$this->assertNull($ctx->metadata('missing'));
	}

	public function testMetadataReturnsExplicitDefaultForMissingKey(): void
	{
		$ctx = new OperationContext('req-1');

		$this->assertSame('fallback', $ctx->metadata('missing', 'fallback'));
	}

	public function testCancelIsIdempotent(): void
	{
		$ctx = new OperationContext('req-1');
		$ctx->cancel();
		$ctx->cancel();

		$this->assertTrue($ctx->isCancelled());
	}
}
