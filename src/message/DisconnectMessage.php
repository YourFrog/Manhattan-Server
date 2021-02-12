<?php

namespace Yukoriko\message;

use Yukoriko\OutputMessage;

/**
 *  Wiadomość wyświetlana użytkownikowi w przypadku rozłączenia
 *
 * @package Yukoriko\message
 */
class DisconnectMessage extends OutputMessage
{
    /**
     *  Konstruktor
     *
     * @param array $xtea
     * @param string $message
     */
    public function __construct(array $xtea, string $message)
    {
        parent::__construct($xtea);

        $this->addByte(0x0A);
        $this->addString($message);
    }
}