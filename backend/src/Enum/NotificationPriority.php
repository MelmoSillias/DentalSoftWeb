<?php

namespace App\Enum;

enum NotificationPriority: string
{
    case INFO = 'info';
    case AVERTISSEMENT = 'avertissement';
    case CRITIQUE = 'critique';
}
