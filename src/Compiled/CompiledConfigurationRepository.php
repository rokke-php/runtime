<?php

declare(strict_types=1);

namespace Rokke\Runtime\Compiled;

final class CompiledConfigurationRepository
{
    /** @var array<class-string, object> */
    private array $store;

    /** @var list<object> */
    private array $ordered;

    /**
     * @param array<class-string, object> $store
     * @param list<object> $ordered
     */
    private function __construct(array $store, array $ordered)
    {
        $this->store   = $store;
        $this->ordered = $ordered;
    }

    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * @param list<object> $configurations
     * @throws \RuntimeException if two objects of the same class appear
     */
    public static function build(array $configurations): self
    {
        $store   = [];
        $ordered = [];

        foreach ($configurations as $config) {
            $class = $config::class;

            if (isset($store[$class])) {
                throw new \RuntimeException(
                    "Duplicate compiled configuration: {$class} appears more than once. " .
                    "Each configuration type must be unique within a build.",
                );
            }

            $store[$class]  = $config;
            $ordered[]      = $config;
        }

        return new self($store, $ordered);
    }

    public function has(string $class): bool
    {
        return isset($this->store[$class]);
    }

    /** @throws \RuntimeException if $class is not registered */
    public function get(string $class): object
    {
        if (!isset($this->store[$class])) {
            throw new \RuntimeException(
                "Configuration '{$class}' is not registered in CompiledConfigurationRepository.",
            );
        }

        return $this->store[$class];
    }

    /** @return list<object> */
    public function all(): array
    {
        return $this->ordered;
    }
}
