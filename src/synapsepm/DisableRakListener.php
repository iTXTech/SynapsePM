<?php
declare(strict_types=1);
namespace synapsepm;

use pocketmine\event\Listener;
use pocketmine\event\server\NetworkInterfaceRegisterEvent;
use pocketmine\network\mcpe\RakLibInterface;


class DisableRakListener implements Listener {
	/**
	 * @param NetworkInterfaceRegisterEvent $event
	 * @ignoreCancelled true
	 */
	public function onNetworkInterfaceRegister(NetworkInterfaceRegisterEvent $event) {
		if ($event->getInterface() instanceof RakLibInterface) {
			$event->setCancelled();
		}
	}
}