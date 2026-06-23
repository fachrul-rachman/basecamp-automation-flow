<?php

namespace App\Modules\KpusGaHw\Domain\Enums;

enum AiReviewResult: string
{
    case Ok = 'ok';
    case Anomaly = 'anomaly';
    case Uncertain = 'uncertain';
}
