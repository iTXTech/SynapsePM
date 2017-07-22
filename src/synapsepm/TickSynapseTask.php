<?php
namespace synapsepm;

use pocketmine\scheduler\PluginTask;


class TickSynapseTask extends PluginTask {
	public function __construct(SynapsePM $owner) {
		parent::__construct($owner);
	}

	public function onRun($currentTick) {
		/** @var SynapsePM $owner */
		$owner = $this->getOwner();

		foreach ($owner->getSynapses() as $synapse) {
			$synapse->tick();
		}
	}
}