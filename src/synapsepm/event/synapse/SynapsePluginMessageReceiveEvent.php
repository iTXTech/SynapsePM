<?php
declare(strict_types=1);
namespace synapsepm\event\synapse;

use synapsepm\Synapse;


class SynapsePluginMessageReceiveEvent extends SynapseEvent {
	public static $handlerList = null;

	/** @var string */
	protected $message;

	/**
	 * SynapsePluginMessageReceiveEvent constructor.
	 *
	 * @param Synapse $synapse
	 * @param string  $message
	 */
	public function __construct(Synapse $synapse, string $message) {
		$this->synapse = $synapse;
		$this->message = $message;
	}

	public function getMessage() : string {
		return $this->message;
	}
}