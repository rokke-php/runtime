<?php

// PHPStan stub for PHPUnit\Framework\TestCase.
// Only the minimal surface needed to satisfy static analysis.
// The real implementation is provided by the phpunit/phpunit package at test runtime.

namespace PHPUnit\Framework;

abstract class Assert
{
    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void {}

    public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void {}

    /** @param class-string $expected */
    public static function assertInstanceOf(string $expected, mixed $actual, string $message = ''): void {}

    public static function assertTrue(mixed $condition, string $message = ''): void {}

    public static function assertFalse(mixed $condition, string $message = ''): void {}

    public static function assertNull(mixed $actual, string $message = ''): void {}

    public static function assertCount(int $expectedCount, \Countable|iterable $haystack, string $message = ''): void {}

    /** @param iterable<mixed> $haystack */
    public static function assertContains(mixed $needle, iterable $haystack, string $message = ''): void {}
}

abstract class TestCase extends Assert
{
    protected function setUp(): void {}

    protected function tearDown(): void {}

    /** @param class-string<\Throwable> $exceptionClass */
    public function expectException(string $exceptionClass): void {}

    public function expectExceptionMessage(string $message): void {}

    /** @return never */
    public function markTestSkipped(string $message = ''): void {}

    /** @return never */
    public function fail(string $message = ''): void {}

    public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void {}

    public static function assertIsInt(mixed $actual, string $message = ''): void {}

    public static function assertIsBool(mixed $actual, string $message = ''): void {}

    public static function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void {}

    public static function assertGreaterThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void {}
}
