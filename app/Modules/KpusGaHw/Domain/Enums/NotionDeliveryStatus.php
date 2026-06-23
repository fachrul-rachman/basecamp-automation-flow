<?php

namespace App\Modules\KpusGaHw\Domain\Enums;

enum NotionDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
