<?php
class ToolAction extends AdministratorAction {
	/*
	 * 工具 - 数据备份
	 */
	public function backup() {
		$dir = DATA_PATH . '/database/';
		if (is_dir ( $dir )) {
			if ($dh = opendir ( $dir )) {
				while ( ($filename = readdir ( $dh )) !== false ) {
					if ($filename != '.' && $filename != '..') {
						if (substr ( $filename, strrpos ( $filename, '.' ) ) == '.sql' || substr ( $filename, strrpos ( $filename, '.' ) ) == '.php') {
							$file = $dir . $filename;
							$filemtime = date ( 'Y-m-d H:i:s', filemtime ( $file ) );
							$addtime [] = $filemtime;
							$log [] = array (
									'filename' => $filename,
									'filesize' => formatsize ( filesize ( $file ) ),
									'addtime' => $filemtime,
									'filepath' => C ( 'SITE_URL' ) . $file 
							);
						}
					}
				}
			}
		} else {
			@mk_dir ( $dir, 0777 );
		}
		
		array_multisort ( $addtime, SORT_ASC, $log );
		$this->assign ( 'log', $log );
		
		$this->assign ( 'table', D ( 'Database' )->getTableList () );
		$this->display ();
	}
	public function doBackUp() {
		if (empty ( $_REQUEST ['backup_type'] ))
			$this->error ( '参数错误' );
		
		$tables = array ();
		// 当前卷号
		$volume = isset ( $_GET ['volume'] ) ? (intval ( $_GET ['volume'] ) + 1) : 1;
		// 备份文件的文件名
		$filename = date ( 'ymd' ) . '_' . substr ( md5 ( uniqid ( rand () ) ), 0, 10 );
		
		$_REQUEST ['backup_type'] = t ( $_REQUEST ['backup_type'] ) == 'custom' ? 'custom' : 'all';
		
		if ($_REQUEST ['backup_type'] == 'all') {
			$tables = D ( 'Database' )->getTableList ();
			$tables = getSubByKey ( $tables, 'Name' );
		} else if ($_REQUEST ['backup_type'] == 'custom') {
			if ($_POST ['backup_table']) {
				$tables = $_POST ['backup_table'];
				$_SESSION ['backup_custom_table'] = $tables;
			} else {
				$tables = $_SESSION ['backup_custom_table'];
			}
		}
		
		$filename = trim ( $_REQUEST ['filename'] ) ? trim ( $_REQUEST ['filename'] ) : $filename;
		$startfrom = intval ( $_REQUEST ['startform'] );
		$tableid = intval ( $_REQUEST ['tableid'] );
		$sizelimit = intval ( $_REQUEST ['sizelimit'] ) ? intval ( $_REQUEST ['sizelimit'] ) : 1000;
		$tablenum = count ( $tables );
		$filesize = $sizelimit * 1000;
		$complete = true;
		$tabledump = '';
		
		if ($tablenum == 0)
			$this->error ( '请选择备份的表' );
		
		for(; $complete && ($tableid < $tablenum) && strlen ( $tabledump ) + 500 < $filesize; $tableid ++) {
			
			$sqlDump = D ( 'Database' )->getTableSql ( $tables [$tableid], $startfrom, $filesize, strlen ( $tabledump ), $complete );
			
			$tabledump .= $sqlDump ['tabledump'];
			$complete = $sqlDump ['complete'];
			$startfrom = intval ( $sqlDump ['startform'] );
			if ($complete)
				$startfrom = 0;
		}
		
		! $complete && $tableid --;
		
		if (trim ( $tabledump )) {
			// $filepath = DATA_PATH . '/database/'.$filename."_$volume".'.sql';
			$filepath = DATA_PATH . '/database/' . $filename . "_$volume" . '.php';
			$fp = @fopen ( $filepath, 'ab' );
			fwrite ( $fp, "#<?php exit;?>\n\n" );
			fwrite ( $fp, $tabledump );
			if (! fwrite ( $fp, $tabledump )) {
				$this->error ( '文件目录写入失败, 请检查data目录是否可写' );
			} else {
				$url_param = array (
						'filename' => $filename,
						'backup_type' => $_REQUEST ['backup_type'],
						'sizelimit' => $sizelimit,
						'tableid' => $tableid,
						'startform' => $startfrom,
						'volume' => $volume 
				);
				
				$url = U ( 'admin/Tool/doBackUp', $url_param );
				$this->assign ( 'jumpUrl', $url );
				
				if ($_POST ['backup_type'] == 'custom') {
					$_LOG ['uid'] = $this->mid;
					$_LOG ['type'] = '1';
					$data [] = '扩展 - 工具 - 数据备份';
					if ($_POST ['__hash__'])
						unset ( $_POST ['__hash__'] );
					$data [] = $_POST;
					$_LOG ['data'] = serialize ( $data );
					$_LOG ['ctime'] = time ();
					M ( 'AdminLog' )->add ( $_LOG );
				}
				if ($_POST ['backup_type'] == 'all') {
					$_LOG ['uid'] = $this->mid;
					$_LOG ['type'] = '1';
					$data [] = '扩展 - 工具 - 数据备份';
					$data [] = array (
							'全部数据表都备份成功' 
					);
					$_LOG ['data'] = serialize ( $data );
					$_LOG ['ctime'] = time ();
					M ( 'AdminLog' )->add ( $_LOG );
				}
				
				$this->success ( "备份第{$volume}卷成功" );
			}
		} else {
			$this->assign ( 'jumpUrl', U ( 'admin/Tool/backup' ) );
			
			$this->success ( "备份成功" );
		}
	}
	public function doDeleteBackUp() {
		$_POST ['selected'] = explode ( ',', t ( $_POST ['selected'] ) );
		
		foreach ( $_POST ['selected'] as $file ) {
			// $file = DATA_PATH . '/database/'.$file.'.sql';
			$file = DATA_PATH . '/database/' . $file . '.php';
			file_exists ( $file ) && @unlink ( $file );
		}
		
		$_LOG ['uid'] = $this->mid;
		$_LOG ['type'] = '2';
		$data [] = '扩展 - 工具 - 数据备份';
		$data [] = $_POST ['selected'];
		$_LOG ['data'] = serialize ( $data );
		$_LOG ['ctime'] = time ();
		M ( 'AdminLog' )->add ( $_LOG );
		
		echo 1;
	}
	
	// 导入备份
	function import() {
		$filename = $_GET ['filename'];
		$sqldump = '';
		$file = DATA_PATH . '/database/' . $filename;
		if (file_exists ( $file )) {
			
			$fp = @fopen ( $file, 'rb' );
			$sqldump = fread ( $fp, filesize ( $file ) );
			
			fclose ( $fp );
		}
		
		$ret = D ( 'Database' )->import ( $sqldump );
		if ($ret) {
			$this->success ( '导入成功' );
		} else {
			$this->error ( '导入失败' );
		}
	}
	public function dbconvert() {
		$this->display ();
	}
	public function doDbconvert() {
		if ($_POST ['doDbconvert'] != 1) {
			$this->error ( '参数错误' );
		}
		
		// 转换ts_comment的数据
		$dao = M ( 'comment' );
		$db_prefix = C ( 'DB_PREFIX' );
		
		// 转换to_uid
		$sql = "UPDATE {$db_prefix}comment c1 SET `to_uid` = ( SELECT c2.uid FROM ( SELECT temp.* FROM {$db_prefix}comment temp ) c2 WHERE c2.id = c1.toId ) WHERE toId <> 0";
		if (false === $dao->execute ( $sql )) {
			$this->error ( '转换ts_comment的to_uid字段出现错误' );
		}
		
		// 转换data
		$apps = array (
				'blog',
				'vote' 
		);
		foreach ( $apps as $k => $app ) {
			$appids = $dao->query ( "SELECT `appid` FROM {$db_prefix}comment WHERE `type` = '{$app}'" );
			$appids = getSubByKey ( $appids, 'appid' );
			
			unset ( $map );
			$map ['id'] = array (
					'in',
					$appids 
			);
			$app_titles = M ( $app )->where ( $map )->field ( 'id,title,uid' )->findAll ();
			
			$sql = array ();
			foreach ( $app_titles as $app_detail ) {
				unset ( $data );
				$data ['title'] = $app_detail ['title'];
				$data ['url'] = ($app == 'blog') ? U ( 'blog/Index/show', array (
						'id' => $app_detail ['id'],
						'mid' => $app_detail ['uid'] 
				) ) : U ( 'vote/Index/pollDetail', array (
						'id' => $app_detail ['id'] 
				) );
				$data ['table'] = $app;
				$data ['id_field'] = 'id';
				$data ['comment_count_field'] = 'commentCount';
				
				if (! $dao->where ( "`type`='{$app}' AND `appid`={$app_detail['id']}" )->setField ( 'data', serialize ( $data ) )) {
					echo '转换程序出现错误, SQL: ' . $dao->getLastSql ();
					exit ();
				}
			} // END foreach - app_info
		} // END foreach - apps
		
		$this->assign ( 'jumpUlr', U ( 'admin/Tool/dbconvert' ) );
		$this->success ( '转换成功' );
	}
	public function doDownload() {
		$filename = $_REQUEST ['filename'];
		// 下载函数
		require_once ( ADDON_PATH.'/library/Http.class.php' );
		$file_path = DATA_PATH . '/database' . '/' . $filename;

		if (file_exists ( $file_path )) {
			$filename = iconv ( "utf-8", 'gb2312', $filename );
			Http::download ( $file_path, $filename );
		} else {
			$this->error ( "数据不存在！" );
		}
	}
	function checkdir() {
		set_time_limit ( 0 );
		header ( "Content-type: text/html; charset=utf-8" );
		
		$this->_checkdir ( SITE_PATH );
	}
	function _checkdir($basedir) {
		if ($dh = opendir ( $basedir )) {
			while ( ($file = readdir ( $dh )) !== false ) {
				if ($file != '.' && $file != '..' && $file != '.svn') {
					
					$filename = $basedir . "/" . $file;
					if (is_dir ( $filename )) {
						$this->_checkdir ( $filename );
					} else {
						$this->_checkFile ( $filename );
					}
				}
			}
			closedir ( $dh );
		}
	}
	function _checkFile($filename) {
		$str = file_get_contents ( $filename );
		preg_match_all ( "/L\(\'([a-zA-Z0-9_]+)\'\)/i", $str, $match );
		
		$match = $match [1];
		if (empty ( $match ))
			return true;
		
		$path = str_replace ( '\\', '/', $filename );
		$arr = explode ( '/', $path );
		foreach ( $arr as $k => $vo ) {
			if (strtolower ( $vo ) == 'apps') {
				$app = strtoupper ( $arr [$k + 1] );
			}
		}
		if (empty ( $app )) {
			$app = 'PUBLIC';
		}
		
		substr ( $filename, - 3, 3 ) == '.js' ? $filetype = 1 : $filetype = 0;
		
		foreach ( $match as $m ) {
			$m = strtoupper ( $m );
			$this->_langIsExist ( $filename, $m, $app, $filetype );
		}
	}
	function _langIsExist($filename, $key, $app = '', $filetype = 0) {
		static $_lang;
		if (empty ( $_lang )) {
			$list = model ( 'Lang' )->field ( '`key`, appname, filetype' )->findAll ();
			foreach ( $list as $v ) {
				$k = strtoupper ( $v ['key'] );
				$app = strtoupper ( $v ['appname'] );
				$_lang [$k] [$app] [$v ['filetype']] = true;
			}
			unset ( $list );
		}
		
		if (! isset ( $_lang [$key] [$app] [$filetype] ) && ! isset ( $_lang [$key] ['PUBLIC'] [$filetype] )) {
			$_lang [$key] [$app] [$filetype] = true;
			$this->_addLang ( $filename, $key, $app, $filetype );
		}
	}
	function _addLang($filename, $key, $appname, $filetype = 0) {
		$data ['key'] = strtoupper ( $key );
		$data ['appname'] = strtoupper ( $appname );
		$data ['filetype'] = $filetype;
		$data ['zh-cn'] = '==**==';
		$data ['en'] = '==**==';
		$data ['zh-tw'] = $filename;
		
		model ( 'Lang' )->add ( $data );
	}
	/*
	 * 工具 - 缓存文件清理
	*/	
	function cleancache(){
		//清文件缓存
		$dirs	=	array('./_runtime/');
		
		//清理缓存
		foreach($dirs as $value) {
			$this->_rmdirr($value);
			echo "<div style='border:2px solid green; background:#f1f1f1; padding:20px;margin:20px;width:800px;font-weight:bold;color:green;text-align:center;'>\"".$value."\" 缓存目录已经删除! </div> <br /><br />";
		}
		
		@mkdir('_runtime',0777,true);		
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
}