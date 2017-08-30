<?php
declare(strict_types=1);
namespace synapsepm\network\protocol\spp;

class Info {
	const CURRENT_PROTOCOL = 8;
	const PROTOCOL_MAGIC = 0xbabe;

	const HEARTBEAT_PACKET = 0x01;
	const CONNECT_PACKET = 0x02;
	const DISCONNECT_PACKET = 0x03;
	const REDIRECT_PACKET = 0x04;
	const PLAYER_LOGIN_PACKET = 0x05;
	const PLAYER_LOGOUT_PACKET = 0x06;
	const INFORMATION_PACKET = 0x07;
	const TRANSFER_PACKET = 0x08;
	const BROADCAST_PACKET = 0x09;
	const FAST_PLAYER_LIST_PACKET = 0x0a;
}
