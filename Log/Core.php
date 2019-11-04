<?php
/*
  +----------------------------------------------------------------------+
  | example                                                              |
  +----------------------------------------------------------------------+
  |  日志配置：完全与框架无关 即插即用										 |
  |     日志的路径、日志的名字、日志级别                                      |
  +----------------------------------------------------------------------+
  |                                                                      |
  +----------------------------------------------------------------------+
*/
namespace base\Log;

class Core
{
    protected static $_baseDir = null;

    //上报HOST
    protected static $_reportHost = null;

    //上报端口
    protected static $_reportPort = null;

    protected static $_suffix = '.log';

    /**
     * @brief 系统日志配置信息
     * @param string $host 上报host
     * @param int $port 上报端口
     * @param string $_baseDir 日志根目录(绝对路径)
     */
    public static function initConfig(string $host, int $port,string $_baseDir)
    {
        self::$_reportHost      = $host;
        self::$_reportPort      = $port;
        self::$_baseDir         = $_baseDir;
    }

    /**
     * 返回日志根目录
     *
     * @return string
     */
    private static function _logDir()
    {
        $ds = DIRECTORY_SEPARATOR;
        if(empty(self::$_baseDir)){
            self::$_baseDir = dirname(__DIR__).$ds.'Logs'.$ds;
        }
        return self::$_baseDir;
    }

    /**
     * @param $msg
     * @param string $level
     * @param int $index
     * @throws \Exception
     */
    public static function write($msg,$level = 'info', $index = 3)
    {
        $logPath = self::_logDir();
        $business = 'yaf-base';
        $msg = is_string($msg) ? $msg : json_encode($msg,JSON_UNESCAPED_UNICODE);
        Logger::write($logPath,$business,$msg, $level, $index);
    }
}