<?php
declare(strict_types=1);
namespace synapse\task;

use pocketmine\scheduler\Task;


class CallbackTask extends Task {
	private $callback;

	public function __construct(callable $callback) {
		$this->callback = $callback;
	}

	public function onRun(int $currentTick) {
		call_user_func($this->callback, $currentTick);
	}
}