<?php

namespace App\Domain\Lunar;

enum RoverClass: string
{
    case Crawler = 'crawler';
    case Scout = 'scout';
    case Hauler = 'hauler';
}
