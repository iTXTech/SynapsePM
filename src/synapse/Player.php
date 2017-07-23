<?php
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
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
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

	/** @var UUID */
	private $overrideUUID;

	public function __construct(SynLibInterface $interface, $clientID, $ip, $port) {
		parent::__construct($interface, $clientID, $ip, $port);
		$this->synapse = $interface->getSynapse();
	}

	public function handleLoginPacket(PlayerLoginPacket $packet) {
		$this->isFirstTimeLogin = $packet->isFirstTime;
		$this->server->getPluginManager()->callEvent($ev = new PlayerConnectEvent($this, $this->isFirstTimeLogin));
		$loginPacket = $this->synapse->getPacket($packet->cachedLoginPacket);
		if ($loginPacket === null) {
			$this->close($this->getLeaveMessage(), 'Invalid login packet');
			return;
		}
		$this->handleDataPacket($loginPacket);
	}

	protected function processLogin() {
		/*if (!$this->isFirstTimeLogin || $this->slowLoginUntil === 0) {
			parent::processLogin();

			if ($this->isFirstTimeLogin && $this->slowLoginUntil === 0) {
				$this->slowLoginUntil = microtime(true) + 0.05;
				return;
			}
		}*/

		parent::processLogin();

		//parent::completeLoginSequence();
	}

	public function doFirstSpawn() {
		if ($this->isFirstTimeLogin) {
			$pk = new PlayStatusPacket();
			$pk->status = PlayStatusPacket::PLAYER_SPAWN;
			$this->dataPacket($pk);
		}
		parent::doFirstSpawn();
	}

	public function synapseTransfer(string $hash) {
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

	public function getUniqueId() {
		return $this->overrideUUID ?? parent::getUniqueId();
	}

	public function setUniqueId(UUID $uuid) {
		$this->overrideUUID = $uuid;
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

		$this->interface->putPacket($this, $packet, $needACK, false);
	}

	public function directDataPacket(DataPacket $packet, $needACK = false) {
		if ($this->processPacket($packet)) {
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
