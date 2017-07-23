<?php
namespace synapsepm;

use pocketmine\network\mcpe\RakLibInterface;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\TaskHandler;
use synapse\Synapse;


class SynapsePM extends PluginBase {
	/** @var Synapse[] */
	private $synapses = [];
	/** @var TaskHandler|null */
	private $tickTask = null;
	/** @var bool */
	private static $useLoadingScreen;

	public function onEnable() {
		$this->saveDefaultConfig();
		$this->reloadConfig();

		if (!$this->getConfig()->get('enabled')) {
			$this->setEnabled(false);

			return;
		}

		if ($this->getConfig()->get('disable-rak')) {
			foreach ($this->getServer()->getNetwork()->getInterfaces() as $interface) {
				if ($interface instanceof RakLibInterface) {
					$interface->shutdown();
					break;
				}
			}
		}

		foreach ($this->getConfig()->get('synapses') as $synapseConfig) {
			if ($synapseConfig['enabled']) {
				$this->synapses [] = new Synapse($this->getServer(), $synapseConfig);
			}
		}

		self::$useLoadingScreen = (bool)$this->getConfig()->get('loadingScreen', true);

		$this->tickTask = $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickSynapseTask($this), 1);
	}

	public function onDisable() {
		if ($this->tickTask !== null) {
			$this->tickTask->cancel();

			foreach ($this->synapses as $synapse) {
				$synapse->shutdown();
			}

			$this->tickTask = null;
		}
	}

	/**
	 * Adds synapse to synapses list
	 *
	 * @param Synapse $synapse
	 */
	public function addSynapse(Synapse $synapse) {
		$this->synapses [] = $synapse;
	}

	/**
	 * Returns first enabled synapse
	 * @return Synapse|null
	 */
	public function getSynapse() {
		return $this->synapses[0] ?? null;
	}

	/**
	 * Returns array of all enabled synapses
	 * @return Synapse[]
	 */
	public function getSynapses() : array {
		return $this->synapses;
	}

	/**
	 * @return boolean
	 */
	public static function isUseLoadingScreen() : bool {
		return self::$useLoadingScreen;
	}
}