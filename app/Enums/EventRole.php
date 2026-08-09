<?php

namespace App\Enums;

enum EventRole: string
{
    case Admin = 'admin';
    case Judge = 'judge';
    case Tabulator = 'tabulator';
}
