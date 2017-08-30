<?php
declare(strict_types=1);
namespace synapsepm\network\protocol\spp;

class DisconnectPacket extends DataPacket {
	const NETWORK_ID = Info::DISCONNECT_PACKET;
	const TYPE_WRONG_PROTOCOL = 0;
	const TYPE_GENERIC = 1;

	public $type;
	public $message;

	public function encode() {
		$this->reset();
		$this->putByte($this->type);
		$this->putString($this->message);
	}

	public function decode() {
		$this->type = $this->getByte();
		$this->message = $this->getString();
	}
}