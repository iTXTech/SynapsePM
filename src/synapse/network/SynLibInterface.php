<?php
namespace synapse\network;

use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\SourceInterface;
use pocketmine\Player;
use synapse\network\protocol\spp\RedirectPacket;
use synapse\Synapse;


class SynLibInterface implements SourceInterface {
	private $synapseInterface;
	private $synapse;

	public function __construct(Synapse $synapse, SynapseInterface $interface) {
		$this->synapse = $synapse;
		$this->synapseInterface = $interface;
	}

	public function getSynapse() : Synapse {
		return $this->synapse;
	}

	public function emergencyShutdown() {
	}

	public function setName(string $name) {
	}

	public function process() {
	}

	public function close(Player $player, $reason = "unknown reason") {
	}

	public function putPacket(Player $player, DataPacket $packet, $needACK = false, $immediate = true) {
		$packet->encode();
		$pk = new RedirectPacket();
		$pk->uuid = $player->getUniqueId();
		$pk->direct = $immediate;
		$pk->mcpeBuffer = $packet->buffer;
		$this->synapseInterface->putPacket($pk);
	}

	public function shutdown() {
	}
}