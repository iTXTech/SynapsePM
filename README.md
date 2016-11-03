# SynapsePM
Synapse client for PocketMine like server software. Supports multiple connections.

# SPP
Synapse Proxy Version: `7`

# Config
 - `enabled` - if false, plugin and all other options will be disabled;
 - `disable-rak`  - if true, disables server`s raknet and players will not able to join to server not thought proxy;
 - `synapses` - list of synapse server to connect:
   - `enabled` - if false, current synapse client will be disabled;
   - `server-ip` - ip of synapse server;
   - `server-port` - port of synapse server;
   - `is-main-server` - if true, players will connect after to current server joining to synapse server;
   - `server-password` - password of synapse server;
   - `description` - name of current synapse client.

# API
If you want to get synapse for given player use `synapse\Player::getSynapse()`:
```
$synapse = $player->getSynapse();
```

Also you can get list of all synapse clients using `synapsepm\SynapsePM::getSynapses()`:
```
$synapses = $this->getServer()->getPlugin('SynapsePM')->getSynapses();
```