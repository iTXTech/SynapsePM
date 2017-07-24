<?php
namespace synapsepm;

use pocketmine\scheduler\PluginTask;


class TickSynapseTask extends PluginTask {
	public function __construct(SynapsePM $owner) {
		parent::__construct($owner);
	}

	public function onRun(int $currentTick) {
		/** @var SynapsePM $owner */
		$owner = $this->getOwner();

		foreach ($owner->getSynapses() as $synapse) {
			try {
				$synapse->tick();
			} catch (\Throwable $e) {
				$owner->getLogger()->emergency('Failed to tick synapse:');
				$owner->getLogger()->logException($e, $e->getTrace());
				$owner->getLogger()->emergency($e->getTraceAsString());
			}
		}
	}
}