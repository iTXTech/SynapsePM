<?php
declare(strict_types=1);
namespace synapsepm\network\synlib;

use pocketmine\utils\Binary;
use synapsepm\network\protocol\spp\Info;


class ServerConnection {
	private $receiveBuffer = '';
	/** @var resource */
	private $socket;
	private $ip;
	private $port;
	/** @var SynapseClient */
	private $server;
	private $lastCheck;
	private $connected;

	public function __construct(SynapseClient $server, SynapseSocket $socket) {
		$this->server = $server;
		$this->socket = $socket;
		@socket_getpeername($this->socket->getSocket(), $address, $port);
		$this->ip = $address;
		$this->port = $port;

		$this->lastCheck = microtime(true);
		$this->connected = true;

		$this->run();
	}

	public function run() {
		$this->tickProcessor();
	}

	private function tickProcessor() {
		while (!$this->server->isShutdown()) {
			$start = microtime(true);
			$this->tick();
			$time = microtime(true);
			if ($time - $start < 0.01) {
				@time_sleep_until($time + 0.01 - ($time - $start));
			}
		}
		$this->tick();
		$this->socket->close();
	}

	private function tick() {
		$this->update();
		if (($packets = $this->readPackets()) !== null) {
			foreach ($packets as $packet) {
				$this->server->pushThreadToMainPacket($packet);
			}
		}
		while (($packet = $this->server->readMainToThreadPacket()) !== null && strlen($packet) !== 0) {
			$this->writePacket($packet);
		}
	}

	public function getHash() : string {
		return $this->ip . ':' . $this->port;
	}

	public function getIp() : string {
		return $this->ip;
	}

	public function getPort() : int {
		return $this->port;
	}

	public function update() {
		if ($this->server->needReconnect and $this->connected) {
			$this->connected = false;
			$this->server->needReconnect = false;
		}
		if ($this->connected) {
			$err = socket_last_error($this->socket->getSocket());
			socket_clear_error($this->socket->getSocket());
			if ($err === 10057 or $err === 10054) {
				$this->server->getLogger()->error('Synapse connection has disconnected unexpectedly');
				$this->connected = false;
				$this->server->setConnected(false);
			}
			else {
				$data = @socket_read($this->socket->getSocket(), 65535, PHP_BINARY_READ);
				if ($data !== '') {
					$this->receiveBuffer .= $data;
				}
			}
		}
		else {
			if ((($time = microtime(true)) - $this->lastCheck) >= 3) {
				$this->server->getLogger()->notice('Trying to re-connect to Synapse Server');
				if ($this->socket->connect()) {
					$this->connected = true;
					@socket_getpeername($this->socket->getSocket(), $address, $port);
					$this->ip = $address;
					$this->port = $port;
					$this->server->setConnected(true);
					$this->server->setNeedAuth(true);
				}
				$this->lastCheck = $time;
			}
		}
	}

	public function getSocket() {
		return $this->socket;
	}

	/**
	 * @return string[]
	 */
	public function readPackets() : array {
		$packets = [];
		if ($this->receiveBuffer !== '') {
			$offset = 0;
			$len = strlen($this->receiveBuffer);
			while ($offset < $len) {
				if ($offset > $len - 7) {
					break;
				}
				$magic = Binary::readShort(substr($this->receiveBuffer, $offset, 2));
				if ($magic !== Info::PROTOCOL_MAGIC) {
					throw new \RuntimeException('Magic does not match.');
				}
				$pid = $this->receiveBuffer{$offset + 2};
				$pkLen = Binary::readInt(substr($this->receiveBuffer, $offset + 3, 4));
				$offset += 7;

				if ($pkLen <= ($len - $offset)) {
					$buf = $pid . substr($this->receiveBuffer, $offset, $pkLen);
					$offset += $pkLen;

					$packets[] = $buf;
				}
				else {
					$offset -= 7;
					break;
				}
			}
			if ($offset < $len) {
				$this->receiveBuffer = substr($this->receiveBuffer, $offset);
			}
			else {
				$this->receiveBuffer = '';
			}
		}

		return $packets;
	}

	public function writePacket($data) {
		@socket_write($this->socket->getSocket(), Binary::writeShort(Info::PROTOCOL_MAGIC));
		@socket_write($this->socket->getSocket(), $data{0});
		@socket_write($this->socket->getSocket(), Binary::writeInt(strlen($data) - 1));
		@socket_write($this->socket->getSocket(), substr($data, 1));
	}
}
