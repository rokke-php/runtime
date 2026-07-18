<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use ReflectionNamedType;
use ReflectionParameter;
use Rokke\Runtime\Compiled\Arguments\ArgumentInstructionInterface;
use Rokke\Runtime\Compiled\Arguments\FactoryArgumentInstruction;

final class ServiceArgumentSourceCompiler implements ArgumentSourceCompilerInterface
{
    public function compile(ReflectionParameter $param, FactoryRepository $factories): ?ArgumentInstructionInterface
    {
        $type = $param->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        /** @var class-string $typeName */
        $typeName = $type->getName();
        $id       = $factories->id($typeName);

        return $id !== null ? new FactoryArgumentInstruction($id, $factories) : null;
    }
}
