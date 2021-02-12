<?php


namespace Yukoriko;


use Ratchet\ConnectionInterface;

define('MOD_ADLER', 65521);

// Headers:
// 2 bytes for unencrypted message size
// 4 bytes for checksum
// 2 bytes for encrypted message size

class OutputMessage
{
    /**
     * @var resource
     */
    private $handle;

    private $messageLength;

    /**
     * @var array
     */
    private $bytes;

    /**
     *  Klucz 128 bitowy w postaci tablicy 4 elementowej z 32 bitowymi liczbami
     *
     * @var array
     */
    private $xtea;

    public function __construct(array $xtea = null)
    {
        $this->xtea = $xtea;
        $this->bytes = [];//, 0, 0, 0, 0, 0 ];

        $this->addShort(0);     // 2 bytes for unencrypted message size
//        $this->addInteger(0);   // 4 bytes for checksum (not exists on 8.1)
        $this->addShort(0);     // 2 bytes for encrypted message size

        $this->messageLength = 0;
    }

    public function addByte(int $byte)
    {
        $this->bytes[] = $byte;
        $this->messageLength += 1;
    }

    public function addShort(int $byte)
    {
        $hex = sprintf("%04X", $byte);

        $this->bytes[] = hexdec(substr($hex, 2, 2));
        $this->bytes[] = hexdec(substr($hex, 0, 2));

        $this->messageLength += 2;
    }

    public function addDouble($value, int $precision)
    {
        $value *= pow(10, $value);

        $this->addByte($precision);
        $this->addInteger((int) $value);
    }

    public function addInteger(int $byte)
    {
        $hex = sprintf("%08X", $byte);

        $this->bytes[] = hexdec(substr($hex, 6, 2));
        $this->bytes[] = hexdec(substr($hex, 4, 2));
        $this->bytes[] = hexdec(substr($hex, 2, 2));
        $this->bytes[] = hexdec(substr($hex, 0, 2));

        $this->messageLength += 4;
    }

    public function addIP(string $ip)
    {
        $value = ip2long($ip);

        var_dump('IP: ', $value);

        $hex = sprintf("%08X", $value);

        $this->bytes[] = hexdec(substr($hex, 0, 2));
        $this->bytes[] = hexdec(substr($hex, 2, 2));
        $this->bytes[] = hexdec(substr($hex, 4, 2));
        $this->bytes[] = hexdec(substr($hex, 6, 2));

        $this->messageLength += 4;
    }

    public function addXTEA() {
        $outputMessageLength = count($this->bytes) - 2; // 8 - is a distance from "0" position

        if( $outputMessageLength % 8 !== 0 ) {
            for($i = 0; $i < 8 - $outputMessageLength % 8; $i++) {
                $this->addByte(0x33);
            }

            $outputMessageLength = count($this->bytes) - 2;
        }

        $this->dumpBytes('Unencrypted message');

        $slice = array_slice($this->bytes, 2);
        $outputMessage = implode('', array_map(function($value) {
            return chr($value);
        }, $slice))
        ;
        var_dump('Output message after padding: ', $outputMessage);
        $outputMessageEncrypt = XTEA::encrypt($outputMessage, $this->xtea);

        var_dump('Output message length aftter crypt: ' . strlen($outputMessageEncrypt));
        # Reset bytes
        $this->bytes = array_fill(0, 2, 0);

        for($i = 0; $i < strlen($outputMessageEncrypt); $i++) {
            $this->bytes[$i + 2] = ord($outputMessageEncrypt[$i]);
        }

        $this->dumpBytes('Encrypted output message');
    }

    public function addChecksum()
    {
//        $adler = $this->adler32();
//        $bytes = sprintf("%08X", $adler);//unpack('C*', $adler);
//
//        var_dump('Adler: ' . $adler . ', bytes: ' . json_encode($bytes));
//
//        $this->bytes[2] = hexdec(substr($bytes, 0, 2));
//        $this->bytes[3] = hexdec(substr($bytes, 2, 2));
//        $this->bytes[4] = hexdec(substr($bytes, 4, 2));
//        $this->bytes[5] = hexdec(substr($bytes, 6, 2));
    }

    public function addSize()
    {
        $hex = sprintf("%04X", $this->messageLength);

        $this->bytes[2] = hexdec(substr($hex, 2, 2));
        $this->bytes[3] = hexdec(substr($hex, 0, 2));
    }

    function adler32(): int
    {
        $a = 1; $b = 0; $len = $this->messageLength;

        for ($index = 6; $index < $len; ++$index) {
            $a = ($a + $this->bytes[$index]) % MOD_ADLER;
            $b = ($b + $a) % MOD_ADLER;
        }
        return ($b << 16) | $a;
    }

    public function addString(string $string)
    {
        $characters = unpack("C*", $string);
        $characters = array_map(function($ord) {
            return chr($ord);
        }, $characters);

        $cc = count($characters);
        $this->addShort($cc);
        $this->messageLength += $cc;

        foreach ($characters as $char) {
            $this->bytes[] = ord($char);
        }
    }

    /**
     *  Wysłanie widomości do konkretnego klienta
     *
     * @param ConnectionInterface $connection
     */
    public function send(ConnectionInterface $connection)
    {
        $outputMessage = $this->__toString();
        $connection->send($outputMessage);
    }

    public function __toString() {
        $this->dumpBytes('Befor encrypted');

        $this->addSize();
        if( $this->xtea !== null ) {
            $this->addXtea();
        }
        $this->addChecksum();

        $hex = sprintf("%04X", count($this->bytes) - 2);

        $this->bytes[0] = hexdec(substr($hex, 2, 2));
        $this->bytes[1] = hexdec(substr($hex, 0, 2));

//        $this->bytes[0] = 1;
//        $this->bytes[0] = count($this->bytes) - 1;
        $packed = pack("C*", ...$this->bytes);

        $maped = array_map(function($value) {
            return dechex($value);
        }, $this->bytes);

//        $this->dumpBytes('Flat message');
//        var_dump('MCount: ' . count($maped), json_encode($maped), $packed);

        $this->handle = fopen('php://memory','r+b');
        rewind($this->handle);
        fwrite($this->handle, $packed);
        rewind($this->handle);

        return stream_get_contents($this->handle);
    }

    private function dumpBytes($title) {
        $arr = array_map(function($value) {
            $hexValue = dechex($value);

            return '0x' . str_pad($hexValue, 2, '0', STR_PAD_LEFT);
        }, $this->bytes);

        echo $title . ' [Length: ' . count($this->bytes) . ']: ' . PHP_EOL;
        $cc = count($this->bytes);
        for($i = 0; $i < $cc; $i++) {
            $value = $arr[$i];

            echo $value . ' ';

            if( $i % 16 == 15 ) {
                echo PHP_EOL;
            }
        }

        echo PHP_EOL;
    }
}