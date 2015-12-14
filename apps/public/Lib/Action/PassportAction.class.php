<?php
/**
 * PassportAction 通行证模块
 * @author  liuxiaoqing <liuxiaoqing@zhishisoft.com>
 * @version TS3.0
 */
class PassportAction extends Action 
{
	var $passport;

	/**
	 * 模块初始化
	 * @return void
	 */
	protected function _initialize() {
		$this->passport = D('Passport');
	}

	/**
	 * 通行证首页
	 * @return void
	 */
	public function index() {
		$this->display();
	}

	/**
	 * 默认登录页
	 * @return void
	 */
	public function login()
	{
		// 添加样式
		$this->appCssList[] = 'login.css';
		if($GLOBALS['ts']['mid'] > 0){
			U('public/Index/index','',true);
		}
		// 获取邮箱后缀
		$registerConf = model('Xdata')->get('admin_Config:register');
		$this->assign('emailSuffix', explode(',', $registerConf['email_suffix']));
		$this->assign( 'register_type' , $registerConf['register_type']);
		$data= model('Xdata')->get("admin_Config:seo_login");
        !empty($data['title']) && $this->setTitle($data['title']);
        !empty($data['keywords']) && $this->setKeywords($data['keywords']);
        !empty($data['des'] ) && $this->setDescription ( $data ['des'] );
		
		$login_bg = getImageUrlByAttachId( $this->site ['login_bg'] );
		if(empty($login_bg))
			$login_bg = APP_PUBLIC_URL . '/image/body-bg2.jpg';
        
        $this->assign('login_bg', $login_bg);
        
		$this->display();
	}
	/**
	 * 快速登录
	 */
	public function quickLogin(){
		$registerConf = model('Xdata')->get('admin_Config:register');
		$this->assign( 'register_type' , $registerConf['register_type']);
		$this->display();
	}
	/**
	 * 用户登录
	 * @return void
	 */
	public function doLogin() {
		$login 		= t($_POST['login_email']);
		$password 	= trim($_POST['login_password']);
		$remember	= intval($_POST['login_remember']);
		
		$result 	= $this->passport->loginLocal($login,$password,$remember);
		
		if(!$result){
			$status = 0; 
			$info	= $this->passport->getError();
			$data 	= '';
		}else{
			$status = 1; 
			$info 	= L('PUBLIC_LOGIN_SUCCESS');
			$data 	= $GLOBALS['ts']['site']['home_url'];
			//添加积分
			model('Credit')->setUserCredit($this->mid,'user_login');
		}
		$this->ajaxReturn($data,$info,$status);
	}	
	
	/**
	 * 注销登录
	 * @return void
	 */
	public function logout() {
		$this->passport->logoutLocal();
		redirect(U('public/Passport/login'));
	}

	/**
	 * 找回密码页面
	 * @return void
	 */
	public function findPassword() {

		// 添加样式
		$this->appCssList[] = 'login.css';

		$this->display();
	}

	/**
	 * 通过安全问题找回密码
	 * @return void
	 */
	public function doFindPasswordByQuestions() {
		$this->display();
	}

	/**
	 * 通过Email找回密码
	 */
	public function doFindPasswordByEmail() {
		$_POST["email"]	= t($_POST["email"]);
		if(!$this->_isEmailString($_POST['email'])) {
			$this->error(L('PUBLIC_EMAIL_TYPE_WRONG'));
		}

		$user =	model("User")->where('`email`="'.$_POST["email"].'"')->find();
		
        if(!$user) {
        	$this->error('找不到该邮箱注册信息');
        } else {
        	$msg['status'] = 1;
        	$msg['info'] = '发送成功';
        	$msg['data'] = $user['uid'];
        	exit(json_encode($msg));
		}
	}

	/**
	 * 找回密码页面
	 */
	public function sendPasswordEmail() {
		$uid = intval($_REQUEST['uid']);
		$user =	model("User")->where('`uid`="'.$uid.'"')->find();
		if($user) {
	    	$this->appCssList[] = 'login.css';		// 添加样式
	        $code = md5($user["uid"].'+'.$user["password"].'+'.rand(1111,9999));
	        $config['reseturl'] = U('public/Passport/resetPassword', array('code'=>$code));
	        D('find_password')->where('uid='.$uid)->setField('is_used',1);
	        $sql =  "INSERT INTO ".C('DB_PREFIX')."find_password (`uid`,`email`,`code`,`is_used`) VALUES ('{$uid}','{$user['email']}','{$code}','0')";
	        D()->query($sql);
	        model('Notify')->sendNotify($user['uid'], 'password_reset', $config);
	        $message = L('PUBLIC_EMAIL_SENDTO_SUCCESS')."<strong>{$user['email']}</strong>";
	       	$this->assign('email', $user["email"]);
	        $this->assign('message', $message);
	        $this->display();
		}
	}

	public function doFindPasswordByEmailAgain(){
		$_POST["email"]	= t($_POST["email"]);
		$user =	model("User")->where('`email`="'.$_POST["email"].'"')->find();		
        if(!$user) {
        	$this->error('找不到该邮箱注册信息');
        } else {
        	D('find_password')->where('uid='.$user['uid'])->setField('is_used',1);
        	$code = md5($user["uid"].'+'.$user["password"].'+'.rand(1111,9999));
	        $config['reseturl'] = U('public/Passport/resetPassword', array('code'=>$code));
        	$sql =  "INSERT INTO ".C('DB_PREFIX')."find_password (`uid`,`email`,`code`,`is_used`) VALUES ('{$user['uid']}','{$user['email']}','{$code}','0')";
	        D()->query($sql);
	        model('Notify')->sendNotify($user['uid'], 'password_reset', $config);
		}
	}

	/**
	 * 通过手机短信找回密码
	 * @return void
	 */
	public function doFindPasswordBySMS() {
		$this->display();
	}

	/**
	 * 重置密码页面
	 * @return void
	 */
	public function resetPassword() {
		$code = t($_GET['code']);
		$this->_checkResetPasswordCode($code);
		$this->assign('code', $code);
		$this->display();
	}

	/**
	 * 执行重置密码操作
	 * @return void
	 */
	public function doResetPassword() {
		$code = t($_GET['code']);
		$user_info = $this->_checkResetPasswordCode($_POST['code']);

		$password = trim($_POST['password']);
		$repassword = trim($_POST['repassword']);
		if(!model('Register')->isValidPassword($password, $repassword)){
			$this->error(model('Register')->getLastError());
		}

		$map['uid'] = $user_info['uid'];
		$data['login_salt'] = rand(10000,99999);
		$data['password']   = md5(md5($password) . $data['login_salt']);
		$res = model('User')->where($map)->save($data);
		if ($res) {
			D('find_password')->where('uid='.$user_info['uid'])->setField('is_used',1);
			model('User')->cleanCache($user_info['uid']);
			$this->assign('jumpUrl', U('public/Passport/login'));
			$config['newpass'] = $password;
			model('Notify')->sendNotify($user_info['uid'],'password_setok',$config);
			//$mail = model('Mail')->sendSysEmail($user_info['email'],'resetPassOk',array(),array('newpass'=>$password));
			$this->success(L('PUBLIC_PASSWORD_RESET_SUCCESS'));
		} else {
			$this->error(L('PUBLIC_PASSWORD_RESET_FAIL'));
		}
	}

	/**
	 * 检查重置密码的验证码操作
	 * @return void
	 */
	private function _checkResetPasswordCode($code) {
		$uid = D('find_password')->where("code='{$code}' and is_used=0")->getField('uid');
		if(!$uid){
			$this->assign('jumpUrl',U('public/Passport/findPassword'));
			$this->error('重置密码链接已失效，请重新找回');
		}
		$user_info = model('User')->where("`uid`={$uid}")->find();

		if (!$user_info) {
			$this->redirect = U('public/Passport/login');
		}

		return $user_info;
	}

	/*
	 * 验证安全邮箱
	 * @return void
	 */
	public function doCheckEmail() {
		$email = t($_POST['email']);
		if($this->_isEmailString($email)){
			die(1);			
		}else{
			die(0);
		}
	}

	/*
	 * 正则匹配，验证邮箱格式
	 * @return integer 1=成功 ""=失败
	 */
	private function _isEmailString($email) {
		return preg_match("/[_a-zA-Z\d\-\.]+@[_a-zA-Z\d\-]+(\.[_a-zA-Z\d\-]+)+$/i", $email) !== 0;
	}
}
?>