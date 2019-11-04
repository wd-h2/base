<?php

namespace base\Db;

use base\Log\Core;

class Pdo
{
    //db 配置
    protected $config = array();

    //db 对象
    protected $db = null;

    //是否开始事物
    protected $isTran = false;

    //db池
    protected $dbPool = array();

    //是否强行查询主库
    protected $useMaster = false;

    protected $sql = null;

    protected $_pk = 'id';//主键id

    protected $_table = null;

    //主库标识
    const M = 'master';

    //从库标识
    const S = 'slave';

    //读取
    const SE = 'SELECT';

    //method
    const Q = 'QUERY';

    const PDO_ERROR = 'pdo_error';

    protected static $statement = null;

    //执行sql语句方法
    protected static $sqlMethod = array('exec','query','prepare');

    //sql
    protected static $logMethod = array('exec','query');

    protected $fields = array();

    protected $where = array();

    protected $sets = array();

    protected $bind = array();//where

    protected $setBind = array();//update

    protected $orderBy = '';

    protected $offset = 0;

    protected $limit = 0;

    protected $lock = false;

    protected $columnBindNum = array();//同一字段绑定次数

    protected $rawStr = '';//聚合函数

    const PDO_CONNECT_ERROR_REPORT_ID = 1033900002;//pdo连接错误

    /**
     * 配置
     *
     * Pdo constructor.
     * @param array $config
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        if ( ! class_exists('\PDO')) {
            throw new \Exception('class PDO is not exists');
        }
        $this->config = $config;
    }

    /**
     * @param string $sql
     * @return $this
     */
    public function setSql($sql){
        $this->sql = $sql;
        return $this;
    }

    /**
     * 返回单条
     */
    public function queryOne(){
        $sth = $this->prepare($this->sql);
        $sth->execute();
        $res = $sth->fetch();
        return $res;
    }

    /**
     * 返回多条
     */
    public function queryAll(){
        $sth = $this->prepare($this->sql);
        $sth->execute();
        $list = $sth->fetchAll();
        return $list;
    }

    /**
     * 排序
     *
     * @param $orderBy
     * @return $this
     */
    public function orderBy($orderBy){
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * 分段查询
     *
     * @param $limit
     * @return $this
     */
    public function limit($limit){
        if(is_numeric($limit) && $limit > 0) {
            $this->limit = $limit;
        }
        return $this;
    }

    /**
     * 分段查询
     *
     * @param $offset
     * @return $this
     */
    public function offset($offset){
        if(is_numeric($offset) && $offset >= 0) {
            $this->offset = $offset;
        }
        return $this;
    }

    /**
     * @param bool $isLock
     * @return $this
     */
    public function lock($isLock = true){
        $this->lock = $isLock;
        return $this;
    }

    /**
     * @param string    $column
     * @param mixed     $operator
     * @param mixed     $value
     * @return $this
     */
    public function where($column, $operator = null, $value = null){
        $num = func_num_args();
        if($num == 2){
            $this->where[] = '`'.$column.'` = ?';
            $this->bind[] = $operator;
        }else{
            $this->where[] = '`'.$column.'` '.$operator.' ?';
            $this->bind[] = $value;
        }
        return $this;
    }

    /**
     * @param $column
     * @param $start
     * @param $end
     * @return $this
     */
    public function between($column,$start,$end){
        $this->where[] = '`'.$column.'` BETWEEN ? AND ?';
        $this->bind[] = $start;
        $this->bind[] = $end;
        return $this;
    }

    /**
     * @param $column
     * @param array $value
     * @return $this
     */
    public function In($column, array $value = []){

        if (count($value) < 1) {
            return $this;
        }

        $_set = [];
        foreach($value as $val) {
            $_set[] = '?';
            $this->bind[] = $val;
        }

        $this->where[] = '`' . $column . '` IN (' . implode(',', $_set) . ')';
        return $this;
    }

    /**
     * @param $column
     * @param array $value
     * @return $this
     */
    public function whereIn($column, array $value = []){
        if (count($value) < 1) {
            return $this;
        }

        $_set = [];
        foreach($value as $val) {
            $_set[] = '?';
            $this->bind[] = $val;
        }

        $this->where[] = '`' . $column . '` IN (' . implode(',', $_set) . ')';
        return $this;
    }

    /**
     * @param $column
     * @param array $value
     * @return $this
     */
    public function whereNotIn($column, array $value = []){
        if (count($value) < 1) {
            return $this;
        }

        $_set = [];
        foreach($value as $val) {
            $_set[] = '?';
            $this->bind[] = $val;
        }

        $this->where[] = '`' . $column . '` NOT IN (' . implode(',', $_set) . ')';
        return $this;
    }

    /**
     * multiWhere
     *
     * @param array $multiWhere
     * @return $this
     */
    public function multiWhere(array $multiWhere) {
        foreach($multiWhere as $item) {
            if (count($item) > 2) {
                $this->where($item[0],$item[1],$item[2]);
            } else {
                $this->where($item[0], '=', $item[1]);
            }
        }
        return $this;
    }

    /**
     * multiSave
     *
     * @param array $multiData
     * @param array $multiWhere
     * @return array
     * @throws \Exception
     */
    public function multiSave(array $multiData, array $multiWhere = []) {

        foreach($multiData as $field => $val) {
            $this->set($field, $val);
        }

        if ($multiWhere) {
            $this->multiwhere($multiWhere);
        }
        return $this->save();
    }

    /**
     * 生成sql语句
     *
     * @throws \Exception
     */
    protected function generateSql(){

        if ($this->fields) {
            $fields = implode(',', $this->fields);
            $sql = 'SELECT '.$fields.' FROM '.$this->_table;
        } else if ($this->rawStr) {
            $sql = 'SELECT '.$this->rawStr.' FROM '.$this->_table;
        }else{
            $sql = 'SELECT * FROM '.$this->_table;
        }

        if(empty($this->where)){
            throw new \Exception('查询条件不能为空');
        }

        $where = implode(' AND ',$this->where);
        $sql .= ' WHERE '.$where;

        if($this->lock){
            $sql .= ' FOR UPDATE ';
        }

        if($this->orderBy){
            $sql .= ' ORDER BY '.$this->orderBy;
        }

        if($this->limit > 0){
            $sql .= ' LIMIT '.$this->offset.','.$this->limit;
        }

        $this->sql = $sql;
    }

    /**
     * 制定字段
     *
     * @param array $fields
     * @return $this
     */
    public function field(array $fields = array()){
        foreach($fields as $field) {
            $this->fields[] = '`'.$field.'`';
        }
        return $this;
    }

    /**
     * 单条查询
     *
     * @return mixed
     * @throws \Exception
     *
     */
    public function get(){
        $this->generateSql();
        $sth = $this->prepare($this->sql);
        $param = $this->bind;
        $sth->execute($param);
        $res = $sth->fetch();
        $this->clear();
        return $res;
    }

    /**
     * 多条查询
     *
     * @return mixed
     * @throws \Exception
     */
    public function select(){
        $this->generateSql();
        $sth = $this->prepare($this->sql);
        $param = $this->bind;
        $sth->execute($param);
        $list = $sth->fetchAll();
        $this->clear();
        return $list;
    }

    /**
     * 返回数量
     */
    public function count(){
        $sql = 'SELECT count(*) as count FROM '.$this->_table;
        if(empty($this->where)){
            throw new \Exception('查询条件不能为空');
        }

        $where = implode(' AND ',$this->where);
        $sql .= ' WHERE '.$where;
        $this->sql = $sql;
        $sth = $this->prepare($this->sql);
        $param = $this->setBind;
        foreach($this->bind as $item){
            $param[] = $item;
        }
        $sth->execute($param);
        $res = $sth->fetch();
        $this->clear();
        return $res['count'];
    }

    /**
     * 原生聚合函数
     *
     * @param $str
     * @return $this
     * @author penghl@chuchujie.com
     * @date 2019/5/10 10:13
     */
    public function raw($str){
        $this->rawStr = $str;
        return $this;
    }

    /**
     * 插入数据
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function insert(array $data){
        if( is_array($data) ){
            $sql = 'INSERT INTO '.$this->_table.'(';
            $value = ' VALUES (';
            foreach($data as $k=>$v){
                if( is_array($v) ){
                    throw new \Exception('插入数据错误');
                }
                $sql .= "`{$k}`,";
                $value .= "?,";
                $this->bind[] = $v;
            }
            $this->sql = rtrim($sql,',') . ')' . rtrim($value,',') . ')';
            $sth = $this->prepare($this->sql);
            $status = $sth->execute($this->bind);
            $insertId =  $this->db->lastInsertId();
            $rowCount = $sth->rowCount();
            $this->clear();
            return array('status'=>$status,'insertId'=>$insertId,'rowCount'=>$rowCount);
        } else {
            throw new \Exception('插入数据错误');
        }
    }

    /**
     * 批量写入 惠东编写
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function insertAll(array $data){
        if( is_array($data) ){
            $sql = 'INSERT INTO '.$this->_table.'(';
            $values = ' VALUES ';
            $fields = array();
            foreach($data as $key=>$value){
                if( !is_array($value) ){
                    throw new \Exception('插入数据错误');
                }
                $fields = array_keys($value);
                $values .= '(';
                foreach ($value as $k => $v){
                    $values .= "?,";
                    $this->bind[] = $v;
                }
                $values = rtrim($values,',');
                $values .= '),';
            }
            foreach ($fields as $field){
                $sql .= "`{$field}`,";
            }
            $this->sql = rtrim($sql,',') . ')' . rtrim($values,',');
            $sth = $this->prepare($this->sql);
            $status = $sth->execute($this->bind);
            $rowCount = $sth->rowCount();
            $this->clear();
            return array('status'=>$status,'rowCount'=>$rowCount);
        } else {
            throw new \Exception('插入数据错误');
        }
    }

    /**
     * 删除
     *
     * @return array
     * @throws \Exception
     */
    public function del(){
        $sql = 'DELETE FROM '.$this->_table;
        if(empty($this->where)){
            throw new \Exception('删除条件不能为空');
        }
        $where = implode(' AND ',$this->where);
        $sql .= ' WHERE '.$where;
        $this->sql = $sql;
        $sth = $this->prepare($this->sql);
        $param = $this->bind;
        $status = $sth->execute($param);
        $rowCount = $sth->rowCount();
        $this->clear();
        return array('status'=>$status,'rowCount'=>$rowCount);
    }

    /**
     * set字段
     *
     * @param string    $column
     * @param mixed     $value
     * @return $this
     */
    public function set($column, $value){
        $this->sets[] = '`' . $column . '` =  ?';
        $this->setBind[] = $value;
        return $this;
    }

    /**
     * 更新
     *
     * @return array
     * @throws \Exception
     */
    public function save(){
        $sql = 'UPDATE '.$this->_table;
        if(empty($this->where)){
            throw new \Exception('更新条件不能为空');
        }
        if(empty($this->sets)){
            throw new \Exception('更新内容不能为空');
        }
        $sets = implode(' , ',$this->sets);
        $sql .= ' SET '.$sets;

        $where = implode(' AND ',$this->where);
        $sql .= ' WHERE '.$where;
        $this->sql = $sql;
        $sth = $this->prepare($this->sql);
        $param = $this->setBind;
        foreach($this->bind as $item){
            $param[] = $item;
        }
        $status = $sth->execute($param);
        $rowCount = $sth->rowCount();
        $this->clear();
        return array('status'=>$status,'rowCount'=>$rowCount);
    }


    /**
     * 属性初始化
     *
     * @return $this
     */
    protected function clear(){
        $this->fields = array();
        $this->where = array();
        $this->sets = array();
        $this->bind = array();
        $this->setBind = array();
        $this->orderBy = '';
        $this->limit = 0;
        $this->offset = 0;
        $this->lock = false;
        $this->rawStr = '';
        return $this;
    }

    /**
     * 获取sql
     *
     * @return string
     */
    public function getLastSql(){
        return $this->sql;
    }

    /**
     * 创建PDO连接
     *
     * @param $config
     * @return null|\PDO
     * @throws \Exception
     */
    protected function connect($config)
    {
        $host = isset($config['host']) ? $config['host'] : '';
        $port = isset($config['port']) ? $config['port'] : 3306;
        $dbname = isset($config['dbname']) ? $config['dbname'] : '';
        $username = isset($config['username']) ? $config['username'] : '';
        $password = isset($config['password']) ? $config['password'] : '';
        $charset = isset($config['charset']) ? $config['charset'] : 'utf8';
        $options = isset($config['options']) ? $config['options'] : array();
        if (empty($host) || empty($username) || empty($dbname)) {
            throw new \Exception('configIsError:'.json_encode($config));
        }
        $link = null;
        try {
            $startTime = microtime(true);
            $dsn = 'mysql:host='.$host.';port='.$port.';dbname='.$dbname.';charset='.$charset;
            //设置连接超时时间为2秒
            $options[\PDO::ATTR_TIMEOUT] = isset($options[\PDO::ATTR_TIMEOUT]) ? $options[\PDO::ATTR_TIMEOUT] : 2;
            //设置PDO的错误处理模式
            $options[\PDO::ATTR_ERRMODE] = isset($options[\PDO::ATTR_ERRMODE]) ? $options[\PDO::ATTR_ERRMODE] : \PDO::ERRMODE_EXCEPTION;
            //设置字符集
            $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES '.$charset;
            $link = new \PDO($dsn, $username, $password,$options);
            $endTime = microtime(true);
            $runTime = 1000 * floatval($endTime - $startTime);
            $runTime = sprintf('%.2f',$runTime);
            unset($config['username'],$config['password']);
            //连接时间超过100毫秒则记录日志
            if($runTime > 100){
                $log = array('action'=>'pdoConnect','config'=>$config,'runTime'=>$runTime);
                Core::write($log,'info',0);
            }
            if(version_compare(PHP_VERSION,'5.3.6') <= 0){
                $link->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false); //禁用prepared statements的仿真效果
            }
            //$link->exec('SET character_set_connection='.$charset.', character_set_results='.$charset.', character_set_client=binary');
        } catch (\PDOException $e) {
            unset($config['username'],$config['password']);
            $log = array('action'=>'pdoConnectFail','config'=>$config,'PDOExceptionCode'=>$e->getCode());
            Core::write($log,self::PDO_ERROR,0);
            throw $e;
        }
        return $link;
    }

    /**
     * 获取相应连接
     *
     * @param string $type
     * @return null|\PDO
     * @throws \Exception
     */
    protected function getCon($type = self::S)
    {
        //连接已创建则直接返回
        if (isset($this->dbPool[$type])) {
            $num = count($this->dbPool[$type]);
            $index = ($num > 1) ? mt_rand(0,$num-1) : 0;
            $this->db = $this->dbPool[$type][$index];
            return $this->db;
        }
        //创建新的连接池
        if ( ! isset($this->config[$type])) {
            throw new \Exception('PdoConfigIsError,confPatternError');
        }
        $config = $this->config[$type];
        $hostArr = explode(',',$config['host']);
        shuffle($hostArr);
        foreach ($hostArr as $host) {
            $conf = $config;
            $conf['host'] = $host;
            $link = $this->connect($conf);
            if($link){
                $this->dbPool[$type][] = $link;
                break;
            }
        }
        $link = null;
        if (isset($this->dbPool[$type])) {
            $this->db = $this->dbPool[$type][0];
        }
        return $this->db;
    }

    /**
     * 判断主从库
     *
     * @param $method
     * @param $arguments
     * @return bool
     */
    protected function isUseMaster($method, $arguments)
    {
        $userMaster = false;
        if (in_array($method,self::$sqlMethod)) {
            $this->sql = (isset($arguments[0]) && is_string($arguments[0])) ? ltrim($arguments[0]) : '';
        }
        //todo 开启事物后不能切换从库查询! 切换从库查询需在开启事物之前
        if ($this->isTran || $this->useMaster) {
            $this->useMaster = false;
            $userMaster = true;
            return $userMaster;
        }

        $sql = strtoupper($this->sql);
        $sql = trim($sql);
        //非查询语句则用主库
        if ($sql && strpos($sql,self::SE) !== 0) {
            $userMaster = true;
        }
        $this->useMaster = false;
        return $userMaster;
    }

    /**
     * @param $method
     * @param $arguments
     * @return Statement|mixed|null
     * @throws \Exception
     */
    public function __call($method, $arguments)
    {
        // TODO: Implement __call() method.
        $isMaster = $this->isUseMaster($method, $arguments);
        if ($isMaster) {
            $this->getCon(self::M);
        } else {
            $this->getCon(self::S);
        }
        if ( ! is_object($this->db) || ! method_exists($this->db,$method)) {
            $log = array('action'=>'PDO','errorMethod'=>__METHOD__,'method'=>$method,'args'=>$arguments);
            Core::write($log,self::PDO_ERROR,0);
            throw new \Exception('dbConnectFail,method:'.$method.',args:'.json_encode($arguments));
        }

        $result = call_user_func_array(array($this->db,$method),$arguments);
        if ($result instanceof \PDOStatement) {
            if ( ! (self::$statement instanceof Statement)) {
                self::$statement = new Statement();
            }
            self::$statement->setStatement($result,$this->sql);
            return self::$statement;
        }
        return $result;
    }

    /**
     * 切换主库
     *
     * @return $this
     */
    public function forceMaster()
    {
        $this->useMaster = true;
        return $this;
    }

    /**
     * 切换从库 暂误调用
     *
     * @return $this
     */
    public function forceSlave()
    {
        $this->useMaster = false;
        return $this;
    }

    /**
     * 开始事物
     *
     * @return bool
     * @throws \Exception
     */
    public function beginTransaction()
    {
        if ( ! $this->db|| !$this->isTran) {
            $this->getCon(self::M);
        }
        if($this->isTran){
            return true;
        }
        $status = $this->db->beginTransaction();
        if ($status) {
            $this->isTran = true;
        }
        return $status;
    }

    /**
     * 提交事物
     *
     * @return bool
     */
    public function commit()
    {
        if ( ! $this->db) {
            throw new \PDOException('dbIsNull');
        }
        $status = $this->db->commit();
        if ($status) {
            $this->isTran = false;
        }
        return $status;
    }

    /**
     * 回滚事物
     *
     * @return mixed
     */
    public function rollBack()
    {
        if ( ! $this->db) {
            throw new \PDOException('dbIsNull');
        }
        $status = $this->db->rollBack();
        if ($status) {
            $this->isTran = false;
        }
        return $status;
    }

    /**
     * 设置表名
     *
     * @param $table
     * @return $this
     */
    public function setTable($table) {
        $this->_table = '`' .$table. '`';
        return $this;
    }

    /**
     * 删除连接池 脚本延迟运行导致失去连接时可用
     *
     * @return $this
     */
    public function resetDbPool()
    {
        $this->dbPool = array();
        return $this;
    }

    /**
     * 关闭连接
     *
     * @return $this
     */
    public function close()
    {
        if ($this->dbPool) {
            $this->dbPool = null;
        }
        return $this;
    }
}