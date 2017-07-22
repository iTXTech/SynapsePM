<?php
declare(strict_types=1);
namespace synapse\network\protocol\spp;

use pocketmine\utils\UUID;


class BroadcastPacket extends DataPacket {
	const NETWORK_ID = Info::BROADCAST_PACKET;
	/** @var UUID[] */
	public $entries = [];
	public $direct;
	public $payload;

	public function encode() {
		$this->reset();
		$this->putByte($this->direct ? 1 : 0);
		$this->putShort(count($this->entries));
		foreach ($this->entries as $uuid) {
			$this->putUUID($uuid);
		}
		$this->putString($this->payload);
	}

	public function decode() {
		$this->direct = ($this->getByte() == 1) ? true : false;
		$len = $this->getShort();
		for ($i = 0; $i < $len; $i++) {
			$this->entries[] = $this->getUUID();
		}
		$this->payload = $this->getString();
	}
}
