<?php

namespace Yukoriko\Protocol;

use Yukoriko\BinaryReader;
use Yukoriko\Network\Client;

/**
 *  Abstrakcyjna klasa opisująca protokół gry
 *
 * @package Yukoriko\protocol
 */
abstract class AbstractProtocol
{
    const PROTOCOL_GAME = 0x0A;
    const PROTOCOL_LOGIN = 0x01;

    static public function make(string $msg): AbstractProtocol
    {
        $buffer = new BinaryReader($msg);
        $buffer->skip(2); # we are skipped message length

        $protocol = $buffer->readUnsignedByte(); #

        switch($protocol) {
            case self::PROTOCOL_GAME: return new GameProtocol();
            case self::PROTOCOL_LOGIN: return new LoginProtocol();
            default:
                throw new \Exception('Protocol ' . $protocol . '[0x' . dechex($protocol) . '] is unknown');
        }
    }

    /**
     *  Przeparsowanie pierwszej wiadomości
     *
     * @param Client $client
     * @param string $message
     */
    abstract public function parseFirstMessage(Client $client, string $message);

    /**
     *  Przeparsowanie standardowej wiadomości
     *
     * @param Client $client
     * @param string $message
     */
    abstract public function parseMessage(Client $client, string $message);
}