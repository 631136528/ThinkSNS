<?php
error_reporting(E_ERROR);

/** ///调试、找错时请去掉///前空格
ini_set('display_errors',true);
error_reporting(E_ALL); 
set_time_limit(0);
/**/

//安装检查开始：如果您已安装过ThinkSNS，可以删除本段代码
if(is_dir('install/') && !file_exists('install/install.lock')){
	header("Content-type: text/html; charset=utf-8");
	die ("<div style='border:2px solid green; background:#f1f1f1; padding:20px;margin:20px;width:800px;font-weight:bold;color:green;text-align:center;'>"
		."<h1>您尚未安装ThinkSNS系统，<a href='install/install.php'>请点击进入安装页面</a></h1>"
		."</div> <br /><br />");
}
//安装检查结束

//网站根路径设置
define('SITE_PATH', dirname(__FILE__));

//载入核心文件
require(SITE_PATH.'/core/core.php');

//实例化一个网站应用实例
$App = new App();
$App->run();