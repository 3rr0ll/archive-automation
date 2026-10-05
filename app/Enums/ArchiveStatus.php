<?php

namespace App\Enums;

enum ArchiveStatus: string
{
    case Waiting = 'waiting';
    case Ready = 'ready';
    case Failed = 'failed';
}
