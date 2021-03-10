<?php
include "C:\Users\admin\Desktop\Manhattan\Manhattan-Server\src\BinaryReader.php";

$fileName = 'forgotten.otbm';
$fileHandle = fopen($fileName, 'rb'); //filesize($fileName)

$reader = new \Yukoriko\BinaryReader(fread($fileHandle, filesize($fileName)));
$reader->dump('test');
$data = [
    'magic' => [
        $reader->readUnsignedByte(),
        $reader->readUnsignedByte(),
        $reader->readUnsignedByte(),
        $reader->readUnsignedByte()
    ],
    'node' => [
        $reader->readUnsignedByte(),
        $reader->readUnsignedByte()
    ],
    'map' => [
        'version' => $reader->readUnsignedInteger(),
        'width' => $reader->readUnsignedShort(),
        'height' => $reader->readUnsignedShort()
    ],
    'items' => [
        'major' => $reader->readUnsignedInteger(),
        'minor' => $reader->readUnsignedInteger()
    ]
];

die;