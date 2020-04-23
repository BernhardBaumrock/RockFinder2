# RockFinder2

ProcessWire module, successor of RockFinder



---

Sorry for this readme! It is really not more then notes to myself. I hope I can provide a proper readme one day :)

---


```php
$f = new RockFinder2();
$f->find('template=admin');
$f->addColumns(['title']);
db($f);
```
![img](https://i.imgur.com/0LaTBxO.png)

---

## How to create custom column types

It is really easy to create custom column types that can retrieve any data from the PW database. See this commit for two simple examples: https://github.com/BernhardBaumrock/RockFinder2/commit/54476a24c78ae4d3b6d00f8adfb2c8cd9d764b9d

If the fieldtype is of broader interest please consider making a PR. If you are the only one needing it add the type via hook.

Adding a custom column type is as simple as this:

```php
$f->addColumns(['FieldOptionsValue:your_options_fieldname']);
```

---

## Manually joining any kind of data

```php
$f = new RockFinder2();
$f->find('template=basic-page, include=all');
$f->addColumns(['title', 'options']);
$f->query->select("opt.value AS `options.value`");
$f->query->leftjoin("fieldtype_options AS opt ON opt.option_id = _field_options.data");
db($f->getData()->data);
```
![img](https://i.imgur.com/Q3vmS2v.png)

Dumping the query object will be really helpful on such tasks!

![img](https://i.imgur.com/oF0mGyf.png)

You can also replace the field's value instead of adding it to the result:

```php
$f = new RockFinder2();
$f->find('template=basic-page, include=all');
$f->addColumns(['title', 'options']);

$select = $f->query->select;
unset($select[2]);
$f->query->set('select', $select);


$f->query->select("opt.value AS `options`");
$f->query->leftjoin("fieldtype_options AS opt ON opt.option_id = _field_options.data");
db($f->query);
db($f->getData()->data);
```

![img](https://i.imgur.com/5OxQkbm.png)
