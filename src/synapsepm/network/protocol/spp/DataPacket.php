<?php
declare(strict_types=1);
namespace synapsepm\network\protocol\spp;

use pocketmine\utils\BinaryStream;


abstract class DataPacket extends BinaryStream {
	const NETWORK_ID = 0;

	public function pid() {
		return $this::NETWORK_ID;
	}

	abstract public function encode();

	abstract public function decode();

	public function reset() {
		$this->buffer = chr($this::NETWORK_ID);
		$this->offset = 0;
	}
}