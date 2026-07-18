<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build\CodeGen;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CodeGen\Node\ArrayNode;
use Rokke\Runtime\Build\CodeGen\Node\ClassReferenceNode;
use Rokke\Runtime\Build\CodeGen\Node\LiteralNode;
use Rokke\Runtime\Build\CodeGen\Node\NewObjectNode;
use Rokke\Runtime\Build\CodeGen\Node\StaticCallNode;
use Rokke\Runtime\Build\CodeGen\NodeInterface;

final class NodeTest extends TestCase
{
	public function testLiteralNodeImplementsInterface(): void
	{
		$this->assertInstanceOf(NodeInterface::class, new LiteralNode('hello'));
	}

	public function testLiteralNodeExposesValue(): void
	{
		$node = new LiteralNode(42);
		$this->assertSame(42, $node->value);
	}

	public function testArrayNodeExposesItems(): void
	{
		$item = new LiteralNode('x');
		$node = new ArrayNode(['key' => $item]);
		$this->assertSame(['key' => $item], $node->items);
	}

	public function testNewObjectNodeExposesClassAndArguments(): void
	{
		$arg  = new LiteralNode(1);
		$node = new NewObjectNode(\stdClass::class, ['id' => $arg]);
		$this->assertSame(\stdClass::class, $node->class);
		$this->assertSame(['id' => $arg], $node->arguments);
	}

	public function testClassReferenceNodeExposesClass(): void
	{
		$node = new ClassReferenceNode(\stdClass::class);
		$this->assertSame(\stdClass::class, $node->class);
	}

	public function testStaticCallNodeExposesClassMethodArguments(): void
	{
		$arg  = new LiteralNode(0);
		$node = new StaticCallNode(\stdClass::class, 'create', [$arg]);
		$this->assertSame(\stdClass::class, $node->class);
		$this->assertSame('create', $node->method);
		$this->assertSame([$arg], $node->arguments);
	}
}
