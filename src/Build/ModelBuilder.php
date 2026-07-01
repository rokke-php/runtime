<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

final class ModelBuilder implements ModelBuilderInterface
{
	/** @param list<ModelBuilderPassInterface> $passes */
	public function __construct(private readonly array $passes = []) {}

	/** @param list<CapabilityInterface> $capabilities */
	public function build(array $capabilities): ApplicationModel
	{
		$model = new ApplicationModel();

		foreach ($this->passes as $pass) {
			$pass->process($capabilities, $model);
		}

		return $model;
	}
}
