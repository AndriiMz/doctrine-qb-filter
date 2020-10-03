### Query Filter Bundle

Query Filter Bundle gives an ability to user array filters instead of building query builder every time.

For example, you have an entity:
```
class User {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    public $id;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Task")
     */
    public $task;
} 
```

If you need to extract users with task you can write simple lines instead of building queryBuilder:
```
    $filter = new FilterRequest();
    $filter->filter['task']['is_not_null'] = true;
    $result = $queryFilter->getResults(User::class, $filter);
    $users = $result->items;
```
