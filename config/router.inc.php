<?php
return array(
	/**
	 * 路由的key必须写全称. 比如: 使用'wap/Index/index', 而非'wap'.
	 */
	'router' => array(
		// 基本
		'page/Index/index'			=>  SITE_URL.'/page/[page].html',
// 		'public/Index/index'		=> SITE_URL.'/home',
		'public/Profile/index'		=>	SITE_URL.'/@[uid]',
		'public/Profile/data'  =>  SITE_URL.'/@[uid]/Profile',
		'public/Profile/following'  =>  SITE_URL.'/@[uid]/following',
		'public/Profile/follower'  =>  SITE_URL.'/@[uid]/follower',
		'public/Profile/feed'  =>  SITE_URL.'/@[uid]/weibo/[feed_id]',
		'public/Passport/login'  =>  SITE_URL.'/login',
		'public/Register/index'  =>  SITE_URL.'/register',
		'public/Register/waitForActivation'  =>  SITE_URL.'/activate/[uid]',
		'public/Register/waitForAudit'  =>  SITE_URL.'/review/[uid]',
		'public/Register/step2'  =>  SITE_URL.'/register/upload_photo',
		'public/Register/step3'  =>  SITE_URL.'/register/work_information',
		'public/Register/step4'  =>  SITE_URL.'/register/follow_interesting',
// 		'public/Index/index'  =>  SITE_URL.'/allfeed',
		'public/Index/myFeed'  =>  SITE_URL.'/my/weibo',
		'public/Index/following'  =>  SITE_URL.'/my/following',
		'public/Index/follower'  =>  SITE_URL.'/my/follower',
		'public/Collection/index'  =>  SITE_URL.'/my/favorite',
		'public/Mention/index'  =>  SITE_URL.'/atme',
		
// 		'public/Search/index'	=> SITE_URL.'/weibo/search/[t]/[k]',
		'public/Comment/index'  =>  SITE_URL.'/comment/[type]',
			
		'public/Message/index'  =>  SITE_URL.'/message',
		'public/Message/notify'  =>  SITE_URL.'/notify',
		'public/Message/detail' => SITE_URL.'/message/[type]/[id]',
			
		'public/Account/11'  =>  SITE_URL.'/setting',
		'public/Account/avatar'  =>  SITE_URL.'/setting/upload_photo',
		'public/Account/domain'  =>  SITE_URL.'/setting/domain',
		'public/Account/authenticate'  =>  SITE_URL.'/setting/verify',
		'public/Account/privacy'  =>  SITE_URL.'/setting/privacy',
		'public/Account/notify'  =>  SITE_URL.'/setting/notify',
		'public/Account/blacklist'  =>  SITE_URL.'/setting/blacklist',
		'public/Account/security'  =>  SITE_URL.'/setting/security',
		'public/Account/bind'  =>  SITE_URL.'/setting/bind',
		
// 		'channel/Index/index'  =>  SITE_URL.'/channel/[cid]',
// 		'club/Index/index'  =>  SITE_URL.'/club/[cid]',
		'weiba/Index/index'  =>  SITE_URL.'/weiba',
		'weiba/Index/weibalist'  =>  SITE_URL.'/weiba/weibalist',
		'weiba/Index/postlist'  =>  SITE_URL.'/weiba/postlist',
// 		'weiba/Index/myWeiba'  =>  SITE_URL.'/weiba/my/[type]',
// 		'weiba/Index/search'  =>  SITE_URL.'/weiba/search/[type]/[k]',
		'weiba/Index/detail'  =>  SITE_URL.'/weiba/[weiba_id]',
		'weiba/Index/post'  =>  SITE_URL.'/weiba/[weiba_id]/post',
		'weiba/Index/postDetail'  =>  SITE_URL.'/weiba/[post_id]/detail',
		'weiba/Index/postEdit'  =>  SITE_URL.'/weiba/[post_id]/edit',
		'weiba/Index/replyEdit'  =>  SITE_URL.'/weiba/[reply_id]/editreply',
		'weiba/Manage/index'  =>  SITE_URL.'/weiba/[weiba_id]/manage',
		'weiba/Manage/member'  =>  SITE_URL.'/weiba/[weiba_id]/manage/member',
		'weiba/Manage/notify'  =>  SITE_URL.'/weiba/[weiba_id]/manage/notify',
		'weiba/Manage/log'  =>  SITE_URL.'/weiba/[weiba_id]/manage/log',
	)
);