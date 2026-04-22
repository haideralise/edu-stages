<?php

namespace App\Enums;

enum EduAttendanceStatus: string
{
    case Present = 'present';
    case Leave = 'leave';
    case Cancelled = 'cancelled';
}
