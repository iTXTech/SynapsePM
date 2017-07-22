<?php
declare(strict_types=1);
namespace synapsepm\network\protocol\spp;

class ConnectPacket extends DataPacket {
	const NETWORK_ID = Info::CONNECT_PACKET;

	public $protocol = Info::CURRENT_PROTOCOL;
	public $maxPlayers;
	public $isMainServer;
	public $description;
	public $password;

	public function encode() {
		$this->reset();
		$this->putInt($this->protocol);
		$this->putInt($this->maxPlayers);
		$this->putBool($this->isMainServer);
		$this->putString($this->description);
		$this->putString($this->password);
	}

	public function decode() {
		$this->protocol = $this->getInt();
		$this->maxPlayers = $this->getInt();
		$this->isMainServer = $this->getBool();
		$this->description = $this->getString();
		$this->password = $this->getString();
	}
}