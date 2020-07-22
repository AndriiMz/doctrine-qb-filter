<?php

namespace AndriiMz\QbFilter\Model;

class FilterRequest
{
    /**
     * @var array
     */
    public $order;

    /**
     * @var array
     */
    public $filter;

    /**
     * @var string
     */
    public $query;

    /**
     * @var int
     */
    public $page;

    /**
     * @var int
     */
    public $limit;
}
