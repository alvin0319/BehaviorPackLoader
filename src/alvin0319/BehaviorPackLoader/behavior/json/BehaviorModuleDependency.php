<?php

declare(strict_types=1);

namespace alvin0319\BehaviorPackLoader\behavior\json;

final class BehaviorModuleDependency{
	/**
	 * @var string
	 * @required
	 */
	public string $uuid;

	/**
	 * @var int[]
	 * @phpstan-var array{int, int, int}
	 * @required
	 */
	public array $version;
}
