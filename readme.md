# RockFinder2

ProcessWire module, successor of RockFinder

---

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

## New syntax

```
// foo.php
$foo = new RockFinder();
$foo->name = 'foo';
$foo->addColumns(['bar', 'what', 'so', 'ever']);
return $foo;
```

```
// bar.php
$bar = new RockFinder();
$bar->name = 'bar';
$bar->addColumns(['id', 'title']);
return $bar;
```

```
$rf = new RockFinder();
$rf->name = 'demo';
$rf->import('foo', [
  'hide' => ['what', 'so'],
  'rename' => [
    'ever' => 'never',
  ],
  'prefix' => 'foo_',
]);
$rf->hideColumns([
$rf->addColumns(['some', 'more', 'columns']);
$rf->addRelation('bar', 'id', 'bar');
$rf->addOptions('invoice_type');
$rf->addOptions('invoice_status');
$rf->getData();
```

```
|- data [1, 2, 3] (base table)
|- relations
|  '- bar [1, 2, 3]
'- options (options/select fields)
   |- invoice_type [1, 2, 3]
   '- invoice_status [1, 2, 3]
```

Joins would join a finder into the base table ("data"). Column name collisions can occur!

What about images, repeaters, pagetables?

Columns MUST be defined manually. Otherwise joins can not work. If SQL is provided and columns are not set, the columns are retrieved via an `SQL limit=1` query.

## misc

Join SQL column:

```
$rf->addColumn('SELECT foo FROM bar WHERE id = demo.xxx' => 'sqldemo');
```

Add column with custom name:

```
$rf->addColumn('fieldname' => 'customname');
```
