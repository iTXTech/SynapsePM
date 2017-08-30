<?php
declare(strict_types=1);
namespace synapsepm\event\player;

use synapsepm\event\Event;
use synapsepm\Player;


abstract class PlayerEvent extends Event {
	/** @var Player */
	protected $player;

	public function getPlayer() : Player {
		return $this->player;
	}
}