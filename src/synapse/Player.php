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

use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\LongTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\FullChunkDataPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\Player as PMPlayer;
use pocketmine\utils\UUID;
use synapse\event\player\PlayerConnectEvent;
use synapse\network\protocol\spp\FastPlayerListPacket;
use synapse\network\protocol\spp\PlayerLoginPacket;
use synapse\network\SynLibInterface;
use synapse\task\TransferTask;


class Player extends PMPlayer {
	/** @var Synapse */
	private $synapse;
	private $isFirstTimeLogin = false;
	private $lastPacketTime;

	public function __construct(SynLibInterface $interface, $clientID, $ip, $port) {
		parent::__construct($interface, $clientID, $ip, $port);
		$this->synapse = $interface->getSynapse();
	}

	public function handleLoginPacket(PlayerLoginPacket $packet) {
		$this->isFirstTimeLogin = $packet->isFirstTime;
		$this->server->getPluginManager()->callEvent($ev = new PlayerConnectEvent($this, $this->isFirstTimeLogin));
		$pk = $this->synapse->getPacket($packet->cachedLoginPacket);
		$pk->decode();
		$this->handleDataPacket($pk);
	}

	protected function processLogin() {
		if ($this->isFirstTimeLogin) {
			parent::processLogin();
		}
		else {
			if (!$this->server->isWhitelisted($this->iusername)) {
				$this->close($this->getLeaveMessage(), "Server is white-listed");

				return;
			}
			elseif ($this->server->getNameBans()->isBanned($this->iusername) or $this->server->getIPBans()->isBanned($this->getAddress())) {
				$this->close($this->getLeaveMessage(), "You are banned");

				return;
			}

			foreach ($this->server->getOnlinePlayers() as $p) {
				if ($p !== $this and $p->iusername === $this->iusername) {
					if ($p->kick("logged in from another location") === false) {
						$this->close($this->getLeaveMessage(), "Logged in from another location");

						return;
					}
				}
				elseif ($p->loggedIn and $this->getUniqueId()->equals($p->getUniqueId())) {
					if ($p->kick("logged in from another location") === false) {
						$this->close($this->getLeaveMessage(), "Logged in from another location");

						return;
					}
				}
			}

			$this->namedtag = $this->server->getOfflinePlayerData($this->username);

			$this->playedBefore = ($this->namedtag["lastPlayed"] - $this->namedtag["firstPlayed"]) > 1; // microtime(true) - microtime(true) may have less than one millisecond difference
			if (!isset($this->namedtag->NameTag)) {
				$this->namedtag->NameTag = new StringTag("NameTag", $this->username);
			}
			else {
				$this->namedtag["NameTag"] = $this->username;
			}
			$this->gamemode = $this->namedtag["playerGameType"] & 0x03;
			if ($this->server->getForceGamemode()) {
				$this->gamemode = $this->server->getGamemode();
				$this->namedtag->playerGameType = new IntTag("playerGameType", $this->gamemode);
			}

			$this->allowFlight = (bool)($this->gamemode & 0x01);

			if (($level = $this->server->getLevelByName((string)$this->namedtag["Level"])) === null) {
				$this->setLevel($this->server->getDefaultLevel());
				$this->namedtag["Level"] = $this->level->getName();
				$this->namedtag["Pos"][0] = $this->level->getSpawnLocation()->x;
				$this->namedtag["Pos"][1] = $this->level->getSpawnLocation()->y;
				$this->namedtag["Pos"][2] = $this->level->getSpawnLocation()->z;
			}
			else {
				$this->setLevel($level);
			}

			$this->achievements = [];

			/** @var ByteTag $achievement */
			foreach ($this->namedtag->Achievements as $achievement) {
				$this->achievements[$achievement->getName()] = $achievement->getValue() > 0 ? true : false;
			}

			$this->namedtag->lastPlayed = new LongTag("lastPlayed", (int)floor(microtime(true) * 1000));
			if ($this->server->getAutoSave()) {
				$this->server->saveOfflinePlayerData($this->username, $this->namedtag, true);
			}

			$this->sendPlayStatus(PlayStatusPacket::LOGIN_SUCCESS);

			$this->loggedIn = true;

			$pk = new ResourcePacksInfoPacket();
			$manager = $this->server->getResourceManager();
			$pk->resourcePackEntries = $manager->getResourceStack();
			$pk->mustAccept = $manager->resourcePacksRequired();
			$this->dataPacket($pk);
		}
	}

	public function doFirstSpawn() {
		if ($this->isFirstTimeLogin) {
			$pk = new PlayStatusPacket();
			$pk->status = PlayStatusPacket::PLAYER_SPAWN;
			$this->dataPacket($pk);
		}

		parent::doFirstSpawn();
	}

	public function transfer(string $hash) {
		$clients = $this->synapse->getClientData();
		if (isset($clients[$hash])) {
			foreach ($this->getLevel()->getEntities() as $entity) {
				if (isset($entity->hasSpawned[$this->getLoaderId()])) {
					$entity->despawnFrom($this);
				}
			}


			(new TransferTask($this, $hash))->onRun(0);
		}
	}

	protected function forceSendEmptyChunks() {
		$chunkX = $this->getX() >> 4;
		$chunkZ = $this->getZ() >> 4;

		for ($x = -3; $x < 3; ++$x) {
			for ($z = -3; $z < 3; ++$z) {
				$pk = new FullChunkDataPacket();
				$pk->chunkX = $chunkX + $x;
				$pk->chunkZ = $chunkZ + $z;
				$pk->data = '';
				$this->dataPacket($pk);
			}
		}
	}

	public function handleDataPacket(DataPacket $packet) {
		$this->lastPacketTime = microtime(true);

		return parent::handleDataPacket($packet);
	}

	public function onUpdate($currentTick) {
		if ((microtime(true) - $this->lastPacketTime) >= 5 * 60) {
			$this->close("", "timeout");

			return false;
		}

		return parent::onUpdate($currentTick);
	}

	public function setUniqueId(UUID $uuid) {
		$this->uuid = $uuid;
	}

	protected function processPacket(DataPacket $packet) : bool {
		if ($packet instanceof PlayerListPacket) {
			$pk = new FastPlayerListPacket();
			$pk->sendTo = $this->uuid;
			$pk->type = $packet->type;
			foreach ($packet->entries as $entry) {
				if ($packet->type !== PlayerListPacket::TYPE_REMOVE) {
					array_pop($entry);
					array_pop($entry);
				}
				$pk->entries[] = $entry;
			}
			$this->synapse->sendDataPacket($pk);

			return true;
		}

		return false;
	}

	public function dataPacket(DataPacket $packet, $needACK = false) {
		if ($this->processPacket($packet)) {
			return;
		}

		$this->server->getPluginManager()->callEvent($ev = new DataPacketSendEvent($this, $packet));
		if ($ev->isCancelled()) {
			return;
		}

		$this->interface->putPacket($this, $packet, $needACK);
	}

	public function directDataPacket(DataPacket $packet, $needACK = false) {
		if ($packet instanceof PlayerListPacket) {
			$pk = new FastPlayerListPacket();
			$pk->sendTo = $this->uuid;
			$pk->type = $packet->type;
			foreach ($packet->entries as $entry) {
				if ($packet->type !== PlayerListPacket::TYPE_REMOVE) {
					array_pop($entry);
					array_pop($entry);
				}
				$pk->entries[] = $entry;
			}
			$this->synapse->sendDataPacket($pk);

			return;
		}

		$this->server->getPluginManager()->callEvent($ev = new DataPacketSendEvent($this, $packet));
		if ($ev->isCancelled()) {
			return;
		}

		$this->interface->putPacket($this, $packet, $needACK, true);
	}

	public function isFirstLogin() {
		return $this->isFirstTimeLogin;
	}

	public function getSynapse() : Synapse {
		return $this->synapse;
	}
}
