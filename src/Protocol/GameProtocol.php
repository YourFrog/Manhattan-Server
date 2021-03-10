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

    const COMMAND_USE_ITEM = 0x82;

    # Chat
    const COMMAND_PLAYER_SAY = 0x96;
    const COMMAND_REQUEST_FOR_CHANNELS_IN_DIALOG = 0x97;
    const COMMAND_CREATE_NEW_PRIVATE_CAHNNEL = 0x9a;

    # Quest log
    const COMMAND_OPEN_QUEST_LOG = 0xF0;
    const COMMAND_SHOW_MISSION_DESCRIPTION = 0xF1;

    const COMMAND_ADD_VIP = 0xDC;
    const COMMAND_REMOVE_VIP = 0xDD;

    const REQUEST_PING = 0x1E;
    const REQUEST_CHANNELS_IN_DIALOG = 0xAB;
    const REQUEST_CREATED_NEW_CHANNEL = 0xAD;
    const REQUEST_CREATURE_SET_OUTFIT = 0x8E;
    const REQUEST_MESSAGE = 0xB4;

    const REQUEST_QUEST_LOG = 0xF0;
    const REQUEST_MISSION_DESCRIPTION = 0xF1;

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

    const ITEM_BACKPACK = 2854;

    # Movement
    const COMMAND_MOVE_NORTH = 0x65;
    const COMMAND_MOVE_EAST = 0x66;
    const COMMAND_MOVE_SOUTH = 0x67;
    const COMMAND_MOVE_WEST = 0x68;

    const COMMAND_MOVE_NORTH_WEST = 0x6D;
    const COMMAND_MOVE_NORTH_EAST = 0x6A;
    const COMMAND_MOVE_SOUTH_WEST = 0x6C;
    const COMMAND_MOVE_SOUTH_EAST = 0x6B;

    #Outfits
    const OUTFIT_GAMEMASTER = 0x4B;
    const OUTFIT_MALE_HUNTER = 0x81;
    const OUTFIT_MALE_MAGE = 0x80;

    /** @var Position */
    private $playerPosition;

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
        $this->playerPosition = new Position(1024, 1024, 7, 1);
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
        $outputMessage->addShort($this->playerPosition->x); # Start map position
        $outputMessage->addShort($this->playerPosition->y);
        $outputMessage->addByte($this->playerPosition->z);

        $this->addMap($outputMessage);


        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();

        // Effect
        $outputMessage->addByte(0x83);
        $outputMessage->addShort($this->playerPosition->x); # Start map position
        $outputMessage->addShort($this->playerPosition->y);
        $outputMessage->addByte($this->playerPosition->z);
        $outputMessage->addByte(0x0B); # Effect

        $client->send($outputMessage);
        $outputMessage = $client->makeOutputMessage();
        // Inventory
        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_HEAD);

        $outputMessage->addByte(0x79);
        $outputMessage->addByte(self::SLOT_NECKLACE);

        $outputMessage->addByte(0x78);
        $outputMessage->addByte(self::SLOT_BACKPACK);
        $outputMessage->addShort(2854); // Item ID, backpack

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

        # VIP list
        $outputMessage->addByte(0xD2);
        $outputMessage->addInteger(100); // To jest Guid - chyba id gracza?
        $outputMessage->addString('Test Offline');
        $outputMessage->addByte(0);

        $outputMessage->addByte(0xD2);
        $outputMessage->addInteger(200); // To jest Guid - chyba id gracza?
        $outputMessage->addString('Test Online');
        $outputMessage->addByte(1);

        $client->send($outputMessage);

        $this->sendOutfitWindow($client);
        $this->sendCancelMessage($client, 'Test');


        $outputMessage = $client->makeOutputMessage();

        #Icons (? maybe not sending)
        $outputMessage->addByte(0xA2);
        $outputMessage->addShort(0x00);

        $client->send($outputMessage);

        $this->sendSelfOnBattleList($client, false, $this->playerPosition);


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

                        echo 'ping? ';
                        $client->send($output);

                        $this->sendLookMessage($client, "Send ping :)");
                    });
                break;
            case 0x64:
                // Ignore player auto click
                var_dump('Ignore player auto click');
                break;
            case self::COMMAND_MOVE_NORTH:
                $output = $client->makeOutputMessage();

                $oldPosition = clone $this->playerPosition; // Clone bo inaczej pracuje na jednej instancji
                $this->playerPosition->goNorth();

                $this->sendMove($output, $oldPosition, $this->playerPosition, 351);

                $client->send($output);
                $this->sendSelfOnBattleList($client, true, $this->playerPosition, Directions::NORTH);
                break;
            case self::COMMAND_MOVE_EAST:
                $output = $client->makeOutputMessage();

                $oldPosition = clone $this->playerPosition; // Clone bo inaczej pracuje na jednej instancji
                $this->playerPosition->goEast();

                $this->sendMove($output, $oldPosition, $this->playerPosition, 351);

                $client->send($output);
                $this->sendSelfOnBattleList($client, true, $this->playerPosition, Directions::EAST);
                break;
            case self::COMMAND_MOVE_SOUTH:
                $output = $client->makeOutputMessage();

                $oldPosition = clone $this->playerPosition; // Clone bo inaczej pracuje na jednej instancji
                $this->playerPosition->goSouth();

                $this->sendMove($output, $oldPosition, $this->playerPosition, 351);

                $client->send($output);
                $this->sendSelfOnBattleList($client, true, $this->playerPosition, Directions::SOUTH);
                break;
            case self::COMMAND_MOVE_WEST:
                $output = $client->makeOutputMessage();

                $oldPosition = clone $this->playerPosition; // Clone bo inaczej pracuje na jednej instancji
                $this->playerPosition->goWest();

                $this->sendMove($output, $oldPosition, $this->playerPosition, 351);

                $client->send($output);
                $this->sendSelfOnBattleList($client, true, $this->playerPosition, Directions::WEST);
                break;
            case self::COMMAND_MOVE_SOUTH_EAST:
                $output = $client->makeOutputMessage();

                $oldPosition = clone $this->playerPosition; // Clone bo inaczej pracuje na jednej instancji
                $this->playerPosition->goSouth();
                $this->playerPosition->goEast();

                $this->sendMove($output, $oldPosition, $this->playerPosition, 351);

                $client->send($output);
                $this->sendSelfOnBattleList($client, true, $this->playerPosition, Directions::EAST);
                break;
            case self::COMMAND_MOVE_SOUTH_WEST:
                $output = $client->makeOutputMessage();

                $oldPosition = clone $this->playerPosition; // Clone bo inaczej pracuje na jednej instancji
                $this->playerPosition->goSouth();
                $this->playerPosition->goWest();

                $this->sendMove($output, $oldPosition, $this->playerPosition, 351);

                $client->send($output);
                $this->sendSelfOnBattleList($client, true, $this->playerPosition, Directions::WEST);
                break;
            case self::COMMAND_MOVE_NORTH_EAST:
                $output = $client->makeOutputMessage();

                $oldPosition = clone $this->playerPosition; // Clone bo inaczej pracuje na jednej instancji
                $this->playerPosition->goNorth();
                $this->playerPosition->goEast();

                $this->sendMove($output, $oldPosition, $this->playerPosition, 351);

                $client->send($output);
                $this->sendSelfOnBattleList($client, true, $this->playerPosition, Directions::EAST);
                break;
            case self::COMMAND_MOVE_NORTH_WEST:
                $output = $client->makeOutputMessage();

                $oldPosition = clone $this->playerPosition; // Clone bo inaczej pracuje na jednej instancji
                $this->playerPosition->goNorth();
                $this->playerPosition->goWest();

                $this->sendMove($output, $oldPosition, $this->playerPosition, 351);

                $client->send($output);
                $this->sendSelfOnBattleList($client, true, $this->playerPosition, Directions::WEST);
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
                    $output->addShort($this->playerPosition->x); # Start map position
                    $output->addShort($this->playerPosition->y);
                    $output->addByte($this->playerPosition->z);
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

            case self::COMMAND_USE_ITEM:
                $x = $buffer->readUnsignedShort();
                $y = $buffer->readUnsignedShort();
                $z = $buffer->readUnsignedByte();

                $spriteID = $buffer->readUnsignedShort();
                $stack = $buffer->readUnsignedByte();
                $index = $buffer->readUnsignedByte(); // This is parent ID ?


                if( $spriteID == self::ITEM_BACKPACK ) {
                    $cid = random_int(0, 10);
                    $items = [self::ITEM_BACKPACK];

                    $output = $client->makeOutputMessage();

                    $output->addByte(0x6E);  // Command
                    $output->addByte($cid);  // CID

                    $output->addShort(self::ITEM_BACKPACK);  // Command
//                        $output->addByte(0xFF);
                    $output->addString('Backpack.. (' . $cid . ')');

                    $output->addByte(0x0B); // Capacity
                    $output->addByte(0x00); // ID Parent

//                        $output->addByte(0x00); // Drag and drop
//                        $output->addByte(0x00); // Pagination

                    $itemCC = count($items);
                    $output->addByte($itemCC); // Container size
//                        $output->addShort(0x00); // First index

                    foreach($items as $item) {
                        $output->addShort($item);
                    }

                    $client->send($output);
                }

                var_dump($x, $y, $z, $spriteID, $stack, $index);

                break;

            case self::COMMAND_REQUEST_OUTFIT_WINDOW:
                $this->sendOutfitWindow($client);
                break;

            case 0x82: // Use item
                $output = $client->makeOutputMessage();

                $x = $buffer->readUnsignedShort();
                $y = $buffer->readUnsignedShort();
                $z = $buffer->readUnsignedByte();

                $spriteID = $buffer->readUnsignedShort();
                $stackposition = $buffer->readUnsignedByte();
                $index = $buffer->readUnsignedByte();

                $client->send($output);

                // Position pos = msg.getPosition();
                // uint16_t spriteId = msg.get<uint16_t>();
                // uint8_t stackpos = msg.getByte();
                // uint8_t index = msg.getByte();
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

                $this->sendSelfOnBattleList($client, true, $this->playerPosition);
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

            case self::COMMAND_OPEN_QUEST_LOG:
                $questCC = 10;
                $output = $client->makeOutputMessage();

                $output->addByte(self::REQUEST_QUEST_LOG);
                $output->addShort($questCC);

                for($i = 0; $i < $questCC; $i++) {
                    $output->addShort($i);
                    $output->addString('Quest ID: ' . $i);
                    $output->addByte(random_int(0, 1));
                }

                $client->send($output);
                break;

            case self::COMMAND_SHOW_MISSION_DESCRIPTION:
                $missionCC = 10;
                $output = $client->makeOutputMessage();

                $output->addByte(self::REQUEST_MISSION_DESCRIPTION);
                $output->addShort(0x01); // Quest ID
                $output->addByte($missionCC);

                for($i = 0; $i < $missionCC; $i++) {
                    $output->addString('Quest Name: ' . $i);
                    $output->addString('Quest Description: ' . $i);
                }

                $client->send($output);
                break;
            case self::COMMAND_REMOVE_VIP:
                // Server nic nie odpowiada
                break;
            default:
                    echo 'Unknown protocol ' . $command . ' [0x' . str_pad(dechex($command), 2, '0', STR_PAD_LEFT) . ']' . PHP_EOL;
                break;
        }
    }

    private function sendCancelMessage(Client $client, string $message)
    {
        $this->sendMessage($client, MessageType::MSG_STATUS_DEFAULT, $message);
    }

    private function sendLookMessage(Client $client, string $message)
    {
        $this->sendMessage($client, MessageType::MSG_INFO_DESCR, $message);
    }

    private function sendMessage(Client $client, int $messageType, string $message)
    {
        $outputMessage = $client->makeOutputMessage();
//
        # Message??
        $outputMessage->addByte(self::REQUEST_MESSAGE);
        $outputMessage->addByte($messageType);
        $outputMessage->addString($message);

        $client->send($outputMessage);
    }

    private function sendSelfOnBattleList(Client $client, bool $known, Position $position, int $dir = Directions::SOUTH)
    {
        $output = $client->makeOutputMessage();
        $output->AddByte(0x6A);
        $output->addShort($position->x); # Start map position
        $output->addShort($position->y);
        $output->addByte($position->z);

        if($known) {
            $output->addShort(0x61);
            $output->addInteger(0x01);
        } else {
            $output->addShort(0x61);
            $output->addInteger(0x00);
            $output->addInteger(0x01);
            $output->addString("Yukoriko");
        }

        $output->addByte(0x32); // HP Bar in percent
        $output->addByte($dir); // Look direction
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
    }

    private function sendOutfitWindow(Client $client)
    {
        $outputMessage = $client->makeOutputMessage();
        # Outfits ?
        $outputMessage->addByte(0xC8);
        $outputMessage->addShort(self::OUTFIT_GAMEMASTER);   # Gamemaster (ID: 75)
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x00);
        $outputMessage->addByte(0x00);

        $outfits = [
            ['id' => self::OUTFIT_GAMEMASTER, 'name' => 'Game master', 'addons' => 0x0],
            ['id' => self::OUTFIT_MALE_HUNTER, 'name' => 'Hunter', 'addons' => 0x0],
            ['id' => self::OUTFIT_MALE_MAGE, 'name' => 'Mage', 'addons' => 0x0]
        ];

        $cc = count($outfits);
        $outputMessage->addByte($cc);         // Outfit count
        for($i = 0; $i < $cc; $i++) {
            $outfit = $outfits[$i];

            $outputMessage->addShort($outfit['id']);
            $outputMessage->addString($outfit['name']);
            $outputMessage->addByte($outfit['addons']);
        }

        $client->send($outputMessage);
    }

    private function sendMove(OutputMessage $msg, Position $oldPosition, Position $newPosition, int $floorId = 351): OutputMessage
    {
        $msg->AddByte(0x6D);

        # Old position
        $msg->addShort($oldPosition->x); # Start map position
        $msg->addShort($oldPosition->y);
        $msg->addByte($oldPosition->z);

        # Stack
        $msg->AddByte($newPosition->stack);

        # New position
        $msg->addShort($newPosition->x); # Start map position
        $msg->addShort($newPosition->y);
        $msg->addByte($newPosition->z);

        if($oldPosition->y > $newPosition->y) {
            $msg->addByte(0x65);

            $this->GetMapDescription($oldPosition->x - 8, $newPosition->y - 6, $newPosition->z, 18, 1, $msg, $floorId);
        } elseif ($oldPosition->y < $newPosition->y) {
            $msg->addByte(0x67);

            $this->GetMapDescription($oldPosition->x - 8, $newPosition->y + 7, $newPosition->z, 18, 1, $msg, $floorId);
        }

        if($oldPosition->x < $newPosition->x) {
            $msg->addByte(0x66);

            $this->GetMapDescription($oldPosition->x + 9, $newPosition->y - 6, $newPosition->z, 1, 14, $msg, $floorId);
        } elseif ($oldPosition->x > $newPosition->x) {
            $msg->addByte(0x68);

            $this->GetMapDescription($oldPosition->x - 8, $newPosition->y - 6, $newPosition->z, 1, 14, $msg, $floorId);
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


//        $stacks = [
//            [Items::BACKPACK, Items::ROPE, Items::VODO_DOLL],
//            [Items::VODO_DOLL],
//            [Items::BACKPACK, Items::ROPE],
//            [Items::DEAD_ANCIENT_SCARAB],
//            [Items::DEAD_ANCIENT_SCARAB, Items::ROPE],
//        ];
//
//        $stacks = [
//            [Items::CRYSTAL_COINS_11]
//        ];
//
//        $stackCount = 0;
//        if(rand(0, 10) == 1) {
//            $randomStack = $stacks[rand(0, count($stacks) - 1)] ?? [];
//            foreach(array_reverse($randomStack) as ['id' => $itemId, 'amount' => $amount]) {
//                if($stackCount < 10) {
//                    $msg->addShort($itemId);
//                    if(is_numeric($amount)) {
//                        $msg->addByte($amount);
//                    }
//                    print_r([$itemId, $amount]);
//                    $stackCount++;
//                }
//            }
//        }

        $ids = [2941, 3001, 3002, 3003, 2854];
        $msg->addShort($floorId);
        $msg->addShort(2874);
        $msg->addByte(rand(0, 7)%8);

    }
}

class Items
{
    const ROPE = ['id' => 3003, 'amount' => null];
    const VODO_DOLL = ['id' => 3002, 'amount' => null];
    const BACKPACK = ['id' => 2854, 'amount' => null];
    const DEAD_ANCIENT_SCARAB = ['id' => 6021, 'amount' => null];
    const CRYSTAL_COINS_11 = ['id' => 2160, 'amount' => 0x0A];
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

class Position
{
    public $x, $y, $z, $stack;

    /**
     * Position constructor.
     *
     * @param $x
     * @param $y
     * @param $z
     * @param $stack
     */
    public function __construct($x, $y, $z, $stack)
    {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->stack = $stack;
    }

    public function goSouth(): void
    {
        $this->y += 1;
    }

    public function goNorth(): void
    {
        $this->y -= 1;
    }

    public function goEast(): void
    {
        $this->x += 1;
    }

    public function goWest(): void
    {
        $this->x -= 1;
    }
}