<?php
declare(strict_types=1);
namespace synapsepm\network\protocol\spp;

class InformationPacket extends DataPacket {
	const NETWORK_ID = Info::INFORMATION_PACKET;
	const TYPE_LOGIN = 0;
	const TYPE_CLIENT_DATA = 1;
	const TYPE_PLUGIN_MESSAGE = 2;
	const INFO_LOGIN_SUCCESS = 'success';
	const INFO_LOGIN_FAILED = 'failed';

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