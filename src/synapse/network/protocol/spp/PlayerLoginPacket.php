<?php
namespace synapse\network\protocol\spp;

use pocketmine\utils\UUID;


class PlayerLoginPacket extends DataPacket {
	const NETWORK_ID = Info::PLAYER_LOGIN_PACKET;
	/** @var UUID */
	public $uuid;
	public $address;
	public $port;
	public $isFirstTime;
	public $cachedLoginPacket;

	public function encode() {
		$this->reset();
		$this->putUUID($this->uuid);
		$this->putString($this->address);
		$this->putInt($this->port);
		$this->putByte($this->isFirstTime ? 1 : 0);
		$this->putShort(strlen($this->cachedLoginPacket));
		$this->put($this->cachedLoginPacket);
	}

	public function decode() {
		$this->uuid = $this->getUUID();
		$this->address = $this->getString();
		$this->port = $this->getInt();
		$this->isFirstTime = ($this->getByte() == 1) ? true : false;
		$this->cachedLoginPacket = $this->get($this->getShort());
	}
}