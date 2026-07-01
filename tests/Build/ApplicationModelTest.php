<?php

declare(strict_types=1);

namespace Rokke\Runtime\Tests\Build;

use PHPUnit\Framework\TestCase;
use Rokke\Contracts\Build\DefinitionInterface;
use Rokke\Runtime\Build\ApplicationModel;

// ── Fixtures ──────────────────────────────────────────────────────────────────

final class AlphaDefinition implements DefinitionInterface
{
	public function __construct(public readonly string $value) {}
}

final class BetaDefinition implements DefinitionInterface
{
	public function __construct(public readonly string $value) {}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class ApplicationModelTest extends TestCase
{
	public function testEmptyModelReturnsEmptyListForAnyType(): void
	{
		$model = new ApplicationModel();

		$this->assertSame([], $model->definitions(AlphaDefinition::class));
	}

	public function testAddedDefinitionCanBeRetrievedByType(): void
	{
		$model = new ApplicationModel();
		$def   = new AlphaDefinition('foo');

		$model->add($def);

		$this->assertSame([$def], $model->definitions(AlphaDefinition::class));
	}

	public function testDefinitionsOfDifferentTypesAreIsolated(): void
	{
		$model = new ApplicationModel();
		$alpha = new AlphaDefinition('a');
		$beta  = new BetaDefinition('b');

		$model->add($alpha);
		$model->add($beta);

		$this->assertSame([$alpha], $model->definitions(AlphaDefinition::class));
		$this->assertSame([$beta], $model->definitions(BetaDefinition::class));
	}

	public function testMultipleDefinitionsOfSameTypePreserveInsertionOrder(): void
	{
		$model = new ApplicationModel();
		$first = new AlphaDefinition('first');
		$second = new AlphaDefinition('second');

		$model->add($first);
		$model->add($second);

		$this->assertSame([$first, $second], $model->definitions(AlphaDefinition::class));
	}

	public function testUnknownTypeReturnsEmptyList(): void
	{
		$model = new ApplicationModel();
		$model->add(new AlphaDefinition('x'));

		$this->assertSame([], $model->definitions(BetaDefinition::class));
	}
}
