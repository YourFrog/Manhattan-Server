<?php

namespace Yukoriko\Protocol;

use Yukoriko\BinaryReader;
use Yukoriko\Network\Client;
use Yukoriko\OutputMessage;

/**
 *  Protokół do obsługi logowania
 *
 * @package Yukoriko\Protocol
 */
class LoginProtocol extends AbstractProtocol
{
    const COMMAND_CHARACTER_LIST = 0x64;
    const COMMAND_MESSAGE_OF_DAY = 0x14;

    /**
     * @inheritDoc
     */
    public function parseFirstMessage(Client $client, string $message)
    {
        $buffer = new BinaryReader($message);

        $messageLength = $buffer->readUnsignedShort(); #
        $protocol = $buffer->readUnsignedByte(); #
        $os = $buffer->readUnsignedShort(); # Client OS (?)
        $clientVersion = $buffer->readUnsignedShort();
        $signatureOfDat = $buffer->readUnsignedInteger();
        $signatureOfSpr = $buffer->readUnsignedInteger();
        $signatureOfPic = $buffer->readUnsignedInteger();

        var_dump('Message length: ' . $messageLength);
        var_dump('Protocol: ' . $protocol);
        var_dump('OS: ' . $os); // 1 - Linux, 2 - Windows, 3 - Flash, 10 - OT Client linux, 11 - OT Client windows, 12 - OT Client Mac
        var_dump('Client version: ' . $clientVersion);
        var_dump('Signature of dat: ' . dechex($signatureOfDat));
        var_dump('Signature of spr: ' . dechex($signatureOfSpr));
        var_dump('Signature of pic: ' . dechex($signatureOfPic));

        $rsaEncrypt = $buffer->rsa();
        $rsaDecrypt = openssl_private_decrypt($rsaEncrypt, $decrypted, $client->privateKey, OPENSSL_NO_PADDING)."\n";

        if( $rsaDecrypt == false ) {
            echo 'RSA Error: ' . openssl_error_string();
            die();
        }

        $decrypted = new BinaryReader($decrypted, true);

        if( $decrypted->readUnsignedByte() !== 0 ) {
            echo 'RSA invalid check bit';
            die();
        }

        $client->setXTEA([
            $decrypted->readUnsignedInteger(),
            $decrypted->readUnsignedInteger(),
            $decrypted->readUnsignedInteger(),
            $decrypted->readUnsignedInteger(),
        ]);

        $accountName = $decrypted->readUnsignedInteger();
        $accountPassword = $decrypted->readString();

        var_dump('AccountName: ' . $accountName . ', Hex: ' . dechex($accountName));
        var_dump('AccountPassword: ' . $accountPassword);

        if( empty($accountName) ) {
            return $client->disconnect('Account name must be not empty.');
        }

        if( empty($accountPassword) ) {
            return $client->disconnect('Account password must be not empty.');
        }


        # send char list
        $outputMessage = $client->makeOutputMessage();
        $outputMessage->addByte(self::COMMAND_MESSAGE_OF_DAY); # Command for message of day
        $outputMessage->addString("1\nTestowa wiadomosc");

        $outputMessage->addByte(self::COMMAND_CHARACTER_LIST); # Command for char list

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
        $client->send($outputMessage);
        $client->close();
    }

    /**
     * @inheritDoc
     */
    public function parseMessage(Client $client, string $message)
    {
        die('Second message on login protocol :)');
    }
}