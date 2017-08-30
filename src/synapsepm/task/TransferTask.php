<?php
declare(strict_types=1);
namespace synapsepm\task;

use pocketmine\scheduler\Task;
use synapsepm\Player;


class TransferTask extends Task {
	/** @var Player */
	private $player;
	/** @var string */
	private $hash;

	public function __construct(Player $player, string $hash) {
		$this->player = $player;
		$this->hash = $hash;
	}

	public function onRun(int $currentTick) {
		$this->player->getSynapse()->transfer($this->player, $this->hash);
	}
}