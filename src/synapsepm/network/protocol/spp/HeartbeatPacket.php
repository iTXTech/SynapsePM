<?php
declare(strict_types=1);
namespace synapsepm\network\protocol\spp;

class HeartbeatPacket extends DataPacket {
	const NETWORK_ID = Info::HEARTBEAT_PACKET;

	public $tps;
	public $load;
	public $upTime;

	public function encode() {
		$this->reset();
		$this->putFloat($this->tps);
		$this->putFloat($this->load);
		$this->putLong($this->upTime);
	}

	public function decode() {
		$this->tps = $this->getFloat();
		$this->load = $this->getFloat();
		$this->upTime = $this->getLong();
	}
}