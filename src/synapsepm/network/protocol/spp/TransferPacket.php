<?php
declare(strict_types=1);
namespace synapsepm\network\protocol\spp;

use pocketmine\utils\UUID;


class TransferPacket extends DataPacket {
	const NETWORK_ID = Info::TRANSFER_PACKET;

	/** @var UUID */
	public $uuid;
	public $clientHash;
	public $afterLoginPacket = '';

	public function encode() {
		$this->reset();
		$this->putUUID($this->uuid);
		$this->putString($this->clientHash);
		$this->put($this->afterLoginPacket);
	}

	public function decode() {
		$this->uuid = $this->getUUID();
		$this->clientHash = $this->getString();
		$this->afterLoginPacket = $this->get(true);
	}
}