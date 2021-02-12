<?php

namespace Yukoriko\Network;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Yukoriko\BinaryReader;
use Yukoriko\message\DisconnectMessage;
use Yukoriko\OutputMessage;
use Yukoriko\protocol\AbstractProtocol;
use Yukoriko\XTEA;

/**
 *  Reprezentacja klienta
 *
 * @package Yukoriko\network
 */
class Client
{
    /**
     * @var ConnectionInterface
     */
    private $connection;

    /**
     *  Klucze do szyfrowania xtea
     *
     * @var array|null
     */
    private $xtea = null;

    /**
     * @var AbstractProtocol
     */
    private $protocol;

    public $privateKey;

    /**
     * @var MessageComponentInterface
     */
    private $server;

    /**
     *  Konstruktor
     *
     * @param MessageComponentInterface $server
     * @param ConnectionInterface $connection
     */
    public function __construct(MessageComponentInterface $server, ConnectionInterface $connection, $privateKey)
    {
        $this->server = $server;

        $this->connection = $connection;
        $this->privateKey = $privateKey;
    }

    /**
     *  Czy klient nadesłał już wiadomość
     *
     * @return bool
     */
    public function isFirstMessage(): bool
    {
        return $this->protocol === null;
    }

    /**
     * @return AbstractProtocol
     */
    public function getProtocol(): AbstractProtocol
    {
        return $this->protocol;
    }

    /**
     * @param AbstractProtocol $protocol
     */
    public function setProtocol(AbstractProtocol $protocol): void
    {
        $this->protocol = $protocol;
    }

    public function setXTEA(array $xtea)
    {
        $this->xtea = $xtea;
    }

    public function parseFirstMessage(string $message)
    {
        $buffer = new BinaryReader($msg);

        $messageLength = $buffer->readUnsignedShort(); #
        $protocol = $buffer->readUnsignedByte(); #
        $os = $buffer->readUnsignedShort(); # Client OS (?)

//        $protocolVersion = $buffer->readUnsignedInteger();


        var_dump('Message length: ' . $messageLength);
        var_dump('Protocol: ' . $protocol);
        var_dump('OS: ' . $os); // 1 - Linux, 2 - Windows, 3 - Flash, 10 - OT Client linux, 11 - OT Client windows, 12 - OT Client Mac

        if( $protocol == 10 ) {

            $clientVersion = $buffer->readUnsignedShort();
//            $xx = $buffer->readUnsignedInteger();
//            $clientType = $buffer->readUnsignedByte();
//            $datRevision = $buffer->readUnsignedShort();

            var_dump('Client version: ' . $clientVersion);
//            var_dump('XX: ' . $xx);
//            var_dump('Client type: ' . $clientType);
//            var_dump('Dat revision: ' . $datRevision);
        } else {
            $clientVersion = $buffer->readUnsignedShort();

            $signatureOfDat = $buffer->readUnsignedInteger();
            $signatureOfSpr = $buffer->readUnsignedInteger();
            $signatureOfPic = $buffer->readUnsignedInteger();

            //        $buffer->skip(1);

            //        var_dump('Protocol version: ' . $protocolVersion);
            var_dump('Client version: ' . $clientVersion);
            var_dump('Signature of dat: ' . dechex($signatureOfDat));
            var_dump('Signature of spr: ' . dechex($signatureOfSpr));
            var_dump('Signature of pic: ' . dechex($signatureOfPic));
        }
//        $buffer->skip(1); // Unknown

        // ???
        $all = $buffer->rsa();

        $rsaDecrypt = openssl_private_decrypt($all, $decrypted, $this->privateKey, OPENSSL_NO_PADDING)."\n";

        if( $rsaDecrypt == false ) {
            echo 'RSA Error: ' . openssl_error_string();
            die();
        }

        $decrypted = new BinaryReader($decrypted, true);

        $rsaCheck = $decrypted->readUnsignedByte();

        if( $rsaCheck !== 0 ) {
            echo 'RSA invalid check bit';
            die();
        }

        $this->xtea = [
            $decrypted->readUnsignedInteger(),
            $decrypted->readUnsignedInteger(),
            $decrypted->readUnsignedInteger(),
            $decrypted->readUnsignedInteger(),
        ];

        if( $protocol == 10 ) {
            $gamemasterFlag = $decrypted->readUnsignedByte();

            $accountName = $decrypted->readUnsignedInteger();
            $characterName = $decrypted->readString();
            $accountPassword = $decrypted->readString();

            var_dump('Gamemaster Flag: ' . $gamemasterFlag);
            var_dump('AccountName: ' . $accountName . ', Hex: ' . dechex($accountName));
            var_dump('AccountPassword: ' . $accountPassword);
            var_dump('CharacterName: ' . $characterName);
////
//            $outputMessage = new OutputMessage($xtea);
//            $outputMessage->addByte(0x1F);
//            $outputMessage->addInteger(time());
//            $outputMessage->addByte(0x01);
//            $outputMessage->send($from);


            $outputMessage = new OutputMessage($xtea);
            $outputMessage->addByte(0x0A);
            $outputMessage->addInteger(0x01); // Player ID
            $outputMessage->addByte(0x32); // ?
            $outputMessage->addByte(0x00); // ?


            $outputMessage->addByte(0x00); // Can reports bugs
//            $outputMessage->addByte(0x00); // Can set pvp
//            $outputMessage->addByte(0x00); // Can set expert mode
//
//            $outputMessage->addShort(0x00);
//            $outputMessage->addShort(0x25);
//
            $outputMessage->send($from);

//            $outputMessage = new OutputMessage($xtea);
//            $outputMessage->addByte(0x0A);
//            $outputMessage->send($from);


//            $outputMessage = new OutputMessage($xtea);
//            $outputMessage->addByte(0x16);
//            $outputMessage->addString('u are waiting');
//            $outputMessage->addByte(0x20);
//            $outputMessage->send($from);
//
//            $from->close();
            return;
        }

        $accountName = $decrypted->readUnsignedInteger();
        $accountPassword = $decrypted->readString();

        var_dump('RSA: ' . $rsaCheck);
        var_dump('XTea', $xtea);
        var_dump('AccountName: ' . $accountName . ', Hex: ' . dechex($accountName));
        var_dump('AccountPassword: ' . $accountPassword);

        if( empty($accountName) ) {
            return $this->disconnect($from, $xtea, 'Account name must be not empty.');
        }

        if( empty($accountPassword) ) {
            return $this->disconnect($from, $xtea, 'Account password must be not empty.');
        }

        # send char list
        $outputMessage = new OutputMessage($xtea);
        $outputMessage->addByte(0x14); # Command for message of day
        $outputMessage->addString("1\nTestowa wiadomosc");

        $outputMessage->addByte(0x64); # Command for char list
//        $outputMessage->addByte(0x01); # World numbers

//        $outputMessage->addByte(0x00); # World ID
//        $outputMessage->addString('Azaroth');
//        $outputMessage->addString('127.0.0.1');
//        $outputMessage->addString('7172');
//        $outputMessage->addByte(0x00);

        $chars = [
            ['name' => 'Kurasz', 'serverName' => 'Rl World', 'ip' => '127.0.0.1', 'port' => 7171],
            ['name' => 'YourFrog', 'serverName' => 'Azaroth', 'ip' => '127.0.0.1', 'port' => 7171],
        ];

        $cc = count($chars);
        $outputMessage->addByte($cc); # Chars on list

        for($i = 0; $i < $cc; $i++) {
            $char = $chars[$i];

            var_dump('IP: ' . $char['ip']);

            $outputMessage->addString($char['name']);
            $outputMessage->addString($char['serverName']);
            $outputMessage->addIP($char['ip']);
            $outputMessage->addShort($char['port']);
        }

        $outputMessage->addShort(0x190); # premium days
        $outputMessage->send($from);

        $this->onClose($from);
    }

    public function parseMessage(string $message)
    {
    }


    /**
     *  Rozłączenie klienta z przesłaniem wiadomości o powodzie
     *
     * @param string $message
     */
    public function disconnect(string $message)
    {
        $output = new DisconnectMessage($this->xtea, $message);
        $output->send($this->connection);

        $this->server->onClose($this->connection);
    }

    public function makeOutputMessage(): OutputMessage
    {
        return new OutputMessage($this->xtea);
    }

    public function send(OutputMessage $message)
    {
        $message->send($this->connection);
    }

    public function close() {
        $this->server->onClose($this->connection);
    }

    public function decryptXTEA(BinaryReader $buffer): BinaryReader {
        $messageLength = $buffer->readUnsignedShort();
        $messageEncrypt = $buffer->all($messageLength);

        $decrypt = XTEA::decrypt($messageEncrypt, $this->xtea);

        return new BinaryReader($decrypt);
    }

    /**
     *  Sprawdzenie czy połączenie jest reprezentowane przez klasę
     *
     * @param ConnectionInterface $other
     *
     * @return bool
     */
    public function equalsConnection(ConnectionInterface $other): bool
    {
        return $this->connection === $other;
    }
}