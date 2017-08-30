<?php
declare(strict_types=1);
namespace synapsepm\network;

use synapsepm\network\protocol\spp\BroadcastPacket;
use synapsepm\network\protocol\spp\ConnectPacket;
use synapsepm\network\protocol\spp\DataPacket;
use synapsepm\network\protocol\spp\DisconnectPacket;
use synapsepm\network\protocol\spp\FastPlayerListPacket;
use synapsepm\network\protocol\spp\HeartbeatPacket;
use synapsepm\network\protocol\spp\Info;
use synapsepm\network\protocol\spp\InformationPacket;
use synapsepm\network\protocol\spp\PlayerLoginPacket;
use synapsepm\network\protocol\spp\PlayerLogoutPacket;
use synapsepm\network\protocol\spp\RedirectPacket;
use synapsepm\network\protocol\spp\TransferPacket;
use synapsepm\network\synlib\SynapseClient;
use synapsepm\Synapse;


class SynapseInterface {
	private $synapse;
	private $ip;
	private $port;
	/** @var SynapseClient */
	private $client;
	/** @var DataPacket[] */
	private $packetPool = [];
	private $connected = true;

	public function __construct(Synapse $server, string $ip, int $port) {
		$this->synapse = $server;
		$this->ip = $ip;
		$this->port = $port;
		$this->registerPackets();
		$this->client = new SynapseClient($server->getLogger(), $server->getServer()->getLoader(), $port, $ip);
	}

	public function getSynapse() {
		return $this->synapse;
	}

	public function reconnect() {
		$this->client->reconnect();
	}

	public function shutdown() {
		$this->client->shutdown();
	}

	public function putPacket(DataPacket $pk) {
		$pk->encode();
		$this->client->pushMainToThreadPacket($pk->buffer);
	}

	public function isConnected() : bool {
		return $this->connected;
	}

	public function process() {
		while (($packet = $this->client->readThreadToMainPacket()) !== null && strlen($packet) !== 0) {
			$this->handlePacket($packet);
		}
		$this->connected = $this->client->isConnected();
		if ($this->client->isNeedAuth()) {
			$this->synapse->connect();
			$this->client->setNeedAuth(false);
		}
	}

	/**
	 * @param $buffer
	 *
	 * @return DataPacket|null
	 */
	public function getPacket($buffer) {
		$pid = ord($buffer{0});
		/** @var DataPacket $class */
		$class = $this->packetPool[$pid];
		if ($class !== null) {
			$pk = clone $class;
			$pk->setBuffer($buffer, 1);

			return $pk;
		}

		return null;
	}

	public function handlePacket($buffer) {
		if (($pk = $this->getPacket($buffer)) !== null) {
			$pk->decode();
			$this->synapse->handleDataPacket($pk);
		}
	}

	/**
	 * @param int    $id 0-255
	 * @param string $class
	 */
	public function registerPacket($id, $class) {
		$this->packetPool[$id] = new $class;
	}

	private function registerPackets() {
		$this->packetPool = new \SplFixedArray(256);

		$this->registerPacket(Info::HEARTBEAT_PACKET, HeartbeatPacket::class);
		$this->registerPacket(Info::CONNECT_PACKET, ConnectPacket::class);
		$this->registerPacket(Info::DISCONNECT_PACKET, DisconnectPacket::class);
		$this->registerPacket(Info::REDIRECT_PACKET, RedirectPacket::class);
		$this->registerPacket(Info::PLAYER_LOGIN_PACKET, PlayerLoginPacket::class);
		$this->registerPacket(Info::PLAYER_LOGOUT_PACKET, PlayerLogoutPacket::class);
		$this->registerPacket(Info::INFORMATION_PACKET, InformationPacket::class);
		$this->registerPacket(Info::TRANSFER_PACKET, TransferPacket::class);
		$this->registerPacket(Info::BROADCAST_PACKET, BroadcastPacket::class);
		$this->registerPacket(Info::FAST_PLAYER_LIST_PACKET, FastPlayerListPacket::class);
	}
}
