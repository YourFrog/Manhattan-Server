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
    const COMMAND_PING = 0x1E;

    const COMMAND_FIGHT_MODE = 0xA0;
    const COMMAND_SET_OUTFIT = 0xD3;
    const COMMAND_REQUEST_OUTFIT_WINDOW =  0xD2;

    # Chat
    const COMMAND_PLAYER_SAY = 0x96;
    const COMMAND_REQUEST_FOR_CHANNELS_IN_DIALOG = 0x97;
    const COMMAND_CREATE_NEW_PRIVATE_CAHNNEL = 0x9a;

    const REQUEST_PING = 0x1E;
    const REQUEST_CHANNELS_IN_DIALOG = 0xAB;
    const REQUEST_CREATED_NEW_CHANNEL = 0xAD;
    const REQUEST_CREATURE_SET_OUTFIT = 0x8E;

    # Movement
    const COMMAND_MOVE_NORTH = 0x65;
    const COMMAND_MOVE_EAST = 0x66;
    const COMMAND_MOVE_SOUTH = 0x67;
    const COMMAND_MOVE_WEST = 0x68;

    const COMMAND_MOVE_NORTH_WEST = 0x6D;
    const COMMAND_MOVE_NORTH_EAST = 0x6A;
    const COMMAND_MOVE_SOUTH_WEST = 0x6C;
    const COMMAND_MOVE_SOUTH_EAST = 0x6B;

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

    private function addMap(OutputMessage $outputMessage) {
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
    }

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

        $this->addMap($outputMessage);


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
        $outputMessage->addByte(0x0B); // fist
        $outputMessage->addByte(0x02);
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
        if( $command == self::COMMAND_PING ) {
            # Not showing pings
            return;
        }

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

            case self::COMMAND_PING:
                    $this->loop->addTimer(5, function() use ($client) {
                        $output = $client->makeOutputMessage();
                        $output->addByte(self::REQUEST_PING);

                        $client->send($output);

                        # Test cancel message
                        $output = $client->makeOutputMessage();
                        $output->addByte(0xB4);
                        $output->addByte(MessageType::MSG_INFO_DESCR);
                        $output->addString("Cancel something");

                        $client->send($output);
                    });
                break;
            case 0x64:
                // Ignore player auto click
                var_dump('Ignore player auto click');
                break;
            case self::COMMAND_MOVE_NORTH:
                $output = $client->makeOutputMessage();

                $oldPosition = [
                    'x' => 1024,
                    'y' => 1025,
                    'z' => 7
                ];

                $newPosition = [
                    'x' => 1024,
                    'y' => 1024,
                    'z' => 7
                ];

                $this->sendMove($output, array_values($oldPosition), array_values($newPosition), 109);

                $client->send($output);
                break;
            case self::COMMAND_MOVE_EAST:
                $output = $client->makeOutputMessage();

                $oldPosition = [
                    'x' => 1024,
                    'y' => 1024,
                    'z' => 7
                ];

                $newPosition = [
                    'x' => 1023,
                    'y' => 1024,
                    'z' => 7
                ];

                $this->sendMove($output, array_values($oldPosition), array_values($newPosition), 109);

                $client->send($output);
                break;
            case self::COMMAND_MOVE_SOUTH:
                $output = $client->makeOutputMessage();

                $oldPosition = [
                    'x' => 1024,
                    'y' => 1024,
                    'z' => 7
                ];

                $newPosition = [
                    'x' => 1024,
                    'y' => 1025,
                    'z' => 7
                ];

                $this->sendMove($output, array_values($oldPosition), array_values($newPosition), 109);

                $client->send($output);
                break;
            case self::COMMAND_MOVE_WEST:
                $output = $client->makeOutputMessage();

                $oldPosition = [
                    'x' => 1024,
                    'y' => 1024,
                    'z' => 7
                ];

                $newPosition = [
                    'x' => 1025,
                    'y' => 1024,
                    'z' => 7
                ];

                $this->sendMove($output, array_values($oldPosition), array_values($newPosition), 108);

                $client->send($output);
                break;

            case self::COMMAND_MOVE_SOUTH_EAST:
                $output = $client->makeOutputMessage();

                $oldPosition = [
                    'x' => 1024,
                    'y' => 1024,
                    'z' => 7
                ];

                $newPosition = [
                    'x' => 1023,
                    'y' => 1025,
                    'z' => 7
                ];

                $this->sendMove($output, array_values($oldPosition), array_values($newPosition), 351);

                $client->send($output);
                break;
            case self::COMMAND_MOVE_SOUTH_WEST:
                $output = $client->makeOutputMessage();

                $oldPosition = [
                    'x' => 1024,
                    'y' => 1024,
                    'z' => 7
                ];

                $newPosition = [
                    'x' => 1025,
                    'y' => 1025,
                    'z' => 7
                ];

                $this->sendMove($output, array_values($oldPosition), array_values($newPosition), 110);

                $client->send($output);
                break;
            case self::COMMAND_MOVE_NORTH_EAST:
                $output = $client->makeOutputMessage();

                $oldPosition = [
                    'x' => 1024,
                    'y' => 1024,
                    'z' => 7
                ];

                $newPosition = [
                    'x' => 1023,
                    'y' => 1023,
                    'z' => 7
                ];

                $this->sendMove($output, array_values($oldPosition), array_values($newPosition), 110);

                $client->send($output);
                break;

            case self::COMMAND_MOVE_NORTH_WEST:
                $output = $client->makeOutputMessage();

                $oldPosition = [
                    'x' => 1024,
                    'y' => 1024,
                    'z' => 7
                ];

                $newPosition = [
                    'x' => 1025,
                    'y' => 1023,
                    'z' => 7
                ];

                $this->sendMove($output, array_values($oldPosition), array_values($newPosition), 110);

                $client->send($output);
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

            case self::COMMAND_REQUEST_OUTFIT_WINDOW:
                    $outputMessage = $client->makeOutputMessage();
                    # Outfits ?
                    $outputMessage->addByte(0xC8);
                    $outputMessage->addShort(0x4B);   # Gamemaster (ID: 75)
                    $outputMessage->addByte(0x00);
                    $outputMessage->addByte(0x00);
                    $outputMessage->addByte(0x00);
                    $outputMessage->addByte(0x00);
                    $outputMessage->addByte(0x00);

                    $outfits = [
                        ['id' => 0x4B, 'name' => 'Game master'],
                        ['id' => 0x81, 'name' => 'Hunter'],
                        ['id' => 0x80, 'name' => 'Mage']
                    ];

                    $cc = 0x120;
                    $outputMessage->addByte($cc);         // Outfit count
                    for($i = 0; $i < $cc; $i++) {
                        $outputMessage->addShort(0x98 - $i);
                        $outputMessage->addString("Gamemaster " . dechex(0x98 - $i));
                        $outputMessage->addByte(0x00);
                    }

                    $client->send($outputMessage);
                break;

            case self::COMMAND_SET_OUTFIT:
                    // Ignore set outfit
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


                    $output = $client->makeOutputMessage();
                    $output->AddByte(0x6A);
                    $output->addShort(1024); # Start map position
                    $output->addShort(1024);
                    $output->addByte(0x6);

                    $output->addShort(0x61);
                    $output->addInteger(0x00);
                    $output->addInteger(0x01);
                    $output->addString("Yukoriko");
                $output->addByte(0x32); // HP Bar in percent
                $output->addByte(0x01); // Look direction
                $output->addShort(0x4B);   # Gamemaster (ID: 75)
                $output->addByte(0x01);
                $output->addByte(0x01);
                $output->addByte(0x01);
                $output->addByte(0x01);
                $output->addByte(0x01);

                $output->addByte(0xFF); // Light
                $output->addByte(0xFF);

                $output->addShort(0x200);

                $output->addByte(0x00); // Skull
                $output->addByte(0x00); // Party shield
            // Outfit??

                $client->send($output);

                break;
            case 0xBE:
                    // Ignore cancel move
                    var_dump('Command of Cancel move!!');
                break;

            case self::COMMAND_REQUEST_FOR_CHANNELS_IN_DIALOG:
                    $channels = [
                        ['id' => 1, 'name' => 'Radio ZET'],
                        ['id' => 2, 'name' => 'Polish unnamed'],
                    ];

                    $cc = count($channels);

                    $output = $client->makeOutputMessage();
                    $output->AddByte(self::REQUEST_CHANNELS_IN_DIALOG);
                    $output->addByte($cc);

                    for($i = 0; $i < $cc; $i++) {
                        $channel = $channels[$i];

                        $output->addShort($channel['id']);
                        $output->addString($channel['name']);
                    }
                    $client->send($output);
                break;

            case self::COMMAND_CREATE_NEW_PRIVATE_CAHNNEL:
                    $channelName = $buffer->readString();

                    $output = $client->makeOutputMessage();
                    $output->AddByte(self::REQUEST_CREATED_NEW_CHANNEL);
                    $output->addString($channelName . ' channel');
                    $client->send($output);
                break;

            default:
                    echo 'Unknown protocol ' . $command . ' [0x' . str_pad(dechex($command), 2, '0', STR_PAD_LEFT) . ']' . PHP_EOL;
                break;
        }
    }

    private function sendMove(OutputMessage $msg, array $oldPosition, array $newPosition, int $floorId = 351): OutputMessage
    {
        $msg->AddByte(0x6D);

        [$oldX, $oldY, $oldZ] = $oldPosition;
        [$newX, $newY, $newZ] = $newPosition;

        # Old position
        $msg->addShort($oldX); # Start map position
        $msg->addShort($oldY);
        $msg->addByte($oldZ);

        # Stack
        $msg->AddByte(0x05);

        # New position
        $msg->addShort($newX); # Start map position
        $msg->addShort($newY);
        $msg->addByte($newZ);


        if($oldY > $newY) {
            $msg->addByte(0x65);

            $this->GetMapDescription($oldX - 8, $newY - 6, $newZ, 18, 1, $msg, $floorId);
        } elseif ($oldY < $newY) {
            $msg->addByte(0x67);

            $this->GetMapDescription($oldX - 8, $newY + 7, $newZ, 18, 1, $msg, $floorId);
        }

        if($oldX > $newX) {
            $msg->addByte(0x66);

            $this->GetMapDescription($oldX + 9, $newY - 6, $newZ, 1, 14, $msg, $floorId);
        } elseif ($oldX < $newX) {
            $msg->addByte(0x68);

            $this->GetMapDescription($oldX - 8, $newY - 6, $newZ, 1, 14, $msg, $floorId);
        }

        return $msg;
    }

    private function GetMapDescription(int $x, int $y, int $z, int $width, int $height, OutputMessage $msg, int $floorId = 351)
    {
        $skip = -1;

        $startz = 7;
        $endz = 0;
        $zstep = -1;

        if($z > 7) {
            $startz = $z - 2;
            $endz = min(15, $z + 2);
            $zstep = 1;
        }

        for($nz = $startz; $nz != $endz + $zstep; $nz += $zstep) {
            $this->GetFloorDescription($msg, $x, $y, $nz, $width, $height, $z - $nz, $skip, $floorId);
        }

        if($skip >= 0) {
            $msg->addByte($skip);
            $msg->addByte(0xFF);
        }

    }

    private function GetFloorDescription(OutputMessage $msg, int $x, int $y, int $z, int $width, int $height, int $offset, int &$skip, int $floorId = 351)
    {
        for($nx = 0; $nx < $width; $nx++) {
            for($ny = 0; $ny < $height; $ny++) {
                if($z == 7) { // Symulacja, że pod i nad mapą nie ma nic
                    if($skip >= 0) {
                        $msg->addByte($skip);
                        $msg->addByte(0xFF);
                    }
                    $skip = 0;

                    $this->GetTileDescription($msg, $floorId);
                } else {
                    $skip ++;
                    if($skip == 0xFF) {
                        $msg->addByte(0xFF);
                        $msg->addByte(0xFF);
                        $skip -= 1;
                    }
                }
            }
        }
    }

    private function GetTileDescription(OutputMessage $msg, int $floorId = 351)
    {
        $msg->addShort($floorId);
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

class MessageType
{
    const MSG_CLASS_FIRST			= 0x01;
    const MSG_STATUS_CONSOLE_YELLOW	= self::MSG_CLASS_FIRST; /*Yellow message in the console*/
    const MSG_STATUS_CONSOLE_LBLUE	= 0x04; /*Lightblue message in the console*/
    const MSG_STATUS_CONSOLE_ORANGE	= 0x11; /*Orange message in the console*/
    const MSG_STATUS_WARNING		= 0x12; /*Red message in game window and in the console*/
    const MSG_EVENT_ADVANCE		= 0x13; /*White message in game window and in the console*/
    const MSG_EVENT_DEFAULT		= 0x14; /*White message at the bottom of the game window and in the console*/
    const MSG_STATUS_DEFAULT		= 0x15; /*White message at the bottom of the game window and in the console*/
    const MSG_INFO_DESCR			= 0x16; /*Green message in game window and in the console*/
    const MSG_STATUS_SMALL		= 0x17; /*White message at the bottom of the game window"*/
    const MSG_STATUS_CONSOLE_BLUE		= 0x18; /*Blue message in the console*/
    const MSG_STATUS_CONSOLE_RED		= 0x19; /*Red message in the console*/
    const MSG_CLASS_LAST			= self::MSG_STATUS_CONSOLE_RED;
};

class Directions {
    const NORTH = 0;
    const EAST = 1;
    const SOUTH = 2;
    const WEST = 3;
    const SOUTHWEST = 4;
    const SOUTHEAST = 5;
    const NORTHWEST = 6;
    const NORTHEAST = 7;
};