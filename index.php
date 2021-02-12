<?php

use Ratchet\Server\IoServer;
use Yukoriko\Server;

require './vendor/autoload.php';


$privateKey = file_get_contents('./rsa.pem');

$server = IoServer::factory(
    new Server($privateKey),
    7171
);

$server->run();