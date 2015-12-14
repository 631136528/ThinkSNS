<?php
/**
 * ThinkSNS App基类
 * @author  liuxiaoqing <liuxiaoqing@zhishisoft.com>
 * @version TS3.0
 */
class App
{
    /**
     * App初始化
     * @access public
     * @return void
     */
	static public function init() {
        // 设定错误和异常处理
        set_error_handler(array('App','appError'));
        set_exception_handler(array('App','appException'));
		// Session初始化
        session_start(); 
		// 时区检查
        date_default_timezone_set('PRC');
        // 模版检查
    }

    /**
     * 运行控制器
     * @access public
     * @return void
     */
    static public function run() {

		App::init();

		//API控制器
		if(APP_NAME=='api'){
			App::execApi();

		//Widget控制器
		}elseif(APP_NAME=='widget'){
			App::execWidget();

		//APP控制器
		}else{
			App::execApp();
		}

        return ;
    }

    /**
     * 执行App控制器
     * @access public
     * @return void
     */
	static public function execApp() {

        // 加载所有插件
        if(C('APP_PLUGIN_ON')) {
            tsload(CORE_LIB_PATH.'/addons.class.php');
            tsload(CORE_LIB_PATH.'/addons/Hooks.class.php');
            tsload(CORE_LIB_PATH.'/addons/AbstractAddons.class.php');
            tsload(CORE_LIB_PATH.'/addons/NormalAddons.class.php');
            tsload(CORE_LIB_PATH.'/addons/SimpleAddons.class.php');
            tsload(CORE_LIB_PATH.'/addons/TagsAbstract.class.php');
            Addons::loadAllValidAddons();
        }

        //创建Action控制器实例
		$className =  MODULE_NAME.'Action';
		tsload(APP_ACTION_PATH.'/'.$className.'.class.php');
		
		if(!class_exists($className)) {
          
			$className	=	'EmptyAction';
            tsload(APP_ACTION_PATH.'/EmptyAction.class.php');
            if(!class_exists($className)){
              
                throw_exception( L('_MODULE_NOT_EXIST_').MODULE_NAME );
            }
		}
		
		$module	=	new $className();

		//异常处理
		if(!$module) {
            // 模块不存在 抛出异常
			throw_exception( L('_MODULE_NOT_EXIST_').MODULE_NAME );
        }

        //获取当前操作名
        $action	=	ACTION_NAME;

        //执行当前操作
		call_user_func(array(&$module,$action));

		return ;
    }

    /**
     * 执行Api控制器
     * @access public
     * @return void
     */
    static public function execApi() {
        include_once (ADDON_PATH.'/api/'.MODULE_NAME.'Api.class.php');
        $className = MODULE_NAME.'Api';
        $module = new $className;
        $action = ACTION_NAME;
        //执行当前操作
        $data = call_user_func(array(&$module,$action));
        $format = (in_array( $_REQUEST['format'] ,array('xml','json','php','test') ) ) ?$_REQUEST['format']:'json';
        if($format=='json'){
            exit(json_encode($data));
        }elseif ($format=='xml'){

        }elseif($format=='php'){
            //输出php格式
            exit(var_export($data));
		}elseif($format=='test'){
            //测试输出
            dump($data);
            exit;
        }
        return ;
    }

    /**
     * 执行Widget控制器
     * @access public
     * @return void
     */
    static public function execWidget() {

        //include_once (ADDON_PATH.'/widget/'.MODULE_NAME.'Widget/'.MODULE_NAME.'Widget.class.php');
        //$className = MODULE_NAME.'Widget';
        
        if(file_exists(ADDON_PATH.'/widget/'.MODULE_NAME.'Widget/'.MODULE_NAME.'Widget.class.php')){
            tsload(ADDON_PATH.'/widget/'.MODULE_NAME.'Widget/'.MODULE_NAME.'Widget.class.php');
        }else{

            if(file_exists(APP_PATH.'/Lib/Widget/'.MODULE_NAME.'Widget/'.MODULE_NAME.'Widget.class.php')){
                tsload(APP_PATH.'/Lib/Widget/'.MODULE_NAME.'Widget/'.MODULE_NAME.'Widget.class.php');
            }
        }

        $className = MODULE_NAME.'Widget';

		$module	=	new $className();
      
		//异常处理
		if(!$module) {
            // 模块不存在 抛出异常
			throw_exception( L('_MODULE_NOT_EXIST_').MODULE_NAME );
        }

        //获取当前操作名
        $action	=	ACTION_NAME;

        //执行当前操作
		if($rs = call_user_func(array(&$module,$action))){
			echo $rs;
		}
        return ;
    }

    /**
     * app异常处理
     * @access public
     * @return void
     */
    static public function appException($e) {
        die('system_error:'.$e->__toString());
    }

    /**
     * 自定义错误处理
     * @access public
     * @param int $errno 错误类型
     * @param string $errstr 错误信息
     * @param string $errfile 错误文件
     * @param int $errline 错误行数
     * @return void
     */
    static public function appError($errno, $errstr, $errfile, $errline) {
      switch ($errno) {
          case E_ERROR:
          case E_USER_ERROR:
            $errorStr = "[$errno] $errstr ".basename($errfile)." 第 $errline 行.";
            //if(C('LOG_RECORD')) Log::write($errorStr,Log::ERR);
            echo $errorStr;
            break;
          case E_STRICT:
          case E_USER_WARNING:
          case E_USER_NOTICE:
          default:
            $errorStr = "[$errno] $errstr ".basename($errfile)." 第 $errline 行.";
            //Log::record($errorStr,Log::NOTICE);
            break;
      }
    }

};//类定义结束