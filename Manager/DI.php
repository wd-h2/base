<?php
/**
 * 依赖注入 控制反转
 * Class Di
 * @package LibX
 * @author  penghl@chuchujie.com
 * @date    2016/11/1
 */
namespace base\Manager;

class DI implements \ArrayAccess
{
    private static $_bindings = array();//服务列表

    private static $_instances = array();//已经实例化的服务

    /**
     * @param $name
     * @return mixed|object|null
     * @throws \ReflectionException
     */
    public static function get($name)
    {
        //先从已经实例化的列表中查找
        if(isset(self::$_instances[$name])){
            return self::$_instances[$name];
        }

        //检测有没有注册该服务
        if( ! isset(self::$_bindings[$name])){
            $msg = '<<'.$name.'>> must be set';
            throw new \Exception($msg);
        }
        $concrete = self::$_bindings[$name]['class'];//对象具体注册内容
        $params = self::$_bindings[$name]['params'];//配置
        $obj = null;
        //匿名函数方式
        if($concrete instanceof \Closure){
            $obj = call_user_func_array($concrete,$params);
        }elseif(is_string($concrete)){//字符串方式
            if(empty($params)){
                $obj = new $concrete;
            }else{
                //带参数的类实例化，使用反射
                $class = new \ReflectionClass($concrete);
                $obj = $class->newInstanceArgs($params);
            }
        }
        //如果是共享服务，则写入_instances列表，下次直接取回
        if(self::$_bindings[$name]['shared'] == true && $obj){
            self::$_instances[$name] = $obj;
        }

        return $obj;
    }

    /**
     * 检测是否已经绑定
     *
     * @param $name
     * @return bool
     */
    public static function has($name)
    {
        return isset(self::$_bindings[$name]) or isset(self::$_instances[$name]);
    }

    /**
     * 卸载服务
     *
     * @param $name
     * @return bool
     */
    public static function remove($name)
    {
        unset(self::$_bindings[$name],self::$_instances[$name]);
        return true;
    }

    /**
     * 设置服务
     *
     * @param $name
     * @param $class
     * @param $params
     * @return bool
     */
    public static function set($name, $class, $params = array())
    {
        self::_registerService($name, $class, $params);
        return true;
    }

    /**
     * 设置共享服务
     *
     * @param $name
     * @param $class
     * @param $params
     */
    public static function setShared($name, $class, $params = array())
    {
        self::_registerService($name, $class, $params, true);
    }

    /**
     * 注册服务
     *
     * @param $name
     * @param $class
     * @param $params
     * @param bool|false $shared
     */
    private static function _registerService($name, $class, $params = array(), $shared = false)
    {
        self::remove($name);
        if( ! ($class instanceof \Closure) && is_object($class)){
            self::$_instances[$name] = $class;
        }else{
            self::$_bindings[$name] = array('class'=>$class,'shared'=>$shared,'params'=>$params);
        }
    }

    /**
     * ArrayAccess接口,检测服务是否存在
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return self::has($offset);
    }

    /**
     * ArrayAccess接口,以$di[$name]方式获取服务
     *
     * @param mixed $offset
     * @return mixed|null|object
     * @throws \ReflectionException
     */
    public function offsetGet($offset)
    {
        return self::get($offset);
    }

    /**
     * ArrayAccess接口,以$di[$name]=$value方式注册服务，非共享
     *
     * @param mixed $offset
     * @param mixed $value
     * @return bool
     */
    public function offsetSet($offset, $value)
    {
        return self::set($offset,$value);
    }

    /**
     * ArrayAccess接口,以unset($di[$name])方式卸载服务
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetUnset($offset)
    {
        return self::remove($offset);
    }
}