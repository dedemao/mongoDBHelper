<?php
class MongoDBHelper
{
    private static $_manager = null; //所有数据库实例共用,避免重复连接数据库
    private $_host = 'localhost';
    private $_port = 27017;
    private $_user = '';
    private $_pass = '';
    private $_dbName = null; //数据库名
    private $_collectionName = null; //集合名

    /**
     * 初始化类
     * @param array $conf 数据库配置
     */
    public function __construct($conf = [])
    {
        class_exists('MongoDB\Driver\Manager') or die("MongoDB not exists.");
        if (empty($conf)) {
            die("no config");
        }
        $this->_host = isset($conf['host']) ? $conf['host'] : $this->_host;
        $this->_port = isset($conf['port']) ? $conf['port'] : $this->_port;
        $this->_user = isset($conf['user']) ? $conf['user'] : '';
        $this->_pass = isset($conf['pass']) ? $conf['pass'] : '';
        $this->_dbName = $conf['dbname'];
        $this->_collectionName = $conf['collectionname'];
        //连接数据库
        if (is_null(self::$_manager)) {
            $this->_connect();
        }
    }

    /**
     * 连接数据库
     * @throws PDOException
     */
    protected function _connect()
    {
        $uri = 'mongodb://';
        if ($this->_user && $this->_pass) {
            $uri .= $this->_user . ':' . $this->_pass . '@';
        }
        $uri .= $this->_host . ':' . $this->_port . '/' . $this->_dbName;
        try {
            $manager = new MongoDB\Driver\Manager($uri);
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->halt('Connection failed: ' . $e->getMessage());
        }
        self::$_manager = $manager;
    }

    /**
     * 设置当前操作的集合
     *
     * @param $collectionName
     */
    public function setCollection($collectionName)
    {
        $this->_collectionName = $collectionName;
    }

    /**
     * 创建集合
     *
     * @param $collectionName
     */
    public function createCollection($collectionName)
    {
        try {
            $command = new MongoDB\Driver\Command([
                'create' => (string)$collectionName,
            ]);
            $cursor = self::$_manager->executeCommand($this->_dbName, $command);
            $response = $cursor->toArray()[0];
            return $response->ok == 1;
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->halt('error: ' . $e->getMessage());
        }
        return $cursor->toArray();
    }

    /**
     * 删除集合
     *
     * @param $collectionName
     */
    public function dropCollection($collectionName)
    {
        try {
            $command = new MongoDB\Driver\Command([
                'drop' => (string)$collectionName,
            ]);
            $cursor = self::$_manager->executeCommand($this->_dbName, $command);
            $response = $cursor->toArray()[0];
            return $response->ok == 1;
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->halt('error: ' . $e->getMessage());
        }
        return $cursor->toArray();
    }

    /**
     * 查询数据
     * @param array $filter 查询条件
     * @param array $options 使用投影操作符指定返回的键
     * @param array $sort 排序
     */
    public function find($filter = [], $options = [], $sort = [])
    {
        $this->checkArgument();

        if (empty($options)) {
            $options = [
                'projection' => ['_id' => 0],
//                'sort' => ['age' => 1],
            ];
        }
        $query = new MongoDB\Driver\Query($filter, $options);
        try {
            $cursor = self::$_manager->executeQuery($this->_dbName . '.' . $this->_collectionName, $query);
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            $this->halt('invalid argument: ' . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->halt('error: ' . $e->getMessage());
        }
        return $cursor->toArray();
    }

    /**
     * 查询一行数据
     * @param array $filter 查询条件
     * @param array $options 使用投影操作符指定返回的键
     * @param array $sort 排序
     */
    public function findOne($filter = [], $options = [], $sort = [])
    {
        $result = current($this->find($filter, $options, $sort));
		return is_bool($result) ? $result : (array) $result;
    }

    /**
     * 更新文档
     *
     * @param array $query update的查询条件，类似sql update查询内where后面的
     * @param array $update update的对象和一些更新的操作符（如$,$inc...）等，也可以理解为sql update查询内set后面的
     * @param bool $multi 只更新找到的第一条记录，如果这个参数为true,就把按条件查出来多条记录全部更新
     * @param bool $upsert 如果不存在update的记录，是否插入objNew,true为插入，默认是false，不插入
     */
    public function update($query, $update, $multi = false, $upsert = false)
    {
        $this->checkArgument();

        try {
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->update($query, $update,
                ['multi' => $multi, 'upsert' => $upsert]
            );
            $writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 1000);
            $result = self::$_manager->executeBulkWrite($this->_dbName . '.' . $this->_collectionName, $bulk, $writeConcern);
            //https://www.php.net/manual/zh/class.mongodb-driver-writeresult.php
            if ($result->getModifiedCount() > 0 || $result->getMatchedCount() > 0) {
                return true;
            }
            if ($result->getUpsertedCount() > 0) {
                return true;
            }
            return false;
        } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
            var_dump($e->getWriteResult()->getWriteErrors());
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            $this->halt('invalid argument: ' . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->halt('error: ' . $e->getMessage());
        }
    }

    public function insertOne($data)
    {
        $this->checkArgument();

        try {
            $bulk = new MongoDB\Driver\BulkWrite;
            $_id = $bulk->insert($data);
            $writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 1000);
            $result = self::$_manager->executeBulkWrite($this->_dbName . '.' . $this->_collectionName, $bulk, $writeConcern);
            return $result->getInsertedCount() > 0 ? $_id : false;
        } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
            $this->halt('insert error: ' . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            $this->halt('invalid argument: ' . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->halt('error: ' . $e->getMessage());
        }
    }

    public function insertMany($datas)
    {
        $this->checkArgument();

        try {
            $bulk = new MongoDB\Driver\BulkWrite;
            foreach ($datas as $data) {
                $bulk->insert($data);
            }
            $writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 1000);
            $result = self::$_manager->executeBulkWrite($this->_dbName . '.' . $this->_collectionName, $bulk, $writeConcern);
            return $result->getInsertedCount() > 0 ? true : false;
        } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
            $this->halt('insert error: ' . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            $this->halt('invalid argument: ' . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->halt('error: ' . $e->getMessage());
        }
    }

    /**
     * 删除文档
     *
     * @param $query  删除的文档的条件
     * @param int $justOne 如果设为 true 或 1，则只删除一个文档，如果不设置该参数，或使用默认值 false，则删除所有匹配条件的文档。
     * @return bool
     */
    public function delete($query, $justOne = 0)
    {
        $this->checkArgument();
        try {
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->delete($query, ['limit' => $justOne]);

            $writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 1000);
            $result = self::$_manager->executeBulkWrite($this->_dbName . '.' . $this->_collectionName, $bulk, $writeConcern);
            return $result->getDeletedCount() > 0;
        } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
            $this->halt('delete error: ' . $e->getWriteResult()->getWriteErrors());
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            $this->halt('invalid argument: ' . $e->getMessage());
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->halt('error: ' . $e->getMessage());
        }
    }

    /**
     * 检查参数
     */
    private function checkArgument()
    {
        try {
            if (!$this->_dbName) {
                throw new MongoDB\Driver\Exception\InvalidArgumentException("database name is empty");
            }
            if (!$this->_collectionName) {
                throw new MongoDB\Driver\Exception\InvalidArgumentException("collection_name is empty");
            }
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            $this->halt('invalid argument: ' . $e->getMessage());
        }
    }

    /**
     * 打印错误
     *
     * @param string $message
     * @param string $sql
     */
    public function halt($message = '')
    {
        exit('<div style="display:block">' . $message . '</div>');
    }
}