```
$database = $this->wire('database'); 
$query = $database->prepare('DELETE FROM modules WHERE class=:class LIMIT 1'); // QA
$query->bindValue(":class", $class, \PDO::PARAM_STR); 
$query->execute();
```

```
$sql = "UPDATE `pages` SET `modified_users_id` = '".$this->wire('user')->id."' WHERE `id` = '".$p->id."';";
$this->wire('db')->query($sql);
```

## Finders MUST have a uniqe name

Names make it easier to JOIN finders, to save finders, to reuse finders, to import existing finders into new ones etc.; It makes it easier to split complicated finders into multiple; It makes it easier to select only some columns of a result;

## Create relations

```
$finder1 = new RockFinder2("template=foo", ['id', 'title', 'bar', 'what', 'so', 'ever']);
$finder2 = new RockFinder2("template=bar", ['id', 'title', 'something', 'else']);

@param finder
@param field of joined finder
@param field of original finder
$finder1->relate($finder2, 'id', 'bar');
```
