<?php

namespace AndriiMz\QbFilter\Enum;

class FilterTypeEnum
{
    public const LIKE_TYPE = 'like';
    public const NOT_LIKE_TYPE = 'not_like';

    public const NOT_EQUALS_TYPE = 'neq';
    public const EQUALS_TYPE = 'eq';

    public const IS_NULL_TYPE = 'null';
    public const IS_NOT_NULL_TYPE = 'not_null';

    public const IN_TYPE = 'in';
    public const IN_QB_TYPE = 'in_qb_type';
    public const NOT_IN_TYPE = 'not_in';

    public const GREATER_THAN = 'gt';
    public const LESS_THAN = 'lt';
    public const GREATER_THAN_COLUMN = 'gt_column';
    public const LESS_THAN_COLUMN = 'lt_column';

    public const GREATER_EQUALS_THAN = 'gte';
    public const LESS_EQUALS_THAN = 'lte';
}
