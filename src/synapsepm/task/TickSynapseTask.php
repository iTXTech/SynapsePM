<?php
declare(strict_types=1);
namespace synapsepm\task;

use pocketmine\scheduler\PluginTask;
use synapsepm\Synapse;
use synapsepm\SynapsePM;


class TickSynapseTask extends PluginTask {
	/** @var Synapse */
	private $synapse;

	public function __construct(SynapsePM $owner, Synapse $synapse) {
		parent::__construct($owner);
		$this->synapse = $synapse;
	}

	public function onRun(int $currentTick) {
		$this->synapse->tick();
	}
}