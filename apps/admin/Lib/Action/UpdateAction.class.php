<?php
class UpdateAction extends AdministratorAction {
	var $updateURL = '';
	// var $updateURL = 'http://192.168.1.100/ts3';
	function _initialize() {
		parent::_initialize ();
		set_time_limit ( 0 );
		
		$this->updateURL = C ( 'TS_UPDATE_URL' );
	}
	function index() {
		$this->display ();
	}
	function checkVersionByAjax() {
		// 取当前版本号
		$path = DATA_PATH . '/update';
		$versionArr = F ( 'versions', '', $path );
		if (! $versionArr) {
			$versionArr [0] = array ();
		}
		$keyArr = array_keys ( $versionArr );
		
		// 取官方最新版本信息
		$url = $this->updateURL . '/versions.txt';
		$remote = file_get_contents ( $url );
		$remote = json_decode ( $remote, true );
		$newArr = array_keys ( $remote );
		
		$diff = array_diff ( $newArr, $keyArr );
		
		foreach ( $diff as $d ) {
			$versionArr [$d] = $remote [$d];
			$this->_writeVersion ( $d, $remote [$d] );
		}
		
		$key = 0;
		foreach ( $versionArr as $k => $vo ) {
			if ($vo ['status'] == 2 ) continue;
			
			if($key==0) $key = $k;
			else if($key>$k) $key = $k;
		}
		if($key!=0)
		   $list [$key] = $versionArr [$key];
		
		$this->assign ( 'list', $list );
		$this->display ();
	}
	function download() {
		header ( "content-Type: text/html; charset=utf-8" );
		
		// 更新当前版本为升级中的版本状态
		$this->_updateVersionStatus ( t ( $_GET ['key'] ) );
		
		$packageName = t ( $_GET ['packageName'] );
		// $packageName = jiemi ( $packageName );
		
		$packageURL = $this->updateURL . '/' . $packageName;
		// $packageURL = 'http://ts3up.b0.upaiyun.com/public.zip';
		
		$localPath = DATA_PATH . '/update/download/';
		// 目录不存在则创建
		if (! is_dir ( $localPath ))
			$res = mkdir ( $localPath, 0777, true );
		
		$file = fopen ( $packageURL, "rb" );
		if (! $file) {
			echo 0;
			exit ();
		}
		
		$newfname = $localPath . basename ( $packageURL );
		$newf = fopen ( $newfname, "wb" );
		if (! $newf) {
			echo - 1;
			exit ();
		}
		
		while ( ! feof ( $file ) ) {
			$data = fread ( $file, 1024 * 8 ); // 默认获取8K
			fwrite ( $newf, $data, 1024 * 8 );
			ob_flush ();
			flush ();
		}
		fclose ( $file );
		fclose ( $newf );
		echo 1;
	}
	function unzipPackage() {
		$targetDir = DATA_PATH . '/update/download/unzip';
		
		$this->_rmdirr ( $targetDir );
		
		$packageName = t ( $_GET ['packageName'] );
		
		// $packageName = jiemi ( $packageName );
		
		$package = DATA_PATH . '/update/download/' . $packageName;
		// $package = DATA_PATH . '/update/download/public.zip';
		// dump($package);exit;
		require_once ADDON_PATH . '/library/pclzip-2-8-2/pclzip.lib.php';
		$archive = new PclZip ( $package );
		$res = $archive->extract ( PCLZIP_OPT_PATH, $targetDir, PCLZIP_OPT_REPLACE_NEWER );
		
		if ($res) {
			echo 1;
		} else {
			echo $archive->errorInfo ( true );
		}
	}
	// 关闭站点，并设置关闭原因
	function closeSite() {
		$data = model ( 'Xdata' )->get ( 'admin_Config:site' );
		
		$config ['site_closed'] = $data ['site_closed'];
		$config ['site_closed_reason'] = $data ['site_closed_reason'];
		
		// 保存当前站点的配置关闭原因
		F ( 'site_config', $config, DATA_PATH . '/update' );
		
		$data ['site_closed'] = 0;
		$data ['site_closed_reason'] = '站点升级中...请稍后再访问。';
		
		model ( 'Xdata' )->put ( 'admin_Config:site', $data );
	}
	// 恢复升级前的站点配置
	function openSite() {
		$config = F ( 'site_config', '', DATA_PATH . '/update' );
		if (empty ( $config )) {
			return false;
		}
		
		$data = model ( 'Xdata' )->get ( 'admin_Config:site' );
		$data ['site_closed'] = $config ['site_closed'];
		$data ['site_closed_reason'] = $config ['site_closed_reason'];
		
		model ( 'Xdata' )->put ( 'admin_Config:site', $data );
	}
	// 清除文件缓存
	function cleanCache() {
		$this->_rmdirr ( CORE_RUN_PATH . '/' );
	}
	function dealSQL() {
		// 执行前先关闭站点
		$this->closeSite ();
		
		$filePath = $targetDir = DATA_PATH . '/update/download/unzip/updateDB.php';
		if (! file_exists ( $filePath )) { // 如果本次升级没有数据库的更新，直接返回
			echo 1;
			exit ();
		}
		
		require_once ($filePath);
		$res = updateDB ();
		
		unlink ( $filePath );
		
		// 数据库验证
		$filePath = $targetDir = DATA_PATH . '/update/download/unzip/checkDB.php';
		if (! file_exists ( $filePath )) { // 如果本次升级没有数据库的更新后的验证代码，直接返回
			echo 1;
			exit ();
		}
		
		require_once ($filePath);
		// checkDB方法正常返回1 否则返回异常的说明信息，如：ts_xxx数据表创建不成功
		$res = checkDB ();
		
		unlink ( $filePath );
		
		echo $res;
	}
	function _overWritten($source = '', $destination = '', $res=array()) {
		if (empty ( $source ))
			$source = DATA_PATH . '/update/download/unzip';
		if (empty ( $destination ))
			$destination = SITE_PATH;
		
		$handle = dir ( $source );
		while ( $entry = $handle->read () ) {
			if (($entry != ".") && ($entry != "..")) {
				$file = $source . "/" . $entry;
				if (is_dir ( $file )) {
					$res = $this->_overWritten ( $file, $destination . "/" . $entry, $res );
				} else {
					$result = copy ( $file, $destination . "/" . $entry );
					if ($result) {
						$res ['success'] [] = $file;
					} else {
						$res ['error'] [] = $file;
					}
				}
			}
		}
		
		return $res;
	}
	function overWritten() {
		// 提示需要删除的文件
		$filePath = $targetDir = DATA_PATH . '/update/download/unzip/fileForDeleteList.php';
		if (file_exists ( $filePath )) {
			$deleteList = require_once ($filePath);
			$this->assign ( 'deleteList', $deleteList );
			unlink ( $filePath );
		}
		
		// 执行文件替换
		$res = $this->_overWritten ();
		$this->assign ( 'success', $res ['success'] );
		$this->assign ( 'error', $res ['error'] );
		
		// 清除缓存
		$this->cleanCache ();
		
		// 开启站点
		$this->openSite ();
		
		// 更新本地版本号信息
		$this->_updateFinishVersionStatus ();
		
		$this->display ();
	}
	function zipBackup() {
		$fileName = date ( 'Y-m-d-h' ) . '.zip';
		$package = DATA_PATH . '/update/backup/';
		// 目录不存在则创建
		if (! is_dir ( $package ))
			$res = mkdir ( $package, 0777, true );
		
		require_once ADDON_PATH . '/library/pclzip-2-8-2/pclzip.lib.php';
		$archive = new PclZip ( $package . $fileName );
		
		// 备份的文件列表
		$fileList = array (
				SITE_PATH . '/index.php',
				SITE_PATH . '/addons',
				SITE_PATH . '/apps',
				SITE_PATH . '/config',
				SITE_PATH . '/core',
				SITE_PATH . '/.htaccess',
				SITE_PATH . '/online_check.php' 
		);
		
		$res = $archive->create ( $fileList, PCLZIP_OPT_REMOVE_PATH, SITE_PATH );
		if ($res) {
			echo 1;
		} else {
			echo $archive->errorInfo ( true );
		}
	}
	
	// 写入当前版本信息
	function _writeVersion($key, $arr) {
		$path = DATA_PATH . '/update';
		$arr ['status'] = 0; // 未升级状态
		
		$versionArr = $this->_getVersionInfo ( $path );
		$versionArr [$key] = $arr;
		
		F ( 'versions', $versionArr, $path );
		
		return $versionArr;
	}
	function _updateVersionStatus($key) {
		$path = DATA_PATH . '/update';
		$versionArr = $this->_getVersionInfo ( $path );
		
		foreach ( $versionArr as $k => &$vo ) {
			if ($k != $key)
				continue;
			
			$vo ['status'] = 1; // 升级中的状态
		}
		
		F ( 'versions', $versionArr, $path );
	}
	function _updateFinishVersionStatus() {
		$path = DATA_PATH . '/update';
		$versionArr = $this->_getVersionInfo ( $path );
		
		foreach ( $versionArr as $k => &$vo ) {
			if ($vo ['status'] != 1)
				continue;
			
			$vo ['status'] = 2; // 升级完成的状态
		}
		
		F ( 'versions', $versionArr, $path );
	}
	function _getVersionInfo($path) {
		$file = $path . '/versions.php';
		
		$versionArr = array ();
		if (file_exists ( $file )) {
			$versionArr = F ( 'versions', '', $path );
		}
		
		return $versionArr;
	}
	function _rmdirr($dirname) {
		if (! file_exists ( $dirname )) {
			return false;
		}
		if (is_file ( $dirname ) || is_link ( $dirname )) {
			return unlink ( $dirname );
		}
		$dir = dir ( $dirname );
		if ($dir) {
			while ( false !== $entry = $dir->read () ) {
				if ($entry == '.' || $entry == '..') {
					continue;
				}
				$this->_rmdirr ( $dirname . DIRECTORY_SEPARATOR . $entry );
			}
		}
		$dir->close ();
		return rmdir ( $dirname );
	}
	function initBetaToRc() {
		$installfile = SITE_PATH.'/ts3BetaToRc.sql';
		if(!file_exists($installfile)){
			echo 'ts3BetaToRc.sql is not exist!';
			exit;
		}
		
		$fp = fopen ( $installfile, 'rb' );
		$sql = fread ( $fp, filesize ( $installfile ) );
		fclose ( $fp );
		
		$db_charset = C ( 'DB_CHARSET' );
		$db_prefix = C ( 'DB_PREFIX' );
		$sql = str_replace ( "\r", "\n", str_replace ( '`' . 'ts_', '`' . $db_prefix, $sql ) );
		foreach ( explode ( ";\n", trim ( $sql ) ) as $query ) {
			$query = trim ( $query );
			if ($query) {
				if (substr ( $query, 0, 12 ) == 'CREATE TABLE') {
					$query = $this->_createtable ( $query, $db_charset );
				} 
				
				$res = M()->execute( $query );
			}
		}
		
		$this->_updataStorey ();
		unlink($installfile);
		echo('Update Finish!');
	}
	function _createtable($sql, $db_charset){
		$db_charset = (strpos($db_charset, '-') === FALSE) ? $db_charset : str_replace('-', '', $db_charset);
		$type = strtoupper(preg_replace("/^\s*CREATE TABLE\s+.+\s+\(.+?\).*(ENGINE|TYPE)\s*=\s*([a-z]+?).*$/isU", "\\2", $sql));
		$type = in_array($type, array("MYISAM", "HEAP")) ? $type : "MYISAM";
		return preg_replace("/^\s*(CREATE TABLE\s+.+\s+\(.+?\)).*$/isU", "\\1", $sql).
		(mysql_get_server_info() > "4.1" ? " ENGINE=$type DEFAULT CHARSET=$db_charset" : " TYPE=$type");
	}
	function _updataStorey() {
		$map ['data'] = array (
				'neq',
				'N;' 
		);
		$commentlist = D ( 'comment' )->where ( $map )->findAll ();
		foreach ( $commentlist as $v ) {
			$data = unserialize ( $v ['data'] );
			if ($data ['storey']) {
				D ( 'comment' )->where ( 'comment_id=' . $v ['comment_id'] )->setField ( 'storey', $data ['storey'] );
			}
		}
	}
	function makeVersionToTxt() {
		$path = DATA_PATH . '/update';
		$file = $path . 'versions.php';
		$res = F ( 'versions', '', $path );
		// dump($res);
		$res = json_encode ( $res );
		echo $res;
	}
	function md5File() {
		$res = $this->_md5File ();
		dump ( $res );
	}
	function _md5File($source = '.', $res = array()) {
		$handle = dir ( $source );
		
		while ( $entry = $handle->read () ) {
			if (($entry != ".") && ($entry != "..")) {
				$file = $source . "/" . $entry;
				if (is_dir ( $file )) {
					$this->_md5File ( $file, $res );
				} else {
					$res [$file] = md5_file ( $file );
				}
			}
		}
		
		return $res;
	}
}
