# mongoDBHelper
A simple mongoDB library for PHP7

# Installation
The preferred method of installing this library is with Composer by running the following from your project root:
```
$ composer require dedemao/mongodb
```

# How to use

Demo 1：

```
require 'vendor/autoload.php';

$conf['host'] = '127.0.0.1';
$conf['port'] = '27017';
$conf['user'] = '';
$conf['pass'] = '';
$conf['dbname'] = 'test';
$conf['collectionname'] = 'demo';
$mongo = new dedemao\mongodb\MongoDBHelper($conf);
```

Demo 2：
```
include "src/mongoDBHelper.php";

$conf['host'] = '127.0.0.1';
$conf['port'] = '27017';
$conf['user'] = '';
$conf['pass'] = '';
$conf['dbname'] = 'test';
$conf['collectionname'] = 'demo';
$mongo = new dedemao\mongodb\MongoDBHelper($conf);
```

CURD:
```
//Creat：
$result = $mongo->insertOne(['name'=>'John','age'=>'18']);
$result = $mongo->insertMany([['name'=>'John','age'=>'18'],['name'=>'Jay','age'=>'20']]);

//Read：
$row = $mongo->find(['age'=>'18']);
$row = $mongo->findOne(['name'=>'John']);

//Update：
$result = $mongo->update(['name'=>'John'],['$set' => ['age' => '30']]);

//Delete
$result = $mongo->delete(['name'=>'John']);
```
