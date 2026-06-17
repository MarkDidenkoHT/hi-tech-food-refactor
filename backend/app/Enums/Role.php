<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Director = 'director';
    case Manager = 'manager';
    case Staff = 'staff';
}
