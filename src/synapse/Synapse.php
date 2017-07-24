<?php
namespace synapse;

use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\Server;
use pocketmine\utils\BinaryStream;
use pocketmine\utils\MainLogger;
use pocketmine\utils\Utils;
use synapse\event\synapse\SynapsePluginMsgRecvEvent;
use synapse\network\protocol\spp\BroadcastPacket;
use synapse\network\protocol\spp\ConnectPacket;
use synapse\network\protocol\spp\DataPacket;
use synapse\network\protocol\spp\DisconnectPacket;
use synapse\network\protocol\spp\HeartbeatPacket;
use synapse\network\protocol\spp\Info;
use synapse\network\protocol\spp\InformationPacket;
use synapse\network\protocol\spp\PlayerLoginPacket;
use synapse\network\protocol\spp\PlayerLogoutPacket;
use synapse\network\protocol\spp\RedirectPacket;
use synapse\network\SynapseInterface;
use synapse\network\SynLibInterface;


class Synapse {
	/** @var Server */
	private $server;
	/** @var MainLogger */
	private $logger;
	private $serverIp;
	private $port;
	private $isMainServer;
	private $password;
	private $interface;
	private $verified = false;
	private $lastUpdate;
	private $lastRecvInfo;
	/** @var Player[] */
	private $players = [];
	/** @var SynLibInterface */
	private $synLibInterface;
	private $clientData = [];
	private $description;
	private $verboseLogging;
	private $connectionTime = PHP_INT_MAX;

	public function __construct(Server $server, array $config) {
		$this->server = $server;
		$this->serverIp = $config["server-ip"] ?? '127.0.0.1';
		$this->port = $config["server-port"] ?? 10305;
		$this->isMainServer = $config["is-main-server"] ?? true;
		$this->password = $config["server-password"];
		$this->description = $config["description"];
		$this->verboseLogging = $config["verbose-logging"] ?? false;
		$this->logger = $server->getLogger();
		$this->interface = new SynapseInterface($this, $this->serverIp, $this->port);
		$this->synLibInterface = new SynLibInterface($this, $this->interface);
		$this->lastUpdate = microtime(true);
		$this->lastRecvInfo = microtime(true);
		$this->description = $config["description"];
		$this->isMainServer = $this->description === 'a';
		$this->connect();
	}

	public function getClientData() {
		return $this->clientData;
	}

	public function getServer() {
		return $this->server;
	}

	public function getInterface() {
		return $this->interface;
	}

	public function shutdown() {
		if ($this->verified) {
			$pk = new DisconnectPacket();
			$pk->type = DisconnectPacket::TYPE_GENERIC;
			$pk->message = "Server closed";
			$this->sendDataPacket($pk);
			$this->getLogger()->debug("Synapse client has disconnected from Synapse server");
		}
	}

	public function getDescription() : string {
		return $this->description;
	}

	public function setDescription(string $description) {
		$this->description = $description;
	}

	public function sendDataPacket(DataPacket $pk) {
		$this->interface->putPacket($pk);
	}

	public function connect() {
		$this->verified = false;
		$pk = new ConnectPacket();
		$pk->password = $this->password;
		$pk->isMainServer = $this->isMainServer();
		$pk->description = $this->description;
		$pk->maxPlayers = $this->server->getMaxPlayers();
		$pk->protocol = Info::CURRENT_PROTOCOL;
		$this->sendDataPacket($pk);
		$this->connectionTime = microtime(true);
	}

	public function tick() {
		$this->interface->process();
		if ((($time = microtime(true)) - $this->lastUpdate) >= 5) {
			$this->lastUpdate = $time;
			$pk = new HeartbeatPacket();
			$pk->tps = $this->server->getTicksPerSecondAverage();
			$pk->load = $this->server->getTickUsageAverage();
			$pk->upTime = microtime(true) - \pocketmine\START_TIME;
			$this->sendDataPacket($pk);
		}
		if (((($time = microtime(true)) - $this->lastUpdate) >= 30) and $this->interface->isConnected()) {
			$this->interface->reconnect();
		}
		if (microtime(true) - $this->connectionTime >= 15 and !$this->verified) {
			$this->interface->reconnect();
		}
	}

	public function getServerIp() : string {
		return $this->serverIp;
	}

	public function getPort() : int {
		return $this->port;
	}

	public function isMainServer() : bool {
		return $this->isMainServer;
	}

	public function broadcastPacket(array $players, DataPacket $packet, $direct = false) {
		$packet->encode();
		$pk = new BroadcastPacket();
		$pk->direct = $direct;
		$pk->payload = $packet->getBuffer();
		foreach ($players as $player) {
			$pk->entries[] = $player->getUniqueId();
		}
		$this->sendDataPacket($pk);
	}

	public function getLogger() {
		return $this->logger;
	}

	public function getHash() : string {
		return $this->serverIp . ":" . $this->port;
	}

	public function getPacket($buffer) {
		$pid = ord($buffer{0});
		if ($pid === 0xFF) {
			$pid = 0xFE;
		}
		if (($data = PacketPool::getPacketById($pid)) === null) {
			return null;
		}
		$data->setBuffer($buffer, 1);
		return $data;
	}

	public function removePlayer(Player $player) {
		if (isset($this->players[$uuid = $player->getUniqueId()->toBinary()])) {
			unset($this->players[$uuid]);
		}
	}

	public function handleDataPacket(DataPacket $pk) {
		if ($this->verboseLogging) {
			$this->logger->debug("Received packet " . $pk::NETWORK_ID . " from {$this->serverIp}:{$this->port}");
		}
		switch ($pk::NETWORK_ID) {
		case Info::DISCONNECT_PACKET:
			/** @var DisconnectPacket $pk */
			$this->verified = false;
			switch ($pk->type) {
			case DisconnectPacket::TYPE_GENERIC:
				$this->getLogger()->notice("Synapse Client has disconnected due to " . $pk->message);
				$this->interface->reconnect();
				break;
			case DisconnectPacket::TYPE_WRONG_PROTOCOL:
				$this->getLogger()->error($pk->message);
				break;
			}
			break;
		case Info::INFORMATION_PACKET:
			/** @var InformationPacket $pk */
			switch ($pk->type) {
			case InformationPacket::TYPE_LOGIN:
				if ($pk->message == InformationPacket::INFO_LOGIN_SUCCESS) {
					$this->logger->info("Login success to {$this->serverIp}:{$this->port}");
					$this->verified = true;
				}
				elseif ($pk->message == InformationPacket::INFO_LOGIN_FAILED) {
					$this->logger->info("Login failed to {$this->serverIp}:{$this->port}");
				}
				break;
			case InformationPacket::TYPE_CLIENT_DATA:
				$this->clientData = json_decode($pk->message, true)["clientList"];
				$this->lastRecvInfo = microtime();
				break;
			case InformationPacket::TYPE_PLUGIN_MESSAGE:
				$this->server->getPluginManager()->callEvent(new SynapsePluginMsgRecvEvent($this, $pk->message));
				break;
			}
			break;
		case Info::PLAYER_LOGIN_PACKET:
			/** @var PlayerLoginPacket $pk */
			$ev = new PlayerCreationEvent($this->synLibInterface, Player::class, Player::class, null, $pk->address, $pk->port);
			$this->server->getPluginManager()->callEvent($ev);
			$class = $ev->getPlayerClass();

			/** @var Player $player */
			$player = new $class($this->synLibInterface, $ev->getClientId(), $ev->getAddress(), $ev->getPort());
			$player->setUniqueId($pk->uuid);
			$this->server->addPlayer(spl_object_hash($player), $player);
			$this->players[$pk->uuid->toBinary()] = $player;
			$player->handleLoginPacket($pk);
			break;
		case Info::REDIRECT_PACKET:
			/** @var RedirectPacket $pk */
			if (isset($this->players[$uuid = $pk->uuid->toBinary()])) {
				$innerPacket = $this->getPacket($pk->mcpeBuffer);
				if ($innerPacket !== null) {
					$this->players[$uuid]->handleDataPacket($innerPacket);
				}
			}
			break;
		case Info::PLAYER_LOGOUT_PACKET:
			/** @var PlayerLogoutPacket $pk */
			if (isset($this->players[$uuid = $pk->uuid->toBinary()])) {
				$this->players[$uuid]->close("", $pk->reason, false);
				$this->removePlayer($this->players[$uuid]);
			}
			break;
		}
	}

	public function sendPluginMessage(string $message) {
		$pk = new InformationPacket();
		$pk->type = InformationPacket::TYPE_PLUGIN_MESSAGE;
		$pk->message = $message;
		$this->sendDataPacket($pk);
	}
}
