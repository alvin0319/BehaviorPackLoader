<?php

declare(strict_types=1);

namespace alvin0319\BehaviorPackLoader\behavior\json;

final class BehaviorManifestHeader{
	/** @var string */
	public string $description;

	/**
	 * @var string
	 * @required
	 */
	public string $name;

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
