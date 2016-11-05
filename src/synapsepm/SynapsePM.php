<?php
namespace synapsepm;

use pocketmine\network\RakLibInterface;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\TaskHandler;
use synapse\Synapse;


class SynapsePM extends PluginBase
{
	/** @var Synapse[] */
	private $synapses = [  ];
	
	/** @var TaskHandler|null */
	private $tickTask = null;
	
	
	public function onEnable()
	{
		$useOldGenisysConfig = false;
		
		if (defined('pocketmine\\GENISYS_API_VERSION'))
	    {
	        $configVersion = $this->getServer()->getAdvancedProperty('config.version', 0);
		    
		    if (($configVersion >= 14) && ($configVersion < 22))
		    {
		    	$useOldGenisysConfig = true;
		    }
	    }
		
		if ($useOldGenisysConfig)
		{
			$this->getConfig()->set('enabled', $this->getServer()->getAdvancedProperty('synapse.enabled', false));
			$this->getConfig()->set('disable-rak', $this->getServer()->getAdvancedProperty('synapse.disable-rak', false));
			$this->getConfig()->set('synapses', [[
				'enabled' => $this->getServer()->getAdvancedProperty('synapse.enabled', false),
				'server-ip' => $this->getServer()->getAdvancedProperty('synapse.server-ip', '127.0.0.1'),
				'server-port' => $this->getServer()->getAdvancedProperty('synapse.server-port', 10305),
				'is-main-server' => $this->getServer()->getAdvancedProperty('synapse.is-main-server', true),
				'server-password' => $this->getServer()->getAdvancedProperty('synapse.server-password', '123456'),
				'description' => $this->getServer()->getAdvancedProperty('synapse.description', 'A Synapse client')
			]]);
			
			$this->getLogger()->warning('Using old config. Please, update your Genisys and Genisys config.');
		}
	
		$this->saveDefaultConfig();
		$this->reloadConfig();
		
		if (!$this->getConfig()->get('enabled'))
		{
			$this->setEnabled(false);
			
			return;
		}
		
		if ($this->getConfig()->get('disable-rak'))
		{
			foreach ($this->getServer()->getNetwork()->getInterfaces() as $interface)
			{
				if ($interface instanceof RakLibInterface)
				{
					$interface->shutdown();
					break;
				}
			}
		}
		
		foreach ($this->getConfig()->get('synapses') as $synapseConfig)
		{
			if ($synapseConfig['enabled'])
			{
				$this->synapses []= new Synapse($this->getServer(), $synapseConfig);
			}
		}
		
		$this->tickTask = $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickSynapseTask($this), 1);
	}
	
	public function onDisable()
	{
		if ($this->tickTask !== null)
		{
			$this->tickTask->cancel();
			
			foreach ($this->synapses as $synapse)
			{
				$synapse->shutdown();
			}
			
			$this->tickTask = null;
		}
	}
	
	/**
	 * Returns first enabled synapse
	 * @return Synapse|null
	 */
	public function getSynapse()
	{
		return $this->synapses[0] ?? null;
	}
	
	/**
	 * Returns array of all enabled synapses
	 * @return Synapse[]
	 */
	public function getSynapses() : array
	{
		return $this->synapses;
	}
}