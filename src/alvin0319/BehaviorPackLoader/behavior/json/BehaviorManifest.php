<?php

declare(strict_types=1);

namespace alvin0319\BehaviorPackLoader\behavior\json;

use pocketmine\resourcepacks\json\ManifestMetadata;

final class BehaviorManifest{

	/**
	 * @var int
	 * @required
	 */
	public int $format_version;

	/**
	 * @var BehaviorManifestHeader
	 * @required
	 */
	public BehaviorManifestHeader $header;

	/** @var ManifestMetadata|null */
	public ?ManifestMetadata $metadata = null;

	public array $modules;

	/**
	 * @var BehaviorModuleDependency[]
	 */
	public array $dependencies;
}