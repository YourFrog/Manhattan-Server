<?php


namespace Yukoriko;


class BinaryReader
{
    /**
     * @var resource
     */
    private $handle;

    private $rsa;

    private $offset;


    public function __construct(string $string, bool $rsa = false)
    {
        $this->rsa = $rsa;

        $this->handle = fopen('php://memory','r+');
        fwrite($this->handle, $string);
        rewind($this->handle);

        $this->offset = 0;
    }

    public function readUnsignedByteArray(array $names): array {
        $names = array_map(function ($value) {
            return 'C' . $value;
        }, $names);

        return $this->readArray($names, 1);
    }

    public function readUnsignedShortArray(array $names): array {
        $names = array_map(function ($value) {
            return 'S' . $value;
        }, $names);

        return $this->readArray($names, 2);
    }

    private function readArray(array $names, int $typeSize) : array {
        $cc = count($names);
        $length = $cc * $typeSize;
        $data = fread($this->handle, $length);


        $this->offset += $cc * $typeSize;
        $format = implode('/', $names);
        return unpack($format, $data);
    }

    public function readUnsignedInteger(): int {
        $data = fread($this->handle, 4);
        $this->offset += 4;

        $format = $this->rsa ? 'V' : 'N';

        return unpack($format, $data)[1];
    }

    public function readString(): string {
        $length = $this->readUnsignedShort();
        $result = [];

        for($i = 0; $i < $length; $i++) {
            $value = $this->readUnsignedByte();
            $result[] = chr($value);
        }

        return implode('', $result);
    }

    public function readUnsignedByte(): int {
        $data = fread($this->handle, 1);
        $this->offset += 1;

        return unpack('C', $data)[1];
    }

    public function readUnsignedShort(): int {
        $data = fread($this->handle, 2);
        $this->offset += 2;

        return unpack('S', $data)[1];
    }

    public function all(int $length): string {
        return fread($this->handle, $length);
    }

    public function skip(int $offset) {
        fread($this->handle, $offset);
        $this->offset += $offset;
    }

    public function set(int $offset) {
        fseek($this->handle, $offset);
    }

    public function rsa(): string {
        return fread($this->handle, 128);
    }

    public function dump(string $title) {
        $oldPosition = ftell($this->handle);
        fseek($this->handle, 0, SEEK_SET);

        $message = fread($this->handle, 4096);
        $bytes = unpack('C*', $message);

        $bytes = array_map(function($value) {
            $hexValue = dechex($value);
            return '0x' . str_pad($hexValue, 2, '0', STR_PAD_LEFT);
        }, $bytes);

        echo $title . ' [Length: ' . count($bytes) . ']: ' . PHP_EOL;
        echo implode(' ', $bytes) . PHP_EOL;

        fseek($this->handle, $oldPosition, SEEK_SET);
    }
}
