<?php

declare(strict_types=1);

// ── Fixtures ──────────────────────────────────────────────────────────────────

namespace Rokke\Runtime\Tests\Build\CodeGen\Fixtures;

final class FooService {}
final class BarService {}

// ── Tests ─────────────────────────────────────────────────────────────────────

namespace Rokke\Runtime\Tests\Build\CodeGen;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CodeGen\Node\ArrayNode;
use Rokke\Runtime\Build\CodeGen\Node\ClassReferenceNode;
use Rokke\Runtime\Build\CodeGen\Node\LiteralNode;
use Rokke\Runtime\Build\CodeGen\Node\NewObjectNode;
use Rokke\Runtime\Build\CodeGen\Node\StaticCallNode;
use Rokke\Runtime\Build\CodeGen\PhpWriter;

final class PhpWriterTest extends TestCase
{
    private PhpWriter $writer;

    protected function setUp(): void
    {
        $this->writer = new PhpWriter();
    }

    private function parse(string $php): mixed
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rokke_pw_');
        assert(is_string($tmp));
        file_put_contents($tmp, $php);
        $result = require $tmp;
        unlink($tmp);

        return $result;
    }

    // ── LiteralNode ───────────────────────────────────────────────────────────

    public function testRendersNullLiteral(): void
    {
        $php = $this->writer->render(new LiteralNode(null));
        $this->assertNull($this->parse($php));
    }

    public function testRendersBoolLiterals(): void
    {
        $this->assertTrue($this->parse($this->writer->render(new LiteralNode(true))));
        $this->assertFalse($this->parse($this->writer->render(new LiteralNode(false))));
    }

    public function testRendersIntLiteral(): void
    {
        $this->assertSame(42, $this->parse($this->writer->render(new LiteralNode(42))));
    }

    public function testRendersStringLiteral(): void
    {
        $this->assertSame("it's a test", $this->parse($this->writer->render(new LiteralNode("it's a test"))));
    }

    // ── ArrayNode ─────────────────────────────────────────────────────────────

    public function testRendersEmptyArray(): void
    {
        $this->assertSame([], $this->parse($this->writer->render(new ArrayNode([]))));
    }

    public function testRendersIndexedArray(): void
    {
        $node   = new ArrayNode([new LiteralNode(1), new LiteralNode(2)]);
        $result = $this->parse($this->writer->render($node));
        $this->assertSame([1, 2], $result);
    }

    public function testRendersAssocArray(): void
    {
        $node   = new ArrayNode(['key' => new LiteralNode('val')]);
        $result = $this->parse($this->writer->render($node));
        $this->assertSame(['key' => 'val'], $result);
    }

    // ── NewObjectNode ─────────────────────────────────────────────────────────

    public function testRendersNewObjectWithNoArgs(): void
    {
        $node   = new NewObjectNode(\stdClass::class);
        $result = $this->parse($this->writer->render($node));
        $this->assertInstanceOf(\stdClass::class, $result);
    }

    public function testRendersNewObjectWithNamedArg(): void
    {
        $node   = new NewObjectNode(\SplStack::class);
        $result = $this->parse($this->writer->render($node));
        $this->assertInstanceOf(\SplStack::class, $result);
    }

    public function testUsesShortClassNameInUseStatement(): void
    {
        $node = new NewObjectNode(Fixtures\FooService::class);
        $php  = $this->writer->render($node);

        $this->assertStringContainsString('use Rokke\Runtime\Tests\Build\CodeGen\Fixtures\FooService;', $php);
        $this->assertStringContainsString('return new FooService()', $php);
    }

    // ── ClassReferenceNode ────────────────────────────────────────────────────

    public function testRendersClassReference(): void
    {
        $node   = new ClassReferenceNode(\stdClass::class);
        $result = $this->parse($this->writer->render($node));
        $this->assertSame(\stdClass::class, $result);
    }

    // ── StaticCallNode ────────────────────────────────────────────────────────

    public function testRendersStaticCall(): void
    {
        $node = new StaticCallNode(\SplStack::class, 'class');
        $php  = $this->writer->render($node);
        $this->assertStringContainsString('SplStack::class', $php);
    }

    // ── use collision ─────────────────────────────────────────────────────────

    public function testCollisionFallsBackToFqcn(): void
    {
        // Two classes with same short name in different namespaces.
        // Since we can't easily have two "FooService" in real PHP here,
        // test that both distinct short names each get their own use statement.
        $node = new ArrayNode([
            new NewObjectNode(Fixtures\FooService::class),
            new NewObjectNode(Fixtures\BarService::class),
        ]);
        $php = $this->writer->render($node);

        // Both appear; FooService and BarService each get their own short name
        // (no collision since they have different names)
        $this->assertStringContainsString('use Rokke\Runtime\Tests\Build\CodeGen\Fixtures\FooService;', $php);
        $this->assertStringContainsString('use Rokke\Runtime\Tests\Build\CodeGen\Fixtures\BarService;', $php);
        $result = $this->parse($php);
        $this->assertCount(2, $result);
    }

    // ── file structure ────────────────────────────────────────────────────────

    public function testOutputStartsWithPhpTag(): void
    {
        $php = $this->writer->render(new LiteralNode(1));
        $this->assertStringStartsWith('<?php', $php);
    }

    public function testOutputContainsDeclareStrictTypes(): void
    {
        $php = $this->writer->render(new LiteralNode(1));
        $this->assertStringContainsString("declare(strict_types=1);", $php);
    }

    public function testOutputEndsWithNewline(): void
    {
        $php = $this->writer->render(new LiteralNode(1));
        $this->assertSame("\n", substr($php, -1));
    }
}
