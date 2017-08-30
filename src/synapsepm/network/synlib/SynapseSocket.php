<?php
declare(strict_types=1);
namespace synapsepm\network\synlib;


class SynapseSocket {
	private $socket;
	/** @var \ThreadedLogger */
	private $logger;
	private $interface;
	private $port;

	public function __construct(\ThreadedLogger $logger, $port = 10305, $interface = '127.0.0.1') {
		$this->logger = $logger;
		$this->interface = $interface;
		$this->port = $port;
		$this->connect();
	}

	public function connect() {
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($this->socket === false or !@socket_connect($this->socket, $this->interface, $this->port)) {
			$this->logger->critical('Synapse Client can\'t connect ' . $this->interface . ':' . $this->port);
			$this->logger->error('Socket error: ' . socket_strerror(socket_last_error()));

			return false;
		}
		$this->logger->info('Synapse has connected to ' . $this->interface . ':' . $this->port);
		socket_set_nonblock($this->socket);
		socket_set_option($this->socket, SOL_TCP, TCP_NODELAY, 1);

		return true;
	}

	public function getSocket() {
		return $this->socket;
	}

	public function close() {
		socket_close($this->socket);
	}
}