<?php

namespace App\Communication\Domain\Enum;

enum NotificationStatus: string
{
    case LU = 'lu';
    case NON_LU = 'non_lu';
}
