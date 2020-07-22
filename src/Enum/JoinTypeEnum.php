<?php

namespace AndriiMz\QbFilter\Enum;

class JoinTypeEnum
{
    public const LEFT_JOIN = 'left_join';
    public const INNER_JOIN = 'inner_join';


    public const JOINS_TYPES = [
        self::LEFT_JOIN,
        self::INNER_JOIN
    ];
}
