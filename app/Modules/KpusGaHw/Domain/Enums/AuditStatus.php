<?php

namespace App\Modules\KpusGaHw\Domain\Enums;

enum AuditStatus: string
{
    case Baik = 'Baik';
    case Bermasalah = 'Bermasalah';
    case NeedReview = 'Need Review';
}
