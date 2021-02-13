<?php

use Ratchet\Server\IoServer;
use Yukoriko\Server;

require './vendor/autoload.php';


$privateKey = file_get_contents('./rsa.pem');

$tibiaServer = new Server($privateKey);
$server = IoServer::factory(
    $tibiaServer,
    7171
);

$tibiaServer->setLoop($server->loop);

$server->run();