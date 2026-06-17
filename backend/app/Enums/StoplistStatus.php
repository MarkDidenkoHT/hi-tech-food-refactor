<?php

namespace App\Enums;

enum StoplistStatus: string
{
    case Stop = 'stop';
    case Limit = 'limit';
    case Play = 'play';
}
