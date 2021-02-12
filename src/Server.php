<?php

namespace Yukoriko;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Yukoriko\message\DisconnectMessage;
use Yukoriko\Network\Client;
use Yukoriko\Protocol\AbstractProtocol;

class Server implements MessageComponentInterface {

    /**
     * @var Client[]
     */
    private $clients;

    private $privateKey;

    public function __construct(string $privateKey)
    {
        $this->privateKey = $privateKey;
        $this->clients = [];
    }


    public function onOpen(ConnectionInterface $conn) {
        $this->clients[$conn->resourceId] = new Client($this, $conn, $this->privateKey);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $client = $this->getClientByConnection($from);


        if( $client->isFirstMessage() ) {
            $protocol = AbstractProtocol::make($msg);
            $protocol->parseFirstMessage($client, $msg);

            $client->setProtocol($protocol);
        } else {
            $client->getProtocol()->parseMessage($client, $msg);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        unset($this->clients[$conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        var_dump('onError: ' . $e->getMessage());
    }

    /**
     *  Wyszukanie instancji klienta na podstawie połączenia
     *
     * @param ConnectionInterface $conn
     *
     * @return Client
     * @throws \Exception
     */
    private function getClientByConnection(ConnectionInterface $conn): Client
    {
        foreach($this->clients as $client) {
            if( $client->equalsConnection($conn) ) {
                return $client;
            }
        }

        throw new \Exception("Client not found");
    }
}

