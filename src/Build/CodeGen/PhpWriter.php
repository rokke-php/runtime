<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build\CodeGen;

use Rokke\Runtime\Build\CodeGen\Node\ArrayNode;
use Rokke\Runtime\Build\CodeGen\Node\ClassReferenceNode;
use Rokke\Runtime\Build\CodeGen\Node\LiteralNode;
use Rokke\Runtime\Build\CodeGen\Node\NewObjectNode;
use Rokke\Runtime\Build\CodeGen\Node\StaticCallNode;

/**
 * Renders a CodeGen node tree to a complete, self-contained PHP file string.
 *
 * Use-statement management:
 *   - The first class encountered with a given short name is imported via `use`.
 *   - A subsequent class that shares the same short name is emitted as a FQCN inline.
 */
final class PhpWriter
{
    /** @var array<string, string> short name → FQCN */
    private array $uses = [];

    public function render(NodeInterface $root): string
    {
        $this->uses = [];

        $this->collect($root);

        $body  = $this->renderNode($root);
        $lines = ['<?php', '', 'declare(strict_types=1);'];

        if ($this->uses !== []) {
            $sorted = $this->uses;
            asort($sorted);
            $lines[] = '';
            foreach ($sorted as $fqcn) {
                $lines[] = "use {$fqcn};";
            }
        }

        $lines[] = '';
        $lines[] = "return {$body};";
        $lines[] = '';

        return implode("\n", $lines);
    }

    // ── Collection pass (builds $this->uses) ──────────────────────────────────

    private function collect(NodeInterface $node): void
    {
        if ($node instanceof NewObjectNode) {
            $this->registerClass($node->class);
            foreach ($node->arguments as $arg) {
                $this->collect($arg);
            }
        } elseif ($node instanceof ArrayNode) {
            foreach ($node->items as $item) {
                $this->collect($item);
            }
        } elseif ($node instanceof ClassReferenceNode) {
            $this->registerClass($node->class);
        } elseif ($node instanceof StaticCallNode) {
            $this->registerClass($node->class);
            foreach ($node->arguments as $arg) {
                $this->collect($arg);
            }
        }
    }

    private function registerClass(string $fqcn): void
    {
        // Leading backslash signals a global/absolute reference — never import.
        if (str_starts_with($fqcn, '\\')) {
            return;
        }

        // Root-level classes (no namespace separator) never need a use statement.
        if (!str_contains($fqcn, '\\')) {
            return;
        }

        $short = $this->extractShortName($fqcn);

        // First class with this short name wins the import slot.
        if (!isset($this->uses[$short])) {
            $this->uses[$short] = $fqcn;
        }
    }

    // ── Render pass ───────────────────────────────────────────────────────────

    private function renderNode(NodeInterface $node): string
    {
        return match (true) {
            $node instanceof LiteralNode        => $this->renderLiteral($node),
            $node instanceof ArrayNode          => $this->renderArray($node),
            $node instanceof NewObjectNode      => $this->renderNewObject($node),
            $node instanceof ClassReferenceNode => $this->renderClassRef($node),
            $node instanceof StaticCallNode     => $this->renderStaticCall($node),
            default => throw new \InvalidArgumentException('Unknown node type: ' . $node::class),
        };
    }

    private function renderLiteral(LiteralNode $node): string
    {
        return match (true) {
            $node->value === null  => 'null',
            is_bool($node->value)  => $node->value ? 'true' : 'false',
            is_int($node->value)   => (string) $node->value,
            is_float($node->value) => var_export($node->value, true),
            default                => "'" . addcslashes((string) $node->value, "'\\") . "'",
        };
    }

    private function renderArray(ArrayNode $node): string
    {
        if ($node->items === []) {
            return '[]';
        }

        $keys   = array_keys($node->items);
        $isList = $keys === range(0, count($keys) - 1);
        $pad    = '    ';
        $parts  = [];

        foreach ($node->items as $key => $value) {
            $rendered = $this->renderNode($value);
            $parts[]  = $isList ? $rendered : var_export($key, true) . ' => ' . $rendered;
        }

        return "[\n{$pad}" . implode(",\n{$pad}", $parts) . ",\n]";
    }

    private function renderNewObject(NewObjectNode $node): string
    {
        $name = $this->resolvedName($node->class);

        if ($node->arguments === []) {
            return "new {$name}()";
        }

        $parts = [];
        foreach ($node->arguments as $argName => $value) {
            $parts[] = "{$argName}: " . $this->renderNode($value);
        }

        if (count($parts) === 1) {
            return "new {$name}(" . $parts[0] . ")";
        }

        $pad = '    ';

        return "new {$name}(\n{$pad}" . implode(",\n{$pad}", $parts) . ",\n)";
    }

    private function renderClassRef(ClassReferenceNode $node): string
    {
        return $this->resolvedName($node->class) . '::class';
    }

    private function renderStaticCall(StaticCallNode $node): string
    {
        $name = $this->resolvedName($node->class);
        $args = array_map($this->renderNode(...), $node->arguments);

        return "{$name}::{$node->method}(" . implode(', ', $args) . ")";
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the name to use in rendered code: short name if imported,
     * FQCN (with leading backslash) on collision.
     */
    private function resolvedName(string $fqcn): string
    {
        if (str_starts_with($fqcn, '\\')) {
            return $fqcn;
        }

        $short = $this->extractShortName($fqcn);

        if (isset($this->uses[$short]) && $this->uses[$short] !== $fqcn) {
            return '\\' . $fqcn;
        }

        return $short;
    }

    private function extractShortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos !== false ? substr($fqcn, $pos + 1) : $fqcn;
    }
}
