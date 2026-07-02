<?php

declare(strict_types=1);

namespace Rokke\Runtime\Build;

use Rokke\Contracts\Module\CapabilityInterface;

final readonly class ServiceCapability implements CapabilityInterface
{
	/** @var class-string */
	public string $implementation;

	/**
	 * @param class-string      $contract
	 * @param class-string|null $implementation
	 */
	public function __construct(
		/** @var class-string */
		public string $contract,
		?string $implementation = null,
	) {
		$this->implementation = $implementation ?? $contract;
	}
}
