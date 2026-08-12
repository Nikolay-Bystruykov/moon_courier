<?php

namespace App\Domain\Lunar;

enum Terrain: string
{
    case Mare = 'mare';
    case Regolith = 'regolith';
    case Crater = 'crater';
    case Rille = 'rille';
    case Shadow = 'shadow';
}
