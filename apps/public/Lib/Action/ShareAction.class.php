<?php
/**
 * ShareAction 分享控制器
 * @author    jason <yangjs17@yeah.net>
 * @version   TS3.0
 */
class ShareAction extends Action
{
	/**
	 * _initialize 模块初始化
	 * @return void
	 */
	protected function _initialize() {

	}

	/**
	 * 分享控制
	 * @return void
	 */
	public function index(){
		$shareInfo['sid'] = intval($_GET['sid']);
		$shareInfo['stable'] = t($_GET['stable']);
		$shareInfo['initHTML']  = h($_GET['initHTML']);
		$shareInfo['curid'] 	= t($_GET['curid']);
		$shareInfo['curtable']  = t($_GET['curtable']);
		$shareInfo['appname']	= t($_GET['appname']);
		$shareInfo['cancomment'] = intval($_GET['cancomment']);
		$shareInfo['is_repost'] = intval($_GET['is_repost']);
		if(empty($shareInfo['stable']) || empty($shareInfo['sid'])){
			echo L('PUBLIC_TYPE_NOEMPTY'); exit();
		}
		if(!$oldInfo = model('Source')->getSourceInfo($shareInfo['stable'],$shareInfo['sid'],false,$shareInfo['appname'])){
			echo L('PUBLIC_INFO_SHARE_FORBIDDEN');exit();
		}
		empty($shareInfo['appname']) && $shareInfo['appname'] = $oldInfo['app'];			
		if($shareInfo['appname'] != '' && $shareInfo['appname'] != 'public'){
			addLang($shareInfo['appname']);
		}	
		if(empty($shareInfo['initHTML']) && !empty($shareInfo['curid'])){
			//判断是否为转发的微博
			if($shareInfo['curid'] != $shareInfo['sid'] && $shareInfo['is_repost']==1){
				$app = $curtable == $shareInfo['stable'] ? $shareInfo['appname'] :'public';
				$curInfo = model('Source')->getSourceInfo($shareInfo['curtable'],$shareInfo['curid'],false,$app);
				$userInfo = $curInfo['source_user_info'];
				// if($userInfo['uid'] != $this->mid){	//分享其他人的分享，非自己的
					$shareInfo['initHTML'] = ' //@'.$userInfo['uname'].'：'.$curInfo['source_content'];
				// }
				$shareInfo['initHTML'] = str_replace(array("\n", "\r"), array('', ''), $shareInfo['initHTML']);
			}
		}
		$shareInfo['shareHtml'] =  !empty($oldInfo['shareHtml'])  ?  $oldInfo['shareHtml'] : '';
		$weiboSet = model('Xdata')->get('admin_Config:feed');
		$canShareFeed = in_array('repost',$weiboSet['weibo_premission']) ? 1  : '0';
		$this->assign('canShareFeed',$canShareFeed);
		$this->assign('initNums',$weiboSet['weibo_nums']);
		$this->assign('shareInfo',$shareInfo);
		$this->assign('oldInfo',$oldInfo);
		$this->display();
	}

	/**
	 * 分享信息
	 * @return mix 分享状态和提示
	 */
	public function shareMessage()
	{
		$post = $_POST;
		// 安全过滤
		foreach($post as $key => $val) {
			$post[$key] = t($post[$key]);
		}
		// 判断资源是否存在
		// 判断资源是否删除
		if(empty($post['curid'])) {
			$map['feed_id'] = $post['sid'];
		} else {
			$map['feed_id'] = $post['curid'];
		}
		$map['is_del'] = 0;
		$isExist = model('Feed')->where($map)->count();
		if($isExist == 0) {
			$return['status'] = 0;
			$return['data'] = '内容已被删除，分享失败';
			exit(json_encode($return));
		}
		// 过滤数据，安全性
		foreach($post as $key => $val) {
			$post[$key] = t($post[$key]);
		}

		exit(json_encode(model('Share')->shareMessage($post)));
	}
}