# mongoDBHelper
A simple mongoDB library for PHP7

# How to use
```
include "mongoDBHelper.php"


$conf['host'] = '127.0.0.1';
$conf['port'] = '27017';
$conf['user'] = 'xxx';
$conf['pass'] = 'xxx';
$conf['dbname'] = 'test';
$conf['collectionname'] = 'demo';
$mongo = new MongoDBHelper($conf);

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
