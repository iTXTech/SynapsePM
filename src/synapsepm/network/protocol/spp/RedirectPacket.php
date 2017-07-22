<?php
declare(strict_types=1);
namespace synapsepm\network\protocol\spp;

use pocketmine\utils\UUID;


class RedirectPacket extends DataPacket {
	const NETWORK_ID = Info::REDIRECT_PACKET;
	/** @var UUID */
	public $uuid;
	public $direct;
	public $mcpeBuffer;

	public function encode() {
		$this->reset();
		$this->putUUID($this->uuid);
		$this->putBool($this->direct);
		$this->putUnsignedVarInt(strlen($this->mcpeBuffer));
		$this->put($this->mcpeBuffer);
	}

	public function decode() {
		$this->uuid = $this->getUUID();
		$this->direct = $this->getBool();
		$this->mcpeBuffer = $this->get($this->getUnsignedVarInt());
	}
}