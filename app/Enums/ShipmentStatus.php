<?php

namespace App\Enums;

enum ShipmentStatus: string 
{
    case Pending = 'pending';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';

}