<?php

namespace App\Enums;

enum RuleOperator: string
{
    case EQUALS = 'equals';
    case EQ = 'eq';
    case NOT_EQUALS = 'notEquals';
    case NE = 'ne';
    case GREATER_THAN = 'greaterThan';
    case GT = 'gt';
    case GREATER_THAN_OR_EQUAL = 'greaterThanOrEqual';
    case GTE = 'gte';
    case LESS_THAN = 'lessThan';
    case LT = 'lt';
    case LESS_THAN_OR_EQUAL = 'lessThanOrEqual';
    case LTE = 'lte';
    case IN = 'in';
    case NOT_IN = 'notIn';
}
