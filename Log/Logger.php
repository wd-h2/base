<?php

namespace base\Log;

class Logger
{

    //日志级别
    const DEBUG     =   0X00;
    const INFO      =   0X02;
    const NOTICE    =   0X04;
    const WARNING   =   0X08;
    const ERROR     =   0X10;
    const CRITICAL  =   0X20;
    const ALERT     =   0X40;
    const EMERGENCY =   0X80;

    //日志级别中文对应
    protected static $arrLevels = array(
        0X00    =>  'DEBUG',
        0X02    =>  'INFO',
        0X04    =>  'NOTICE',
        0X08    =>  'WARNING',
        0X10    =>  'ERROR',
        0X20    =>  'CRITICAL',
        0X40    =>  'ALERT',
        0X80    =>  'EMERGENCY'
    );

    //开启web跟踪
    const WEB_TRACE_ON  =   true;

    //开启debug跟踪
    const BACK_TRACE_ON =   true;

    //一次请求里多条日志的一个唯一的ID
    private static $_logId = null;

    //日志时间格式
    private static $_logSuffix = null;


    /**
     * 日志写入
     *
     * @param string $logPath  日志根目录
     * @param string $business 业务名
     * @param string $msg
     * @param string $level
     * @param int $index debug_trace 截取数组个数
     * @return bool
     * @throws \Exception
     */
    public static function write($logPath,$business = 'yaf-base',$msg = '', $level = 'info', $index = 0)
    {
        if($msg){
            if ( ! is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }
            $filename = strtoupper($level);
            //$trace = self::_getBackTrace($index);
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if($index){
                $trace = array_slice($trace,$index);
            }
            $trace = array(
                'file'      =>  isset($trace[1]) && isset($trace[1]['file']) ? $trace[1]['file'] : null,
                'line'      =>  isset($trace[1]) && isset($trace[1]['line']) ? $trace[1]['line'] : null,
                'class'     =>  isset($trace[2]) && isset($trace[2]['class']) ? $trace[2]['class'] : null,
                'func'      =>  isset($trace[2]) && isset($trace[2]['function']) ? $trace[2]['function'] : null,
            );
            $log = array();
            $log['level'] = $filename;
            $log['logId'] = self::genLogId();
            $log['business'] = $business;
            $log['c_time'] = date('Y-m-d H:i:s');
            $log['http_host'] = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            $log['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            $log['request_uri'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            $log['post'] = isset($_POST) ? json_encode($_POST,JSON_UNESCAPED_UNICODE) : json_encode(array());
            $log['client_ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $log['local_ip'] = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
            $log['func'] = isset($trace['func']) ? $trace['class'].':'.$trace['func'].':'.$trace['line'] : '';
            $log['func'] .= '[file: '.$trace['file'].'; line:'.$trace['line'].']';
            $log['user_id'] = (isset($GLOBALS['userId']) && is_numeric($GLOBALS['userId'])) ? $GLOBALS['userId'] : -1;
            $log['msg'] = is_string($msg) ? $msg : json_encode($msg,JSON_UNESCAPED_UNICODE);
            $strLog = implode('-==-', $log);

            $strLog = str_replace(array("\r", "\n", "\r\n"), ' ', $strLog);

            $logContent = $strLog.PHP_EOL;

            $strLogFile = rtrim($logPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename.'_log_'.date('Y-m-d');
            if(self::$_logSuffix){
                $strLogFile = rtrim($logPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename.'_log_'.self::$_logSuffix;
            }
            $objLog = fopen($strLogFile,'a');
            if ( false === $objLog || !is_resource($objLog) ) {
                throw new \Exception("log file {$strLogFile} open failed!");
            }
            if ( false === fwrite($objLog, $logContent) ) {
                throw new \Exception("log file {$strLogFile} write failed!");
            }
            fclose($objLog);
            return true;
        }else{
            throw new \Exception("logMsg is empty!");
        }
    }

    /**
     * @brief 创建唯一的序列化字段logId,主要为了查出一次请求中的所有log
     */
    public static function genLogId()
    {
        if ( !self::$_logId ) {
            $str = ((mt_rand() << 1) | (mt_rand() & 1) ^ intval(microtime(true)));
            $logId = strtoupper(base_convert($str, 10, 36));
            //补齐六位
            self::$_logId = str_pad($logId, 6, 'X');
        }
        return self::$_logId;
    }

}