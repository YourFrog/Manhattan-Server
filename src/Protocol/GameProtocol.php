<?php

namespace Yukoriko\Protocol;

use Yukoriko\BinaryReader;
use Yukoriko\Network\Client;
use Yukoriko\OutputMessage;

/**
 *  Protokół odpowiedzialny za obsługę "Gry"
 *
 * @package Yukoriko\Protocol
 */
class GameProtocol extends AbstractProtocol
{
    const COMMAND_FIGHT_MODE = 0xA0;
    const COMMAND_SET_OUTFIT = 0xD3;

    const SLOT_WHEREEVER = 0x00;
    const SLOT_FIRST = 0x01;
    const SLOT_HEAD = self::SLOT_FIRST;
    const SLOT_NECKLACE = 0x02;
    const SLOT_BACKPACK = 0x03;
    const SLOT_ARMOR = 0x04;
    const SLOT_RIGHT = 0x05;
    const SLOT_LEFT = 0x06;
    const SLOT_LEGS = 0x07;
    const SLOT_FEET = 0x08;
    const SLOT_RING = 0x09;
    const SLOT_AMMO = 0x0A;
    const SLOT_DEPOT = 0x0B;
    const SLOT_LAST = self::SLOT_DEPOT;

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

        var_dump('Message length: ' . $messageLength);
        var_dump('Protocol: ' . $protocol);
        var_dump('OS: ' . $os); // 1 - Linux, 2 - Windows, 3 - Flash, 10 - OT Client linux, 11 - OT Client windows, 12 - OT Client Mac
        var_dump('Client version: ' . $clientVersion);

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


        $outputMessage = $client->makeOutputMessage();
        $outputMessage->addByte(0x0A);
        $outputMessage->addInteger(0x01); // Player ID
        $outputMessage->addByte(0x32); // ?
        $outputMessage->addByte(0x00); // ?

        $outputMessage->addByte(0x00); // Can reports bugs

        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();

        #Map
        $outputMessage->addByte(0x64);
        $outputMessage->addShort(1024); # Start map position
        $outputMessage->addShort(1025);
        $outputMessage->addByte(6);

        $skip = -1;
        for($z = 7; $z != -1; $z += -1) {
            for ($x = 0; $x < 18; $x++) {
                for ($y = 0; $y < 14; $y++) {


                    if( $skip >= 0 ) {
                        $outputMessage->AddByte(0x00);
                        $outputMessage->AddByte(0xFF);
                    }

                    $skip = 0;
                    $outputMessage->addShort(351);
                }
            }
        }

        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0xFF);


        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();

        // Effect
        $outputMessage->addByte(0x83);
        $outputMessage->addShort(1024); # Start map position
        $outputMessage->addShort(1024);
        $outputMessage->addByte(7);
        $outputMessage->addByte(0x0B); # Effect

        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();
        // Inventory
        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_HEAD);

        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_NECKLACE);

        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_BACKPACK);

        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_ARMOR);

        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_RIGHT);

        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_LEFT);

        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_LEGS);

        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_FEET);

        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_RING);

        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_AMMO);

        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();
        # Stats
        $outputMessage->addByte(0xA0);
        $outputMessage->addShort(0x200);    // Health
        $outputMessage->addShort(0x220);    // Health max
        $outputMessage->addShort(0x32);     // Free cap
        $outputMessage->addInteger(0x10);     // Exp
        $outputMessage->addShort(0x01);     // Level
        $outputMessage->addByte(0x32);     // Level percent
        $outputMessage->addShort(0x50);     // Mana
        $outputMessage->addShort(0x60);     // Mana max
        $outputMessage->addByte(0x01);     // mlevel
        $outputMessage->addByte(0x01);     // mlevel percent
        $outputMessage->addByte(0x01);     // soul
        $outputMessage->addShort(0xD20);     // Stamina minutes

        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();
        #skills
        $outputMessage->addByte(0xA1);
        $outputMessage->addByte(0x0A); // fist
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x0A); // club
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x0A); // sword
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x0A); // axe
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x0A); // dist
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x0A); // shield
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x0A); // fish
        $outputMessage->addByte(0x00);

        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();
        # World light
        $outputMessage->addByte(0x82);
        $outputMessage->addByte(0xFF);
        $outputMessage->addByte(0xD7);

        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();
        # Player light
        $outputMessage->addByte(0x8D);
        $outputMessage->addInteger(0x01); // Player ID
        $outputMessage->addByte(0xFF);
        $outputMessage->addByte(0xD7);

        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();
        # Outfits ?
        $outputMessage->addByte(0xC8);
        $outputMessage->addShort(0x4B);   # Gamemaster (ID: 75)
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x00);

        $outputMessage->addByte(0x01);         // Outfit count
        $outputMessage->addShort(0x4B);
        $outputMessage->addString("Gamemaster");
        $outputMessage->addByte(0x00);

        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();
//
        # Message??
        $outputMessage->addByte(0xB4);
        $outputMessage->addByte(0x15);
        $outputMessage->addString("Test");

        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();

        #Icons (? maybe not sending)
        $outputMessage->addByte(0xA2);
        $outputMessage->addShort(0x00);

        $client->send($outputMessage);


        #Ping
        $outputMessage = $client->makeOutputMessage();
        $outputMessage->addByte(0x1E);
        $client->send($outputMessage);
    }

    private function showInputMessage(int $command, BinaryReader $buffer)
    {
        $commands = $this->getConstants();
        foreach($commands as $key => $value) {
            $keyFromConstant = constant('self::' . $key);
            $commands[$keyFromConstant] = $key;
        }

        $commands = array_filter($commands, function($str){
            return strpos($str, 'COMMAND_') === 0;
        });

        $str = array_key_exists($command, $commands) ? $commands[$command] : 'Unknown command';
        $buffer->dump('Show input message: ' . $str);
    }

    private function getConstants() {
        $oClass = new \ReflectionClass(__CLASS__);
        return $oClass->getConstants();
    }

    /**
     * @inheritDoc
     */
    public function parseMessage(Client $client, string $message)
    {
        $buffer = new BinaryReader($message);
        $buffer = $client->decryptXTEA($buffer);

        $packetSize = $buffer->readUnsignedShort();
        $command = $buffer->readUnsignedByte();


        $this->showInputMessage($command, $buffer);

        switch($command) {
            case self::COMMAND_FIGHT_MODE:
                    var_dump('Command of Fight mode!!');
                break;
            case 0x14:
                    // Ignore logout
                    var_dump('Command of logout');
                break;

            case 0x1E:
                    // Ignore ping
                    var_dump('Command of ping');
                break;
            case 0x64:
                // Ignore player auto click
                var_dump('Ignore player auto click');
                break;
            case 0x65:
                    // Ignore player move up
                    var_dump('Ignore player move up');
                break;
            case 0x66:
                // Ignore player move right
                var_dump('Ignore player move right');
                break;
            case 0x67:
                // Ignore player move bottom
                var_dump('Ignore player move bottom');
                break;
            case 0x68:
                // Ignore player move left
                var_dump('Ignore player move left');
                break;
            case 0x6F:
                // Ignore player dance up
                var_dump('Ignore player dance up');
                break;
            case 0x70:
                    // Ignore player dance right
                    var_dump('Ignore player dance right');
                    $output = $client->makeOutputMessage();
                    $output->AddByte(0x6B);
                    $output->addShort(1024); # Start map position
                    $output->addShort(1024);
                    $output->addByte(7);
                    $output->AddByte(0x00);
                    $output->addShort(0x63); /*99*/
                    $output->addInteger(0x01); // Player ID
                    $output->AddByte(0x01); // right

                    $client->send($output);
                break;
            case 0x71:
                    // Ignore player dance bottom
                    var_dump('Ignore player dance bottom');
                break;
            case 0x72:
                    // Ignore player dance left
                    var_dump('Ignore player dance left');
                break;
            case 0x96:
                    // Ignore player message
                    var_dump('Command of player message');
                break;

            case 0xD3:
                    // Ignore set outfit
                    var_dump('Command of Set outfit!!');
                    $output = $client->makeOutputMessage();
                    $output->AddByte(0x8E);
                    $output->addInteger(0x01); // Player ID
                    $output->addShort(0x4B);   # Gamemaster (ID: 75)
                    $output->addByte(0x00);
                    $output->addByte(0x00);
                    $output->addByte(0x00);
                    $output->addByte(0x00);
                    $output->addByte(0x00);
                    $client->send($output);
                break;
            case 0xBE:
                    // Ignore cancel move
                    var_dump('Command of Cancel move!!');
                break;

            default:
                    echo 'Unknown protocol ' . $command . ' [0x' . str_pad(dechex($command), 2, '0', STR_PAD_LEFT) . ']' . PHP_EOL;
                break;
        }
    }
}

class SpeakType
{
    const SPEAK_SAY = 0x01;
    const SPEAK_WHISPER	= 0x02;
    const SPEAK_YELL = 0x03;
    const SPEAK_PRIVATE = 0x04;
    const SPEAK_CHANNEL_Y = 0x05;
    const SPEAK_RVR_CHANNEL	= 0x06;
    const SPEAK_RVR_ANSWER = 0x07;
    const SPEAK_RVR_CONTINUE = 0x08;
    const SPEAK_BROADCAST = 0x09;
    const SPEAK_CHANNEL_R1 = 0x0A; //red - #c text
    const SPEAK_PRIVATE_RED	= 0x0B;	//@name@text
    const SPEAK_CHANNEL_O = 0x0C;
    const SPEAK_UNKNOWN_1 = 0x0D;
    const SPEAK_CHANNEL_R2	= 0x0E;	//red anonymous - #d text
    const SPEAK_UNKNOWN_2 = 0x0F;
    const SPEAK_MONSTER_SAY	= 0x10;
    const SPEAK_MONSTER_YELL = 0x11;
};