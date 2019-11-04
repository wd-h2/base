<?php
/**
 *
 * Class Statement
 * @package LibX
 * @author  penghl@chuchujie.com
 * @date    2016/11/7
 */
namespace Curd\Db;

use base\Log\Core;

class Statement
{
    //statement
    protected $pdoStatement = null;

    protected $sql = null;

    protected $bindParam = array();

    //设置默认的获取模式
    protected static $fetchMode = \PDO::FETCH_ASSOC;

    protected $fetch = array('fetch','fetchAll');

    const EXECUTE = 'execute';

    const EXP_REPORT_ID = 1033900000;//pdo异常报警

    const PDO_USE_REPORT_ID = 1033900001;//pdo查询次数

    protected $codeList = array(
        23000   =>  '索引重复'
    );

    public function __construct()
    {

    }

    public function setStatement($pdoStatement, $sql = '')
    {
        $this->pdoStatement = $pdoStatement;
        $this->sql = $sql;
    }

    /**
     * 此函数不能用__call 因第二个参数为引用传值
     *
     * @param $column
     * @param $param    //绑定到 SQL 语句参数的 PHP 变量名。
     * @param int $type
     * @return mixed
     */
    public function bindColumn($column, &$param, $type = null)
    {
        $this->bindParam[] = array(__FUNCTION__,$column,$param,$type);
        if($type === null){
            return $this->pdoStatement->bindColumn($column, $param);
        }else{
            return $this->pdoStatement->bindColumn($column, $param, $type);
        }
    }

    /**
     * 此函数不能用__call 因第二个参数为引用传值
     *
     * @param $column
     * @param $variable     //绑定到 SQL 语句参数的 PHP 变量名。
     * @param int $dataType
     * @return mixed
     */
    public function bindParam($column, &$variable, $dataType = null)
    {
        $this->bindParam[] = array(__FUNCTION__,$column,$variable,$dataType);
        if($dataType === null){
            return $this->pdoStatement->bindParam($column, $variable);
        }else{
            return $this->pdoStatement->bindParam($column, $variable, $dataType);
        }
    }

    /**
     * @param $method
     * @param $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call($method, $arguments)
    {
        if( ! $this->pdoStatement || ! method_exists($this->pdoStatement,$method)){
            $log = array('action'=>'pdoStatement','errorMethod'=>__METHOD__,'method'=>$method,'args'=>$arguments);
            Core::write($log,'pdo_error',0);
            throw new \Exception('method_exists:'.$method);
        }

        if(in_array($method,$this->fetch)){
            $this->pdoStatement->setFetchMode(\PDO::FETCH_ASSOC);
        }

        try {
            $result = call_user_func_array(array($this->pdoStatement, $method), $arguments);
        }catch (\PDOException $e){
            $code = $e->getCode();
            $log = array();
            $log['action'] = 'pdoStatement';
            $log['method'] = $method;
            $log['sql'] = $this->sql;
            $log['args'] = $arguments;
            $log['code'] = $code;
            $log['msg'] = $e->getMessage();
            Core::write($log,'pdo_error',0);
            throw $e;
        }
        return $result;
    }
}