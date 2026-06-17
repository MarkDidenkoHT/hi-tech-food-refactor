<?php

namespace App\Enums;

enum TaskSource: string
{
    case Checklist = 'checklist';
    case Manual = 'manual';
}
