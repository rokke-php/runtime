<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Context;

final class ContextTest extends TestCase
{
	private Context $context;

	protected function setUp(): void
	{
		$this->context = new Context('test-id-001');
	}

	public function testReturnsCorrectId(): void
	{
		$this->assertSame('test-id-001', $this->context->id());
	}

	public function testSetAndGetValue(): void
	{
		$this->context->set('user_id', 42);

		$this->assertSame(42, $this->context->get('user_id'));
	}

	public function testGetReturnsDefaultWhenKeyMissing(): void
	{
		$this->assertNull($this->context->get('missing'));
		$this->assertSame('fallback', $this->context->get('missing', 'fallback'));
	}

	public function testHasReturnsTrueForExistingKey(): void
	{
		$this->context->set('trace_id', 'abc-123');

		$this->assertTrue($this->context->has('trace_id'));
	}

	public function testHasReturnsFalseForMissingKey(): void
	{
		$this->assertFalse($this->context->has('nonexistent'));
	}

	public function testOverwriteExistingKey(): void
	{
		$this->context->set('role', 'user');
		$this->context->set('role', 'admin');

		$this->assertSame('admin', $this->context->get('role'));
	}

	public function testStoresAnyType(): void
	{
		$object = new \stdClass();
		$object->name = 'test';

		$this->context->set('payload', $object);

		$this->assertSame($object, $this->context->get('payload'));
	}

	public function testDestroyClearsAllValues(): void
	{
		$this->context->set('key_a', 'value_a');
		$this->context->set('key_b', 'value_b');

		$this->context->destroy();

		$this->assertFalse($this->context->has('key_a'));
		$this->assertFalse($this->context->has('key_b'));
	}

	public function testGetReturnsDefaultAfterDestroy(): void
	{
		$this->context->set('token', 'abc');
		$this->context->destroy();

		$this->assertNull($this->context->get('token'));
	}

	public function testSetAfterDestroyWorks(): void
	{
		$this->context->set('a', 1);
		$this->context->destroy();
		$this->context->set('b', 2);

		$this->assertFalse($this->context->has('a'));
		$this->assertTrue($this->context->has('b'));
		$this->assertSame(2, $this->context->get('b'));
	}

	public function testOnDestroyCallbackIsInvokedOnDestroy(): void
	{
		$called = false;

		$this->context->onDestroy(function () use (&$called): void {
			$called = true;
		});

		$this->context->destroy();

		$this->assertTrue($called);
	}

	public function testAllOnDestroyCallbacksInvokedEvenIfOneFails(): void
	{
		$secondCalled = false;

		$this->context->onDestroy(function (): void {
			throw new \RuntimeException('cleanup error');
		});

		$this->context->onDestroy(function () use (&$secondCalled): void {
			$secondCalled = true;
		});

		$this->context->destroy();

		$this->assertTrue($secondCalled);
	}

	public function testOnDestroyCallbacksNotRepeatedOnSecondDestroy(): void
	{
		$callCount = 0;

		$this->context->onDestroy(function () use (&$callCount): void {
			$callCount++;
		});

		$this->context->destroy();
		$this->context->destroy();

		$this->assertSame(1, $callCount);
	}
}
