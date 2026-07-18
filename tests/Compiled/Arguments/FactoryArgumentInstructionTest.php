<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Compiled\Arguments;

use PHPUnit\Framework\TestCase;
use Rokke\Runtime\Build\CompiledFactory;
use Rokke\Runtime\Build\FactoryRepository;
use Rokke\Runtime\Compiled\Arguments\FactoryArgumentInstruction;
use Rokke\Runtime\Contracts\OperationContextInterface;

final class InstrFixtureA {}
final class InstrFixtureB {}

final class FactoryArgumentInstructionTest extends TestCase
{
    private function makeCtx(): OperationContextInterface
    {
        return $this->createStub(OperationContextInterface::class);
    }

    public function testResolveDelegatesToFactoryRepository(): void
    {
        $repo  = FactoryRepository::fromDescriptors([new CompiledFactory(InstrFixtureA::class)]);
        $instr = new FactoryArgumentInstruction(0, $repo);

        $this->assertInstanceOf(InstrFixtureA::class, $instr->resolve($this->makeCtx()));
    }

    public function testResolveIgnoresContext(): void
    {
        $repo  = FactoryRepository::fromDescriptors([new CompiledFactory(InstrFixtureA::class)]);
        $instr = new FactoryArgumentInstruction(0, $repo);

        $ctx1 = $this->makeCtx();
        $ctx2 = $this->makeCtx();

        $this->assertInstanceOf(InstrFixtureA::class, $instr->resolve($ctx1));
        $this->assertInstanceOf(InstrFixtureA::class, $instr->resolve($ctx2));
    }

    public function testResolveCallsCreateEachTime(): void
    {
        $repo  = FactoryRepository::fromDescriptors([new CompiledFactory(InstrFixtureA::class)]);
        $instr = new FactoryArgumentInstruction(0, $repo);
        $ctx   = $this->makeCtx();

        $a = $instr->resolve($ctx);
        $b = $instr->resolve($ctx);

        $this->assertNotSame($a, $b);
    }
}
