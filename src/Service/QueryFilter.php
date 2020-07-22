<?php

namespace AndriiMz\QbFilter\Service;

use AndriiMz\QbFilter\Enum\FilterTypeEnum;
use AndriiMz\QbFilter\Enum\JoinTypeEnum;
use AndriiMz\QbFilter\Model\Condition;
use AndriiMz\QbFilter\Model\FilterRequest;
use AndriiMz\QbFilter\Model\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use function count;
use function in_array;

class QueryFilter
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var QueryBuilderFilterStrategy
     */
    private $queryBuilderFilterStrategy;

    /**
     * @var PropertyTypeExtractorInterface
     */
    private $propertyTypeExtractor;

    /**
     * @param EntityManagerInterface $entityManager
     * @param QueryBuilderFilterStrategy $queryBuilderFilterStrategy
     * @param PropertyTypeExtractorInterface $propertyTypeExtractor
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        QueryBuilderFilterStrategy $queryBuilderFilterStrategy,
        PropertyTypeExtractorInterface $propertyTypeExtractor
    ) {
        $this->entityManager = $entityManager;
        $this->queryBuilderFilterStrategy = $queryBuilderFilterStrategy;
        $this->propertyTypeExtractor = $propertyTypeExtractor;
    }

    /**
     * @param string $entityName
     * @param FilterRequest $request
     * @param array $joins
     *
     * @return Result
     */
    public function getResults(
        string $entityName,
        FilterRequest $request,
        array $joins = []
    ): Result
    {
        /** @var EntityRepository $repository */
        $repository = $this->entityManager->getRepository($entityName);
        $entityTableName = $this->extractEntityTableName($entityName);
        $alias = uniqid($entityTableName, false);
        $select = $alias;

        $qb = $repository->createQueryBuilder($alias);
        $conditions = $this->extractConditions($request);
        $joinTables = $this->extractJoinTables($entityName, $conditions);

        $tableAliases = [
            $entityTableName => $alias
        ];

        foreach ($joins as $joinTable => $options) {
            $validType = in_array($options['type'], JoinTypeEnum::JOINS_TYPES);

            if (!isset($joinTables[$joinTable])) {
                $joinTables[$joinTable] = [
                    'alias' => uniqid($joinTable, false),
                    'type' => $validType ? $options['type'] : JoinTypeEnum::INNER_JOIN
                ];
            } elseif($validType) {
                $joinTables[$joinTable]['type'] = $options['type'];
            }

            $joinTables[$joinTable] = array_merge($joinTables[$joinTable], $options);
        }

        foreach ($joinTables as $table => $opts) {
            $tableAliases[$table] = $opts['alias'];
        }

        $qbParams = [];
        if (!empty($conditions)) {
            foreach ($joinTables as $tableName => &$joinOptions) {
                $joinTo = $joinOptions['joinTo'] ?? $alias;
                if (!empty($joinOptions['condition'])) {
                    $joinOptions['condition'] = str_replace(
                        array_map(function ($item) { return '`' . $item . '`'; }, array_keys($tableAliases)),
                        array_values($tableAliases),
                        $joinOptions['condition']
                    );
                }

                if (!empty($joinOptions['select'])) {
                    $select .= ', ' . $joinOptions['alias'];
                }

                switch ($joinOptions['type']) {
                    default:
                    case JoinTypeEnum::INNER_JOIN:
                        $qb = $qb->join(
                            $joinTo . '.' . $tableName,
                            $joinOptions['alias'],
                            $joinOptions['conditionType'] ?? null,
                            $joinOptions['condition'] ?? null
                        );
                        break;
                    case JoinTypeEnum::LEFT_JOIN:
                        $qb = $qb->leftJoin(
                            $joinTo . '.' . $tableName,
                            $joinOptions['alias'],
                            $joinOptions['conditionType'] ?? null,
                            $joinOptions['condition'] ?? null
                        );
                        break;
                }
            }

            $joinTables = array_map(function ($opts) {
                return $opts['alias'];
            }, $joinTables);

            $qb = $this->queryBuilderFilterStrategy
                ->getFilteredQueryBuilder(
                    $qb,
                    $conditions,
                    $alias,
                    $joinTables,
                    $qbParams
                );
        }

        if (!empty($request->order)) {
            foreach ($request->order as $column => $direction) {
                $currentAlias = $alias;
                $path = explode('.', $column);
                if (count($path) === 2) {
                    $currentAlias = $joinTables[$path[0]];
                    $column = $path[1];
                }

                $qb = $qb->orderBy($currentAlias . '.' . $column, $direction);
            }
        }

        if (null !== $request->limit) {
            $qb = $qb->setMaxResults($request->limit);
        }

        $result = new Result();
        $result->qb = $qb;
        $result->mainAlias = $alias;
        $result->qbParams = $qbParams;
        $result->total = (int) $qb
            ->select("count($alias)")
            ->getQuery()
            ->getSingleScalarResult();

        $result->query = $qb
            ->select("$select")
            ->getQuery()
            ->getSQL();

        if (null !== $request->page && $request->page > 0 && null !== $request->limit) {
            $qb = $qb->setFirstResult(($request->page - 1) * $request->limit);
        }

        $result->items = $qb
            ->select("$select")
            ->getQuery()
            ->getResult();

        $result->page = $request->page;
        $result->limit = $request->limit;

        return $result;
    }

    /**
     * @param string $entityName
     *
     * @return string
     */
    private function extractEntityTableName(string $entityName): string
    {
        $entityPath = explode('\\', $entityName);

        return strtolower(
            array_pop($entityPath)
        );
    }

    /**
     * @param string $className
     * @param array|Condition[] $conditions
     * @param array $tables
     *
     * @return array
     */
    private function extractJoinTables(string $className, array $conditions, array $tables = []): array
    {
        foreach ($conditions as $condition) {
            if (empty($condition->conditions)) {
                $path = explode('.', $condition->property);
                if (count($path) < 2) {
                    continue;
                }

                $previousAlias = null;
                $currentClassName = $className;

                while (count($path) > 1) {
                    $prop = array_shift($path);
                    $relationName = $prop;
                    //$relationName = $this->nameConverter->denormalize($prop);
                    $types = $this->propertyTypeExtractor->getTypes($currentClassName, $prop);

                    if (!empty($types)) {
                        $type = $types[0];
                        if ($type->isCollection()) {
                            $currentClassName = $type->getCollectionValueType()->getClassName() ?? $currentClassName;
                        } else {
                            $currentClassName = $type->getClassName() ?? $currentClassName;
                        }

                        if ($this->entityManager->getMetadataFactory()->isTransient($currentClassName)) {
                            $path = [];
                            continue;
                        }
                    }

                    if (!isset($tables[$relationName])) {
                        $tables[$relationName] = [
                            'alias' => uniqid($relationName, false),
                            'type' => JoinTypeEnum::INNER_JOIN
                        ];

                        if (null !== $previousAlias) {
                            $tables[$relationName]['joinTo'] = $previousAlias;
                        }
                    }

                    $previousAlias = $tables[$relationName]['alias'];
                }
            } else {
                $tables = $this->extractJoinTables($className, $condition->conditions, $tables);
            }
        }

        return $tables;
    }

    /**
     * @param FilterRequest $request
     *
     * @return array
     * TODO: refactor extraction
     */
    private function extractConditions(
        FilterRequest $request
    ): array {
        $conditions = [];
        foreach ($request->filter as $property => $filters) {
            if (in_array($property, [Condition::OR_TYPE, Condition::AND_TYPE])) {
                $sub = [];
                foreach ($filters as $p => $f) {
                    $this->extractCondition($p, $f, $sub, $property);
                }
                $condition = new Condition();
                $condition->type = Condition::AND_TYPE;
                $condition->conditions = $sub;

                $conditions[] = $condition;
            } else {
                $this->extractCondition($property, $filters, $conditions);
            }
        }

        return  $conditions;
    }

    /**
     * @param string $property
     * @param $filters
     * @param array $conditions
     * @param string $type
     */
    private function extractCondition(
        string $property,
        $filters,
        array &$conditions,
        string $type = Condition::AND_TYPE
    )
    {
        $property = trim($property);
        $propertyParts = explode('.', $property);
        $propertyParts[count($propertyParts) - 1] = end($propertyParts);
        $property = implode('.', $propertyParts);
        if (in_array($property, [Condition::OR_TYPE, Condition::AND_TYPE])) {
            $sub = [];
            foreach ($filters as $p => $f) {
                $this->extractCondition($p, $f, $sub, $property);
            }

            $condition = new Condition();
            $condition->type = $property;
            $condition->conditions = $sub;

            $conditions[] = $condition;
            return;
        }

        if (is_numeric($property) && in_array(array_keys($filters)[0], [Condition::OR_TYPE, Condition::AND_TYPE])) {
            $sub = [];
            foreach (array_values($filters)[0] as $p => $f) {
                $this->extractCondition($p, $f, $sub, array_keys($filters)[0]);
            }

            $condition = new Condition();
            $condition->type = $type;
            $condition->conditions = $sub;

            $conditions[] = $condition;


            return;
        }

        if (!is_array($filters)) {
            $condition = new Condition();
            $condition->property = $property;
            $condition->value = $filters;
            $condition->type = $type;
            $condition->operator = FilterTypeEnum::EQUALS_TYPE;

            $conditions[] = $condition;

            return;
        }

        foreach ($filters as $operator => $value) {
            if (in_array($operator, [Condition::OR_TYPE, Condition::AND_TYPE])) {
                $sub = [];
                foreach ($value as $p => $f) {
                    $this->extractCondition($p, $f, $sub, $operator);
                }
                $condition = new Condition();
                $condition->type = $operator;
                $condition->conditions = $sub;

                $conditions[] = $condition;
                continue;
            }

            if (is_array($value) && !in_array($operator, [FilterTypeEnum::IN_TYPE, FilterTypeEnum::NOT_IN_TYPE])) {
                foreach ($value as $val) {
                    $condition = new Condition();
                    $condition->property = $property;
                    $condition->value = $val;
                    $condition->type = $type;
                    $condition->operator = trim($operator);

                    $conditions[] = $condition;
                }
            } else {
                $condition = new Condition();
                $condition->property = $property;
                $condition->value = $value;
                $condition->type = $type;
                $condition->operator = trim($operator);

                $conditions[] = $condition;
            }
        }
    }
}
