<?php
declare(strict_types=1);
namespace synapsepm\event\synapse;

use synapsepm\event\Event;
use synapsepm\Synapse;


abstract class SynapseEvent extends Event {
	/** @var Synapse */
	protected $synapse;

	public function getSynapse() : Synapse {
		return $this->synapse;
	}
}