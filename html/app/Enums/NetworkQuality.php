<?php

namespace App\Enums;

enum NetworkQuality: string
{
    case EXCELLENT = 'excellent';
    case GOOD = 'good';
    case FAIR = 'fair';
    case POOR = 'poor';
    case UNKNOWN = 'unknown';
}
