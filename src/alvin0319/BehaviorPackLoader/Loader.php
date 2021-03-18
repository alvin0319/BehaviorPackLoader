<?php

declare(strict_types=1);

namespace alvin0319\BehaviorPackLoader;

use alvin0319\BehaviorPackLoader\behavior\BehaviorPack;
use alvin0319\BehaviorPackLoader\behavior\BehaviorPackManager;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\resourcepacks\BehaviorPackInfoEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackStackEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackType;
use pocketmine\plugin\PluginBase;
use function array_map;
use function ceil;
use function strpos;
use function substr;

final class Loader extends PluginBase{

	protected BehaviorPackManager $behaviorPackManager;

	protected array $downloadedChunks = [];

	protected function onEnable() : void{
		$this->behaviorPackManager = new BehaviorPackManager($this, $this->getDataFolder(), $this->getServer()->getLogger());

		$this->getServer()->getPluginManager()->registerEvent(DataPacketSendEvent::class, function(DataPacketSendEvent $event) : void{
			$packets = $event->getPackets();
			foreach($packets as $packet){
				if($packet instanceof ResourcePacksInfoPacket){
					$packet->behaviorPackEntries = array_map(function(BehaviorPack $pack) : BehaviorPackInfoEntry{
						return new BehaviorPackInfoEntry($pack->getPackId(), $pack->getPackVersion(), $pack->getPackSize(), "", "", "", false);
					}, $this->behaviorPackManager->getBehaviorPacks());
					$packet->mustAccept = $packet->mustAccept && $this->behaviorPackManager->resourcePacksRequired();
				}elseif($packet instanceof ResourcePackStackPacket){
					$stack = array_map(static function(BehaviorPack $pack) : ResourcePackStackEntry{
						return new ResourcePackStackEntry($pack->getPackId(), $pack->getPackVersion(), ""); //TODO: subpacks
					}, $this->behaviorPackManager->getBehaviorPacks());
					$packet->behaviorPackStack = $stack;
					foreach($event->getTargets() as $session){
						$session->getLogger()->debug("Applying behavior pack stack");
					}
				}elseif($packet instanceof StartGamePacket){
					//$packet->hasLockedBehaviorPack = true;
				}
			}
		}, EventPriority::MONITOR, $this, true);

		$this->getServer()->getPluginManager()->registerEvent(DataPacketReceiveEvent::class, function(DataPacketReceiveEvent $event) : void{
			$packet = $event->getPacket();
			$session = $event->getOrigin();
			//var_dump($packet->pid());
			if($packet instanceof ResourcePackClientResponsePacket){
				if($packet->status === ResourcePackClientResponsePacket::STATUS_SEND_PACKS){
					foreach($packet->packIds as $uuid){
						$splitPos = strpos($uuid, "_");
						if($splitPos !== false){
							$uuid = substr($uuid, 0, $splitPos);
						}
						$pack = $this->behaviorPackManager->getPackById($uuid);
						if(!($pack instanceof BehaviorPack)){
							return;
						}
						$pk = ResourcePackDataInfoPacket::create(
							$pack->getPackId(),
							128 * 1024,
							(int) ceil($pack->getPackSize() / 128 * 1024),
							$pack->getPackSize(),
							$pack->getSha256()
						);
						$pk->packType = ResourcePackType::BEHAVIORS;
						$session->sendDataPacket($pk);
						$event->cancel();
					}
				}
			}elseif($packet instanceof ResourcePackChunkRequestPacket){
				$pack = $this->behaviorPackManager->getPackById($packet->packId);
				if(!$pack instanceof BehaviorPack){
					return;
				}
				$packId = $pack->getPackId();

				if(isset($this->downloadedChunks[$session->getPlayerInfo()->getUsername()][$packId][$packet->chunkIndex])){
					$session->disconnect("Duplicate request for chunk $packet->chunkIndex of pack $packet->packId");
					return;
				}

				$offset = $packet->chunkIndex * 128 * 1024;
				if($offset < 0 or $offset >= $pack->getPackSize()){
					$session->disconnect("Invalid out-of-bounds request for chunk $packet->chunkIndex of $packet->packId: offset $offset, file size " . $pack->getPackSize());
					return;
				}

				if(!isset($this->downloadedChunks[$session->getPlayerInfo()->getUsername()][$packId])){
					$this->downloadedChunks[$session->getPlayerInfo()->getUsername()][$packId] = [$packet->chunkIndex => true];
				}else{
					$this->downloadedChunks[$session->getPlayerInfo()->getUsername()][$packId][$packet->chunkIndex] = true;
				}

				$session->sendDataPacket(
					ResourcePackChunkDataPacket::create(
						$packId,
						$packet->chunkIndex,
						$offset,
						$pack->getPackChunk($offset, 128 * 1024)
					)
				);
				$event->cancel();
			}
		}, EventPriority::HIGHEST, $this, true);

		$this->getServer()->getPluginManager()->registerEvent(PlayerLoginEvent::class, function(PlayerLoginEvent $event) : void{
			$player = $event->getPlayer();
			if(isset($this->downloadedChunks[$player->getName()])){
				unset($this->downloadedChunks[$player->getName()]);
			}
		}, EventPriority::MONITOR, $this, true);
	}
}