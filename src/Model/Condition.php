<?php

namespace AndriiMz\QbFilter\Model;

class Condition
{
    public const AND_TYPE = 'and';
    public const OR_TYPE = 'or';

    /**
     * @var string
     */
    public $property;

    /**
     * @var string|InQbCondition
     */
    public $value;

    /**
     * like, neq, eq, null, not_null
     * see App\Infrastructure\QueryFilter\Enum\FilterTypeEnum
     * @var string
     */
    public $operator;

    /**
     * @var string
     */
    public $type;

    /**
     * @var array|Condition[]
     */
    public $conditions = [];
}
