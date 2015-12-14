<?php
/* 
	load: css, js, image 等静态文件 
	启用 gz压缩、缓存处理、过期处理、文件合并等优化操作
*/
if(extension_loaded('zlib')){
	//检查服务器是否开启了zlib拓展
	ob_start('ob_gzhandler');
}

$allowed_content_types	=	array('js','css');

//解析参数
$gettype	=	strip_tags($_GET['t']);
if($gettype=='css'){
	$content_type	=	'text/css';
}elseif($gettype=='js'){
	$content_type	=	'application/x-javascript';
}
$charset	=	isset($_GET['charset'])? strip_tags($_GET['charset']) :'utf-8';
$getfiles	=	explode(',',strip_tags($_GET['f']));

header ("content-type: ".$content_type."; charset: ".$charset);		//注意修改到你的编码
header ("cache-control: must-revalidate");				//
header ("expires: " . gmdate ("D, d M Y H:i:s", time() + 60 * 60 * 24) . " GMT");	//过期时间

ob_start("compress");

function compress($buffer) {//去除文件中的注释
	$buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
	return $buffer;
}

foreach($getfiles as $file){
	//包含你的全部css文档
	include($file);
}

//输出buffer中的内容，即压缩后的css文件
if(extension_loaded('zlib')){
	ob_end_flush();
}
?>