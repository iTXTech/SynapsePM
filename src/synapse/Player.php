<?php

/*
 *
 *  _____   _____   __   _   _   _____  __    __  _____
 * /  ___| | ____| |  \ | | | | /  ___/ \ \  / / /  ___/
 * | |     | |__   |   \| | | | | |___   \ \/ /  | |___
 * | |  _  |  __|  | |\   | | | \___  \   \  /   \___  \
 * | |_| | | |___  | | \  | | |  ___| |   / /     ___| |
 * \_____/ |_____| |_|  \_| |_| /_____/  /_/     /_____/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author iTX Technologies
 * @link https://itxtech.org
 *
 */

namespace synapse;

use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\protocol\ChangeDimensionPacket;
use pocketmine\network\protocol\ContainerSetContentPacket;
use pocketmine\network\protocol\DataPacket;
use pocketmine\network\protocol\FullChunkDataPacket;
use pocketmine\network\protocol\PlayerListPacket;
use pocketmine\network\protocol\PlayStatusPacket;
use pocketmine\network\protocol\ResourcePacksInfoPacket;
use pocketmine\network\protocol\RespawnPacket;
use pocketmine\network\protocol\SetDifficultyPacket;
use pocketmine\network\protocol\SetEntityDataPacket;
use pocketmine\network\protocol\SetHealthPacket;
use pocketmine\network\protocol\SetPlayerGameTypePacket;
use pocketmine\network\protocol\SetSpawnPositionPacket;
use pocketmine\network\protocol\SetTimePacket;
use pocketmine\network\protocol\StartGamePacket;
use pocketmine\Player as PMPlayer;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;
use pocketmine\utils\UUID;
use synapse\event\player\PlayerConnectEvent;
use synapse\network\protocol\spp\PlayerLoginPacket;
use synapse\network\protocol\spp\TransferPacket;
use synapse\network\protocol\spp\FastPlayerListPacket;
use synapse\network\SynLibInterface;
use synapse\task\TransferTask;
use synapsepm\SynapsePM;


class Player extends PMPlayer{
	/** @var Synapse */
	private $synapse;
	private $isFirstTimeLogin = false;
	private $lastPacketTime;
	
	public function __construct(SynLibInterface $interface, $clientID, $ip, $port) {
		parent::__construct($interface, $clientID, $ip, $port);
		$this->synapse = $interface->getSynapse();
	}
	
	public function handleLoginPacket(PlayerLoginPacket $packet){
		$this->isFirstTimeLogin = $packet->isFirstTime;
		$this->server->getPluginManager()->callEvent($ev = new PlayerConnectEvent($this, $this->isFirstTimeLogin));
		$pk = $this->synapse->getPacket($packet->cachedLoginPacket);
		$pk->decode();
		$this->handleDataPacket($pk);
	}

	protected function processLogin(){
		if($this->isFirstTimeLogin){
			parent::processLogin();
		}else{
			if(!$this->server->isWhitelisted(strtolower($this->getName()))){
				$this->close($this->getLeaveMessage(), "Server is white-listed");

				return;
			}elseif($this->server->getNameBans()->isBanned(strtolower($this->getName())) or $this->server->getIPBans()->isBanned($this->getAddress()) or method_exists($this->server, "getCIDBans") && $this->server->getCIDBans()->isBanned($this->randomClientId)){
				$this->close($this->getLeaveMessage(), TextFormat::RED . "You are banned");

				return;
			}

			if($this->hasPermission(Server::BROADCAST_CHANNEL_USERS)){
				$this->server->getPluginManager()->subscribeToPermission(Server::BROADCAST_CHANNEL_USERS, $this);
			}
			if($this->hasPermission(Server::BROADCAST_CHANNEL_ADMINISTRATIVE)){
				$this->server->getPluginManager()->subscribeToPermission(Server::BROADCAST_CHANNEL_ADMINISTRATIVE, $this);
			}

			foreach($this->server->getOnlinePlayers() as $p){
				if($p !== $this and strtolower($p->getName()) === strtolower($this->getName())){
					if($p->kick("logged in from another location") === false){
						$this->close($this->getLeaveMessage(), "Logged in from another location");
						return;
					}
				}elseif($p->loggedIn and $this->getUniqueId()->equals($p->getUniqueId())){
					if($p->kick("logged in from another location") === false){
						$this->close($this->getLeaveMessage(), "Logged in from another location");
						return;
					}
				}
			}

			$nbt = $this->server->getOfflinePlayerData($this->username);
			$this->playedBefore = ($nbt["lastPlayed"] - $nbt["firstPlayed"]) > 1;
			if(!isset($nbt->NameTag)){
				$nbt->NameTag = new StringTag("NameTag", $this->username);
			}else{
				$nbt["NameTag"] = $this->username;
			}
			if(!isset($nbt->Hunger) or !isset($nbt->Health) or !isset($nbt->MaxHealth)){
				$nbt->Hunger = new ShortTag("Hunger", 20);
				$nbt->Health = new ShortTag("Health", 20);
				$nbt->MaxHealth = new ShortTag("MaxHealth", 20);
			}
			$this->food = $nbt["Hunger"];
			Entity::setMaxHealth($nbt["MaxHealth"]);
			Entity::setHealth(($nbt["Health"] <= 0) ? 20 : $nbt["Health"]);

			$this->gamemode = $nbt["playerGameType"] & 0x03;
			if($this->server->getForceGamemode()){
				$this->gamemode = $this->server->getGamemode();
				$nbt->playerGameType = new IntTag("playerGameType", $this->gamemode);
			}

			$this->allowFlight = $this->isCreative();


			if(($level = $this->server->getLevelByName($nbt["Level"])) === null){
				$this->setLevel($this->server->getDefaultLevel());
				$nbt["Level"] = $this->level->getName();
				$nbt["Pos"][0] = $this->level->getSpawnLocation()->x;
				$nbt["Pos"][1] = $this->level->getSpawnLocation()->y;
				$nbt["Pos"][2] = $this->level->getSpawnLocation()->z;
			}else{
				$this->setLevel($level);
			}

			if(!($nbt instanceof CompoundTag)){
				$this->close($this->getLeaveMessage(), "Invalid data");

				return;
			}

			$this->achievements = [];

			/** @var ByteTag $achievement */
			foreach($nbt->Achievements as $achievement){
				$this->achievements[$achievement->getName()] = $achievement->getValue() > 0 ? true : false;
			}

			$nbt->lastPlayed = new LongTag("lastPlayed", floor(microtime(true) * 1000));
			if($this->server->getAutoSave()){
				$this->server->saveOfflinePlayerData($this->username, $nbt, true);
			}

			Entity::__construct($this->level->getChunk($nbt["Pos"][0] >> 4, $nbt["Pos"][2] >> 4, true), $nbt);
			$this->loggedIn = true;

			$this->server->getPluginManager()->callEvent($ev = new PlayerLoginEvent($this, "Plugin reason"));
			if($ev->isCancelled()){
				$this->close($this->getLeaveMessage(), $ev->getKickMessage());

				return;
			}
			$this->server->addOnlinePlayer($this);
			
			$this->dataPacket(new ResourcePacksInfoPacket());
			if(!isset($this->spawnPosition) and isset($this->namedtag->SpawnLevel) and ($level = $this->server->getLevelByName($this->namedtag["SpawnLevel"])) instanceof Level){
				$this->spawnPosition = new Position($this->namedtag["SpawnX"], $this->namedtag["SpawnY"], $this->namedtag["SpawnZ"], $level);
			}
			$spawnPosition = $this->getSpawn();
			
			$pk = new StartGamePacket();
			$pk->entityUniqueId = 0;
			$pk->entityRuntimeId = 0;
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$pk->seed = -1;
			if(method_exists($this->level, "getDimension")){
				$pk->dimension = $this->level->getDimension();
			}
			$pk->gamemode = $this->gamemode & 0x01;
			$pk->difficulty = $this->server->getDifficulty();
			$pk->spawnX = $spawnPosition->getFloorX();
			$pk->spawnY = $spawnPosition->getFloorY();
			$pk->spawnZ = $spawnPosition->getFloorZ();
			$pk->hasBeenLoadedInCreative = 1;
			$pk->dayCycleStopTime = -1; //TODO: implement this properly
			$pk->eduMode = 0;
			$pk->rainLevel = 0; //TODO: implement these properly
			$pk->lightningLevel = 0;
			$pk->commandsEnabled = 1;
			$pk->unknown = "UNKNOWN";
			$pk->worldName = $this->server->getMotd();
			$this->dataPacket($pk);
			
			if (SynapsePM::isUseLoadingScreen()){
				if(class_exists("ChangeDimensionPacket")){
					$pk = new ChangeDimensionPacket();
					$pk->dimension = $this->getLevel()->getDimension();
					$pk->x = $this->getX();
					$pk->y = $this->getY();
					$pk->z = $this->getZ();
					$this->dataPacket($pk);
				} else {
					$this->server->getLogger()->info(TextFormat::RED. "Your server software doesn't support the loading screen feature, please disable it in your config to prevent this message from appearing in the future");
				}
			}
			
			$pk = new SetTimePacket();
			$pk->time = $this->level->getTime();
			$pk->started = $this->level->stopTime == false;
			$this->dataPacket($pk);
			
			$this->sendAttributes(true);
			$this->setNameTagVisible(true);
			$this->setNameTagAlwaysVisible(true);

			$this->server->getLogger()->info($this->getServer()->getLanguage()->translateString("pocketmine.player.logIn", [
				TextFormat::AQUA . $this->username . TextFormat::WHITE,
				$this->ip,
				$this->port,
				TextFormat::GREEN . $this->randomClientId . TextFormat::WHITE,
				$this->id,
				$this->level->getName(),
				round($this->x, 4),
				round($this->y, 4),
				round($this->z, 4)
			]));

			if($this->gamemode === Player::SPECTATOR){
				$pk = new ContainerSetContentPacket();
				$pk->windowid = ContainerSetContentPacket::SPECIAL_CREATIVE;
				$this->dataPacket($pk);
			}else{
				$pk = new ContainerSetContentPacket();
				$pk->windowid = ContainerSetContentPacket::SPECIAL_CREATIVE;
				$pk->slots = isset($this->personalCreativeItems) ? array_merge(Item::getCreativeItems(), $this->personalCreativeItems) : Item::getCreativeItems();
				$this->dataPacket($pk);
			}

			$pk = new SetEntityDataPacket();
			$pk->eid = 0;
			$pk->metadata = [self::DATA_LEAD_HOLDER_EID => [self::DATA_TYPE_LONG, -1]];
			$this->dataPacket($pk);

			$this->forceMovement = $this->teleportPosition = $this->getPosition();
			$this->sendCommandData();		
               }
	}
	
	public function doFirstSpawn()
	{
		if ($this->isFirstTimeLogin){
			$pk = new PlayStatusPacket();
			$pk->status = PlayStatusPacket::PLAYER_SPAWN;
			$this->dataPacket($pk);
		}
		
		parent::doFirstSpawn();
	}
	
	public function transfer(string $hash){
		$clients = $this->synapse->getClientData();
		if(isset($clients[$hash])){
			foreach($this->getLevel()->getEntities() as $entity){
				if(isset($entity->hasSpawned[$this->getLoaderId()])){
					$entity->despawnFrom($this);
				}
			}
			
			if (SynapsePM::isUseLoadingScreen()){
				if(class_exists("ChangeDimensionPacket")){
					$pk = new ChangeDimensionPacket();
					$pk->dimension = $this->getLevel()->getDimension() === Level::DIMENSION_NORMAL ? ChangeDimensionPacket::DIMENSION_NETHER : ChangeDimensionPacket::DIMENSION_NORMAL;
					$pk->x = $this->getX();
					$pk->y = $this->getY();
					$pk->z = $this->getZ();
					$this->dataPacket($pk);
				} else {
					$this->server->getLogger()->info(TextFormat::RED. "Your server software doesn't support the loading screen feature, please disable it in your config to prevent this message from appearing in the future");
				}
				
				$pk = new PlayStatusPacket();
				$pk->status = PlayStatusPacket::PLAYER_SPAWN;
				$this->dataPacket($pk);
				$this->forceSendEmptyChunks();
				$this->getServer()->getScheduler()->scheduleDelayedTask(new TransferTask($this, $hash), 1);
			}else{
				(new TransferTask($this, $hash))->onRun(0);
			}
		}
	}
	
	protected function forceSendEmptyChunks(){
		$chunkX = $this->getX() >> 4;
		$chunkZ = $this->getZ() >> 4;
		
		for ($x = -3; $x < 3; ++$x){
			for ($z = -3; $z < 3; ++$z){
				$pk = new FullChunkDataPacket();
				$pk->chunkX = $chunkX + $x;
				$pk->chunkZ = $chunkZ + $z;
				$pk->data = '';
				$this->dataPacket($pk);
			}
		}
	}

	public function handleDataPacket(DataPacket $packet){
		$this->lastPacketTime = microtime(true);
		return parent::handleDataPacket($packet);
	}

	public function onUpdate($currentTick){
		if((microtime(true) - $this->lastPacketTime) >= 5 * 60){//5 minutes time out
			$this->close("", "timeout");
			return false;
		}
		return parent::onUpdate($currentTick);
	}

	public function setUniqueId(UUID $uuid){
		$this->uuid = $uuid;
	}

	public function dataPacket(DataPacket $packet, $needACK = false){
		if($packet instanceof PlayerListPacket){
			$pk = new FastPlayerListPacket();
			$pk->sendTo = $this->uuid;
			$pk->type = $packet->type;
			foreach($packet->entries as $entry){
				if($packet->type !== PlayerListPacket::TYPE_REMOVE){
					array_pop($entry);
					array_pop($entry);
				}
				$pk->entries[] = $entry;
			}
			$this->synapse->sendDataPacket($pk);
			return;
		}
		
		$this->server->getPluginManager()->callEvent($ev = new DataPacketSendEvent($this, $packet));
		if($ev->isCancelled()){
			return;
		}
		
		$this->interface->putPacket($this, $packet, $needACK);
	}

	public function directDataPacket(DataPacket $packet, $needACK = false){
		if($packet instanceof PlayerListPacket){
			$pk = new FastPlayerListPacket();
			$pk->sendTo = $this->uuid;
			$pk->type = $packet->type;
			foreach($packet->entries as $entry){
				if($packet->type !== PlayerListPacket::TYPE_REMOVE){
					array_pop($entry);
					array_pop($entry);
				}
				$pk->entries[] = $entry;
			}
			$this->synapse->sendDataPacket($pk);
			return;
		}
		
		$this->server->getPluginManager()->callEvent($ev = new DataPacketSendEvent($this, $packet));
		if($ev->isCancelled()){
			return;
		}
		
		$this->interface->putPacket($this, $packet, $needACK, true);
	}
	
	public function isFirstLogin(){
		return $this->isFirstTimeLogin;
	}
	
	public function getSynapse() : Synapse {
		return $this->synapse;
	}
}
