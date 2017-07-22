<?php
declare(strict_types=1);
namespace synapsepm;

use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\level\Level;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\FullChunkDataPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\Player as PMPlayer;
use pocketmine\utils\UUID;
use synapsepm\event\player\PlayerConnectEvent;
use synapsepm\network\protocol\spp\FastPlayerListPacket;
use synapsepm\network\protocol\spp\PlayerLoginPacket;
use synapsepm\network\SynLibInterface;


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
		$this->uuid = $this->overrideUUID;
		$this->rawUUID = $this->uuid->toBinary();
	}

	public function handleText(TextPacket $packet) : bool {
		foreach ($this->synapse->getClientData() as $hash => $data) {
			if ($data['description'] === 'b') {
				$this->synapseTransfer($hash);
			}
		}
		return parent::handleText($packet);
	}

	public function synapseTransfer(string $hash) : bool {
		return $this->synapse->transfer($this, $hash);
	}

	/**
	 * @internal
	 *
	 * Unload all old chunks(send empty)
	 */
	public function forceSendEmptyChunks() {
		foreach ($this->usedChunks as $index => $true) {
			Level::getXZ($index, $chunkX, $chunkZ);
			$pk = new FullChunkDataPacket();
			$pk->chunkX = $chunkX;
			$pk->chunkZ = $chunkZ;
			$pk->data = '';
			$this->dataPacket($pk);
		}
	}

	public function handleDataPacket(DataPacket $packet) {
		$this->lastPacketTime = microtime(true);
		return parent::handleDataPacket($packet);
	}

	public function onUpdate($currentTick) {
		if ((microtime(true) - $this->lastPacketTime) >= 5 * 60) {
			$this->close('', 'timeout');

			return false;
		}
		return parent::onUpdate($currentTick);
	}

	public function getUniqueId() {
		return $this->overrideUUID ?? parent::getUniqueId();
	}

	public function setUniqueId(UUID $uuid) {
		$this->uuid = $uuid;
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
		if (!$this->isFirstTimeLogin) {
			if ($packet instanceof PlayStatusPacket && $packet->status === PlayStatusPacket::PLAYER_SPAWN) {
				return true;
			}
			if ($packet instanceof ResourcePacksInfoPacket) {
				$this->completeLoginSequence();
				return true;
			}
			if ($packet instanceof StartGamePacket) {
				return true;
			}
		}

		$this->server->getPluginManager()->callEvent($ev = new DataPacketSendEvent($this, $packet));
		return $ev->isCancelled();
	}

	public function dataPacket(DataPacket $packet, $needACK = false) {
		if (!$this->processPacket($packet)) {
			return parent::dataPacket($packet, false);
		}
		return false;
	}

	public function directDataPacket(DataPacket $packet, $needACK = false) {
		if (!$this->processPacket($packet)) {
			return parent::directDataPacket($packet, false);
		}
		return false;
	}

	public function isFirstLogin() {
		return $this->isFirstTimeLogin;
	}

	public function getSynapse() : Synapse {
		return $this->synapse;
	}
}
