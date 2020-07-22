<?php

namespace AndriiMz\QbFilter\Service;

use AndriiMz\QbFilter\Enum\FilterTypeEnum;
use AndriiMz\QbFilter\Model\Condition;
use AndriiMz\QbFilter\Model\InQbCondition;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use function count;

class QueryBuilderFilterStrategy
{
//    /**
//     * @var NameConverterInterface
//     */
//    private $nameConverter;
//
//    /**
//     * @param NameConverterInterface $nameConverter
//     */
//    public function __construct(NameConverterInterface $nameConverter)
//    {
//        $this->nameConverter = $nameConverter;
//    }

    /**
     * @param QueryBuilder $qb
     * @param array|Condition[] $conditions
     * @param string $alias
     *
     * @param array $joinTables
     * @param array $parameters
     *
     * @return QueryBuilder
     */
    public function getFilteredQueryBuilder(
        QueryBuilder $qb,
        array $conditions,
        string $alias,
        array $joinTables,
        array &$parameters = []
    ): QueryBuilder {
        $exp = $this->createExpressions(
            $qb,
            $conditions,
            $alias,
            $joinTables,
            $parameters
        );

        $qb = $qb->andWhere(
                $qb->expr()
                    ->andX()
                    ->addMultiple($exp)
            )->setParameters($parameters);

        return $qb;
    }

    /**
     * @param QueryBuilder $qb
     * @param array $conditions
     * @param string $alias
     * @param array $joinTables
     * @param array $parameters
     *
     * @return array
     */
    private function createExpressions(
        QueryBuilder $qb,
        array $conditions,
        string $alias,
        array $joinTables,
        array &$parameters
    ): array
    {
        $expressions = [];
        foreach ($conditions as $condition) {
            if (!empty($condition->conditions)) {
                $expressions[$condition->type] =
                    array_merge(
                        $expressions[$condition->type] ?? [],
                        $this->createExpressions(
                            $qb,
                            $condition->conditions,
                            $alias,
                            $joinTables,
                            $parameters
                        )
                    );
            } else {
                $expressions[$condition->type][] = $this->getExpression(
                    $qb,
                    $condition,
                    $alias,
                    $joinTables,
                    $parameters
                );
            }
        }

        $typedExp = [];
        if (!empty($expressions[Condition::OR_TYPE])) {
            $typedExp[] = $qb->expr()->orX()->addMultiple($expressions[Condition::OR_TYPE]);
        }

        if (!empty($expressions[Condition::AND_TYPE])) {
            $typedExp[] = $qb->expr()->andX()->addMultiple($expressions[Condition::AND_TYPE]);
        }

        return $typedExp;
    }

    /**
     * @param QueryBuilder $qb
     * @param Condition $condition
     * @param string $alias
     * @param array $joinTables
     * @param array $parameters
     *
     * @return Expr|Expr\Comparison|string
     */
    private function getExpression(
        QueryBuilder $qb,
        Condition $condition,
        string $alias,
        array $joinTables,
        array &$parameters
    ) {
        $currentAlias = $alias;

        $column = $condition->property;
        $path = explode('.', $condition->property);
        if (count($path) > 1) {
            list($currentAlias, $column) = $this->getColumnAlias($path, $joinTables);
        }
        //else {
        //    $column = $this->nameConverter->denormalize($column);
        // }

        switch ($condition->operator) {
            case FilterTypeEnum::LIKE_TYPE:
                return $this->setLikeExpression(
                    $qb,
                    $currentAlias,
                    $column,
                    $condition->value,
                    $parameters
                );
                break;
            case FilterTypeEnum::NOT_LIKE_TYPE:
                return $this->setNotLikeExpression(
                    $qb,
                    $currentAlias,
                    $column,
                    $condition->value,
                    $parameters
                );
                break;
            case FilterTypeEnum::NOT_EQUALS_TYPE:
                return $this->getNotEqualsExpression(
                    $qb,
                    $currentAlias,
                    $column,
                    $condition->value,
                    $parameters
                );
                break;
            case FilterTypeEnum::EQUALS_TYPE:
                return $this->setEqualsCondition(
                    $qb,
                    $currentAlias,
                    $column,
                    $condition->value,
                    $parameters
                );
            case FilterTypeEnum::IN_TYPE:
                return $this->setInCondition(
                    $qb,
                    $currentAlias,
                    $column,
                    $condition->value
                );
                break;
            case FilterTypeEnum::IN_QB_TYPE:
                return $this->setInQbCondition(
                    $qb,
                    $currentAlias,
                    $column,
                    $condition->value,
                    $parameters
                );
                break;
            case FilterTypeEnum::NOT_IN_TYPE:
                return $this->setNotInCondition(
                    $qb,
                    $currentAlias,
                    $column,
                    $condition->value
                );
                break;
            case FilterTypeEnum::IS_NULL_TYPE:
                return $this->setNullCondition($qb, $currentAlias, $column);
            case FilterTypeEnum::IS_NOT_NULL_TYPE:
                return $this->setIsNotNullCondition($qb, $currentAlias, $column);
            case FilterTypeEnum::GREATER_THAN:
                return $this->setGreaterThanCondition($qb, $currentAlias, $column, $condition->value, $parameters);
            case FilterTypeEnum::LESS_THAN:
                return $this->setLessThenCondition($qb, $currentAlias, $column, $condition->value, $parameters);
            case FilterTypeEnum::GREATER_THAN_COLUMN:
                $column2 = $condition->value;
                $path = explode('.', $condition->value);
                $alias2 = $alias;
                if (count($path) > 1) {
                    $column2 = array_pop($path);
                    $relationName = array_pop($path);
                    //$relationName = $this->nameConverter->denormalize($relationName);
                    $alias2 = $joinTables[$relationName];
                }

                //$column2 = $this->nameConverter->denormalize($column2);

                return $this->setGreaterThanColumnCondition($qb, $currentAlias, $column, $alias2, $column2);
            case FilterTypeEnum::LESS_THAN_COLUMN:
                $column2 = $condition->value;
                $path = explode('.', $condition->value);
                $alias2 = $alias;
                if (count($path) > 1) {
                    $column2 = array_pop($path);
                    $relationName = array_pop($path);
                    //$relationName = $this->nameConverter->denormalize($relationName);
                    $alias2 = $joinTables[$relationName];
                }

                //$column2 = $this->nameConverter->denormalize($column2);

                return $this->setLessThenColumnCondition($qb, $currentAlias, $column, $alias2, $column2);
            case FilterTypeEnum::GREATER_EQUALS_THAN:
                return $this->setGreaterThanEqCondition($qb, $currentAlias, $column, $condition->value, $parameters);
            case FilterTypeEnum::LESS_EQUALS_THAN:
                return $this->setLessThenEqCondition($qb, $currentAlias, $column, $condition->value, $parameters);

        }

        return  '';
    }

    /**
     * @param array $path
     * @param array $joinTables
     *
     * @return array
     */
    private function getColumnAlias(array $path, array $joinTables): array
    {
        $column = array_pop($path); //$this->nameConverter->denormalize(array_pop($path));
        $relationName = array_pop($path); //$this->nameConverter->denormalize(array_pop($path));


        while (count($path) > 1) {
            if (!isset($joinTables[$relationName])) {
                $column = $relationName . '.' . $column;
                $relationName = array_pop($path); //$this->nameConverter->denormalize(array_pop($path));
            } else {
                return [$joinTables[$relationName], $column];
            }
        }

        return [$joinTables[$relationName], $column];
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     * @param array $parameters
     *
     * @return Expr|Expr\Comparison
     */
    private function setEqualsCondition(
        QueryBuilder $qb,
        string $alias,
        string $column,
        $value,
        array &$parameters = []
    ) : Expr\Comparison {
        $param = uniqid(
            $alias . '_' . str_replace('.', '_', $column),
            false
        );
        $parameters[$param] = $value;

        return $qb->expr()->eq(
            $alias . '.' . $column,
            ':' . $param
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     * @param array $parameters
     *
     * @return Expr|Expr\Comparison
     */
    private function getNotEqualsExpression(
        QueryBuilder $qb,
        string $alias,
        string $column,
        $value,
        array &$parameters = []
    ) : Expr\Comparison {
        $parameter = uniqid(
            'neq_' . $alias . '_' . str_replace('.', '_', $column),
            false
        );
        $parameters[$parameter] = $value;

        return $qb->expr()->neq(
            $alias . '.' . $column,
            ':' . $parameter
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     *
     * @return Expr|Expr\Func
     */
    private function setInCondition(
        QueryBuilder $qb,
        string $alias,
        string $column,
        $value
    ) : Expr\Func {
        return $qb->expr()->in(
            $alias . '.' . $column,
            $value
        );
    }


    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     * @param array $parameters
     *
     * @return Expr|Expr\Func
     */
    private function setInQbCondition(
        QueryBuilder $qb,
        string $alias,
        string $column,
        InQbCondition $value,
        array &$parameters = []
    ) : Expr\Func {
        $parameters = array_merge($parameters, $value->params);

        return $qb->expr()->in(
            $alias . '.' . $column,
            $value->dql
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     *
     * @return Expr|Expr\Func
     */
    private function setNotInCondition(
        QueryBuilder $qb,
        string $alias,
        string $column,
        $value
    ) : Expr\Func {
        return $qb->expr()->notIn(
            $alias . '.' . $column,
            $value
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     * @param array $parameters
     *
     * @return Expr|Expr\Comparison
     */
    private function setLikeExpression(
        QueryBuilder $qb,
        string $alias,
        string $column,
        $value,
        array &$parameters = []
    ): Expr\Comparison {
        $parameter = uniqid(
            'like_' . $alias . '_' . str_replace('.', '_', $column),
            false
        );
        $parameters[$parameter] = '%' . $value . '%';

        return $qb->expr()->like(
            $alias . '.' . $column,
            ':' . $parameter
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     *
     * @return string
     */
    private function setNullCondition(
        QueryBuilder $qb,
        string $alias,
        string $column
    ): string
    {
        return $qb->expr()->isNull($alias . '.' . $column);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     *
     * @return string
     */
    private function setIsNotNullCondition(
        QueryBuilder $qb,
        string $alias,
        string $column
    ): string {
        return $qb->expr()->isNotNull($alias . '.' . $column);
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     * @param array $parameters
     *
     * @return Expr\Comparison
     */
    private function setNotLikeExpression(
        QueryBuilder $qb,
        string $alias,
        string $column,
        $value,
        array &$parameters = []
    ): Expr\Comparison {
        $parameter = uniqid(
            'like_' . $alias . '_' . str_replace('.', '_', $column),
            false
        );
        $parameters[$parameter] = '%' . $value . '%';

        return $qb->expr()->notLike(
            $alias . '.' . $column,
            ':' . $parameter
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     * @param array $parameters
     *
     * @return Expr|Expr\Comparison
     */
    private function setGreaterThanCondition(
        QueryBuilder $qb,
        string $alias,
        string $column,
        $value,
        array &$parameters = []
    ) : Expr\Comparison {
        $param = uniqid(
            $alias . '_' . str_replace('.', '_', $column),
            false
        );
        $parameters[$param] = $value;

        return $qb->expr()->gt(
            $alias . '.' . $column,
            ':' . $param
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     * @param array $parameters
     *
     * @return Expr|Expr\Comparison
     */
    private function setLessThenCondition(
        QueryBuilder $qb,
        string $alias,
        string $column,
        $value,
        array &$parameters = []
    ) : Expr\Comparison {
        $param = uniqid(
            $alias . '_' . str_replace('.', '_', $column),
            false
        );
        $parameters[$param] = $value;

        return $qb->expr()->lt(
            $alias . '.' . $column,
            ':' . $param
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param string $alias2
     * @param string $column2
     *
     * @return Expr\Comparison
     */
    private function setGreaterThanColumnCondition(
        QueryBuilder $qb,
        string $alias,
        string $column,
        string $alias2,
        string $column2
    ) : Expr\Comparison {
        return $qb->expr()->gt(
            $alias . '.' . $column,
            $alias2 . '.' . $column2
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param string $alias2
     * @param string $column2
     * @return Expr\Comparison
     */
    private function setLessThenColumnCondition(
        QueryBuilder $qb,
        string $alias,
        string $column,
        string $alias2,
        string $column2
    ) : Expr\Comparison {
        return $qb->expr()->lt(
            $alias . '.' . $column,
            $alias2 . '.' . $column2
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     * @param array $parameters
     *
     * @return Expr|Expr\Comparison
     */
    private function setGreaterThanEqCondition(
        QueryBuilder $qb,
        string $alias,
        string $column,
        $value,
        array &$parameters = []
    ) : Expr\Comparison {
        $param = uniqid(
            $alias . '_' . str_replace('.', '_', $column),
            false
        );
        $parameters[$param] = $value;

        return $qb->expr()->gte(
            $alias . '.' . $column,
            ':' . $param
        );
    }

    /**
     * @param QueryBuilder $qb
     * @param string $alias
     * @param string $column
     * @param $value
     * @param array $parameters
     *
     * @return Expr|Expr\Comparison
     */
    private function setLessThenEqCondition(
        QueryBuilder $qb,
        string $alias,
        string $column,
        $value,
        array &$parameters = []
    ) : Expr\Comparison {
        $param = uniqid(
            $alias . '_' . str_replace('.', '_', $column),
            false
        );
        $parameters[$param] = $value;

        return $qb->expr()->lte(
            $alias . '.' . $column,
            ':' . $param
        );
    }
}
