<?php
/**
 * 资源模型 - 业务逻辑模型
 * @example
 * 根据表名及资源ID，获取对应的资源信息
 * @author jason <yangjs17@yeah.net>
 * @version TS3.0
 */
class SourceModel {

	/**
	 * 获取指定资源，并格式化输出
	 * @param string $table 资源表名
	 * @param integer $row_id 资源ID
	 * @param boolean $_forApi 是否提供API，默认为false
	 * @param string $appname 自定应用名称，默认为public
	 * @return [type]           [description]
	 */
	public function getSourceInfo($table, $row_id, $_forApi = false, $appname = 'public') {
		static $forApi = '0';
		$forApi == '0' && $forApi = intval($_forApi);

		$key = $forApi ? $table.$row_id.'_api' : $table.$row_id;
		if($info = static_cache('source_info_'.$key)) {
			return $info;
		}
		switch($table) {
			case 'feed':
				$info = $this->getInfoFromFeed($table, $row_id, $_forApi);
				break;
			case 'comment':
				$info = $this->getInfoFromComment($table, $row_id, $_forApi);
				break;
			default:
				$modelArr = explode('_', $table);
				$model = '';
				foreach($modelArr as $v) {
					$model .=ucfirst($v);
				}
				// 单独的内容，通过此路径获取资源信息
				if(file_exists(SITE_PATH.'/apps/'.$appname.'/Lib/Model/'.$model.'Model.class.php'))
					$info = D($model, $appname)->getSourceInfo($row_id, $_forApi);
				break;	
		}
		
		$info['source_table'] = $table;
		$info['source_id'] = $row_id;
		static_cache('source_info_'.$key,$info);
		return $info;
	}

	/**
	 * 从Feed中提取资源数据
	 * @param string $table 资源表名
	 * @param integer $row_id 资源ID
	 * @param boolean $forApi 是否提供API，默认为false
	 * @return array 格式化后的资源数据
	 */
	private function getInfoFromFeed($table, $row_id, $forApi) {
		$info = model('Feed')->getFeedInfo($row_id,$forApi);
		$info['source_user_info'] = model('User')->getUserInfo($info['uid']);
		$info['source_user'] = $info['uid'] == $GLOBALS['ts']['mid'] ? L('PUBLIC_ME'): $info['source_user_info']['space_link'];			// 我
		$info['source_type'] = L('PUBLIC_WEIBO');
		$info['source_title'] = $forApi ? parseForApi($_info['user_info']['space_link']) : $_info['user_info']['space_link'];	//微博title暂时为空
		$info['source_url'] = U('public/Profile/feed', array('feed_id'=>$row_id, 'uid'=>$info['uid']));
		$info['source_content'] = $info['content'];
		$info['ctime'] = $info['publish_time'];
		unset($info['content']);
		return $info;
	}

	/**
	 * 从评论中提取资源数据
	 * @param string $table 资源表名
	 * @param integer $row_id 资源ID
	 * @param boolean $forApi 是否提供API，默认为false
	 * @return array 格式化后的资源数据
	 */
	private function getInfoFromComment($table, $row_id, $forApi) {
		$_info = model('Comment')->getCommentInfo($row_id, true);
		$info['uid'] = $_info['app_uid'];
		$info['row_id'] = $_info['row_id'];
		$info['is_audit'] = $_info['is_audit'];
		$info['source_user'] = $info['uid'] == $GLOBALS['ts']['mid'] ? L('PUBLIC_ME') : $_info['user_info']['space_link'];			// 我
		$info['comment_user_info'] = model('User')->getUserInfo($_info['user_info']['uid']); 
		$forApi && $info['source_user'] = parseForApi($info['source_user']);
		$info['source_user_info'] = model('User')->getUserInfo($info['uid']);
		$info['source_type'] = L('PUBLIC_STREAM_COMMENT');				// 评论
		$info['source_content'] = $forApi ? parseForApi($_info['content']) : $_info['content'];
		$info['source_url'] = $_info['sourceInfo']['source_url'];
		$info['ctime'] = $_info['ctime'];
		$info['app'] = $_info['app'];
		$info['sourceInfo'] = $_info['sourceInfo'];
		// 微博title暂时为空
		$info['source_title'] = $forApi ? parseForApi($_info['user_info']['space_link']) : $_info['user_info']['space_link'];

		return $info;
	}
}