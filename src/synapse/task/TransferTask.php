<?php
namespace synapse\task;

use pocketmine\scheduler\Task;
use synapse\network\protocol\spp\TransferPacket;
use synapse\Player;


class TransferTask extends Task {
	/** @var Player */
	private $player;
	/** @var string */
	private $hash;

	public function __construct(Player $player, string $hash) {
		$this->player = $player;
		$this->hash = $hash;
	}

	public function onRun($currentTick) {
		$pk = new TransferPacket();
		$pk->uuid = $this->player->getUniqueId();
		$pk->clientHash = $this->hash;
		$this->player->getSynapse()->sendDataPacket($pk);
	}
}