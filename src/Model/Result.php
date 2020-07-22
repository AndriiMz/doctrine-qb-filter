<?php

namespace AndriiMz\QbFilter\Model;

use Doctrine\ORM\QueryBuilder;

class Result
{
    /**
     * @var array
     */
    public $items;

    /**
     * @var int
     */
    public $total;

    /**
     * @var string
     */
    public $query;

    /**
     * @var QueryBuilder
     */
    public $qb;

    /**
     * @var string
     */
    public $mainAlias;

    /**
     * @var array
     */
    public $qbParams;

    /**
     * @var int
     */
    public $page;

    /**
     * @var int
     */
    public $limit;
}
