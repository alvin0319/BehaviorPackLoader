<?php

declare(strict_types=1);

namespace alvin0319\BehaviorPackLoader\behavior;

use alvin0319\BehaviorPackLoader\Loader;
use InvalidArgumentException;
use Logger;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\resourcepacks\ResourcePackException;
use pocketmine\utils\Config;
use SplFileInfo;
use function array_keys;
use function count;
use function file_exists;
use function gettype;
use function is_array;
use function is_dir;
use function is_string;
use function mkdir;
use function strtolower;

final class BehaviorPackManager{
	/** @var Loader */
	private Loader $loader;
	/** @var string */
	private string $path;

	/** @var bool */
	private bool $serverForceResources = false;

	/** @var BehaviorPack[] */
	private array $behaviorPacks = [];

	/** @var BehaviorPack[] */
	private array $uuidList = [];

	public function __construct(Loader $loader, string $path, Logger $logger){
		$this->loader = $loader;
		$this->path = $path;

		if(!file_exists($this->path)){
			$logger->debug("Behavior packs path $path does not exist, creating directory");
			mkdir($this->path);
		}elseif(!is_dir($this->path)){
			throw new InvalidArgumentException("Resource packs path $path exists and is not a directory");
		}

		if(!file_exists($this->path . "behavior_packs.yml")){
			//copy(\pocketmine\RESOURCE_PATH . "resource_packs.yml", $this->path . "resource_packs.yml");
			$this->loader->saveResource("behavior_packs.yml");
		}

		$resourcePacksConfig = new Config($this->path . "behavior_packs.yml", Config::YAML, []);

		$this->serverForceResources = (bool) $resourcePacksConfig->get("force_resources", false);

		$logger->info("Loading behavior packs...");

		$resourceStack = $resourcePacksConfig->get("resource_stack", []);
		if(!is_array($resourceStack)){
			throw new InvalidArgumentException("\"resource_stack\" key should contain a list of pack names");
		}

		foreach($resourceStack as $pos => $pack){
			if(!is_string($pack)){
				$logger->critical("Found invalid entry in resource pack list at offset $pos of type " . gettype($pack));
				continue;
			}
			try{
				$packPath = $this->path . DIRECTORY_SEPARATOR . $pack;
				if(!file_exists($packPath)){
					throw new ResourcePackException("File or directory not found");
				}
				if(is_dir($packPath)){
					throw new ResourcePackException("Directory resource packs are unsupported");
				}

				$newPack = null;
				//Detect the type of resource pack.
				$info = new SplFileInfo($packPath);
				switch($info->getExtension()){
					case "zip":
					case "mcpack":
						$newPack = new BehaviorPack($packPath);
						break;
				}

				if($newPack instanceof BehaviorPack){
					$this->behaviorPacks[] = $newPack;
					$this->uuidList[strtolower($newPack->getPackId())] = $newPack;
				}else{
					throw new ResourcePackException("Format not recognized");
				}
			}catch(ResourcePackException $e){
				$logger->critical("Could not load resource pack \"$pack\": " . $e->getMessage());
			}
		}

		$logger->debug("Successfully loaded " . count($this->behaviorPacks) . " behavior packs");
	}

	/**
	 * Returns the directory which resource packs are loaded from.
	 */
	public function getPath() : string{
		return $this->path;
	}

	/**
	 * Returns whether players must accept resource packs in order to join.
	 */
	public function resourcePacksRequired() : bool{
		return $this->serverForceResources;
	}

	/**
	 * Returns an array of resource packs in use, sorted in order of priority.
	 * @return ResourcePack[]
	 */
	public function getBehaviorPacks() : array{
		return $this->behaviorPacks;
	}

	/**
	 * Returns the resource pack matching the specified UUID string, or null if the ID was not recognized.
	 */
	public function getPackById(string $id) : ?ResourcePack{
		return $this->uuidList[strtolower($id)] ?? null;
	}

	/**
	 * Returns an array of pack IDs for packs currently in use.
	 * @return string[]
	 */
	public function getPackIdList() : array{
		return array_keys($this->uuidList);
	}
}