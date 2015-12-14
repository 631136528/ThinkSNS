<?php
/**
 * 通行证模型 - 业务逻辑模型
 * @author liuxiaoqing <liuxiaoqing@zhishisoft.com>
 * @version TS3.0
 */
class PassportModel {

	protected $error = null;				// 错误信息字段
	protected $rel = array();				// 判断是否是第一次登录

	/**
	 * 返回最后的错误信息
	 * @return string 最后的错误信息
	 */
	public function getError() {
		return $this->error;
	}
	
	/**
	 * 验证后台登录
	 * @return boolean 是否已经登录后台
	 */
	public function checkAdminLogin() {
		if($_SESSION['adminLogin']) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 登录后台
	 * @return boolean 登录后台是否成功
	 */
	public function adminLogin() {
		if(md5($_POST['verify']) != $_SESSION['verify']) {
			$this->error = L('PUBLIC_VERIFY_CODE_ERROR');				// 验证码错误
			return false;
		}
		$login = isset($_POST['uid']) ? t($_POST['uid']) : t($_POST['email']);
		if($this->loginLocal($login, $_POST['password'])) {
			$GLOBALS['ts']['mid'] = $_SESSION['adminLogin'] = intval($_SESSION['mid']); 
			return true;			
		} else {
			return false;
		}
	}
	
	/**
	 * 退出后台
	 * @return void
	 */
	public function adminLogout() {
		unset($_SESSION['adminLogin']);
		session_destroy($_SESSION['adminLogin']);
	}

	/**
	 * 验证用户是否需要登录
	 * @return boolean 登陆成功是返回true, 否则返回false
	 */
	public function needLogin() {
		// 验证本地系统登录
		if($this->isLogged()) {
			return false;
		} else {
            // 匿名访问控制
            $acl = C('access');
			return !($acl[APP_NAME.'/'.MODULE_NAME.'/'.ACTION_NAME] === true
				|| $acl[APP_NAME.'/'.MODULE_NAME.'/*'] === true
				|| $acl[APP_NAME.'/*/*'] === true);

			//ACL判断
			if(MODULE_CODE != 'public/Passport' && MODULE_CODE != 'public/Register'){
				return true;
			} else {
				return false;
			}
		}
	}

	/**
	 * 验证用户是否已登录
	 * 按照session -> cookie的顺序检查是否登陆
	 * @return boolean 登陆成功是返回true, 否则返回false
	 */
	public function isLogged() {
		// 验证本地系统登录
		if(intval($_SESSION['mid']) > 0 && $_SESSION['SITE_KEY']==getSiteKey()) {
			return true;
		} else if($uid = $this->getCookieUid()) {
			return $this->_recordLogin($uid); 
		} else {
			unset($_SESSION['mid']);
			unset($_SESSION['SITE_KEY']);
			return false;
		}
	}

	/**
	 * 根据标示符（email或uid）和未加密的密码获取本地用户（密码为null时不参与验证）
	 * @param string $login 标示符内容（为数字时：标示符类型为uid，其他：标示符类型为email）
	 * @param string|boolean $password 未加密的密码
	 * @return array|boolean 成功获取用户数据时返回用户信息数组，否则返回false
	 */
	public function getLocalUser($login, $password) {
		$login = t($login);
		if(empty($login) || empty($password)) {
			$this->error = L('PUBLIC_ACCOUNT_EMPTY');			// 帐号或密码不能为空
			return false;
		}

		if(is_numeric($login)) {
			$map['uid'] = $login;
		} else {
			$map['login'] = $login;
		}
		$map['is_del'] = 0;
		
		if(!$user = model('User')->where($map)->find()) {
			$this->error = L('PUBLIC_ACCOUNT_NOEXIST');			// 帐号不存在
			return false;
		}

		$uid  = $user['uid'];
		// 记录登陆日志，首次登陆判断
		$this->rel = D('LoginRecord')->where("uid = ".$uid)->find();

		$login_error_time = cookie('login_error_time');

		if($this->rel['locktime'] > time()) {
			$this->error = L('PUBLIC_ACCOUNT_LOCKED');			// 您的帐号已经被锁定，请稍后再登录
			return false;
		}
		
		if($password && md5(md5($password).$user['login_salt']) != $user['password']) {
			$login_error_time = intval($login_error_time) + 1;
			cookie('login_error_time', $login_error_time);

			$this->error = '密码输入错误，您还可以输入'.(6 - $login_error_time).'次';			// 密码错误

			if($login_error_time >=6) {
				// 记录锁定账号时间
				$save['locktime'] = time() + 60 * 60;
				$save['ip'] = get_client_ip();
				$save['ctime'] = time();
				$m['uid'] = $save['uid'] = $uid;

				$this->error = L('PUBLIC_ACCOUNT_LOCK');		// 您输入的密码错误次数过多，帐号将被锁定1小时
				// 发送锁定通知
				model('Notify')->sendNotify($uid, 'user_lock');

				cookie('login_error_time', null);

				if(empty($this->rel)) {
					D('')->table(C('DB_PREFIX').'login_record')->add($save);
				} else {
					D('')->table(C('DB_PREFIX').'login_record')->where($m)->save($save);
				}
			}
			return false;
		} else {
			$logData['uid'] = $uid;
			$logData['ip'] = get_client_ip();
			$logData['ctime'] = time();
			D('')->table(C('DB_PREFIX').'login_logs')->add($logData);
			return $user;
		}
	}

	/**
	 * 使用本地帐号登陆（密码为null时不参与验证）
	 * @param string $login 登录名称，邮箱或用户名
	 * @param string $password 密码
	 * @param boolean $is_remember_me 是否记录登录状态，默认为false
	 * @return boolean 是否登录成功
	 */
	public function loginLocal($login, $password = null, $is_remember_me = false) {
		$user = $this->getLocalUser($login, $password);
		return $user['uid']>0 ? $this->_recordLogin($user['uid'], $is_remember_me) : false;
	}

	/**
	 * 使用本地帐号登陆，无密码
	 * @param string $login 登录名称，邮箱或用户名
	 * @param boolean $is_remember_me 是否记录登录状态，默认为false
	 * @return boolean 是否登录成功
	 */
	public function loginLocalWhitoutPassword($login, $is_remember_me = false) {
		$login = t($login);
		if(empty($login)) {
			$this->error = L('PUBLIC_ACCOUNT_NOTEMPTY');			// 帐号不能为空
			return false;
		}

		$user = D('User')->where(array('login'=>$login))->find();

		if(!$user) {
			$this->error = L('PUBLIC_ACCOUNT_NOEXIST');				// 帐号不存在
			return false;
		}

		return $user['uid']>0 ? $this->_recordLogin($user['uid'], $is_remeber_me) : false;
	}

	/**
	 * 设置登录状态、记录登录日志
	 * @param integer $uid 用户ID
	 * @param boolean $is_remember_me 是否记录登录状态，默认为false
	 * @return boolean 操作是否成功
	 */
	private function _recordLogin($uid, $is_remember_me = false) {

		// 注册cookie
		if(!$this->getCookieUid() && $is_remember_me ) {
			$expire = 3600 * 24 * 365;
			cookie('TSV3_LOGGED_USER', $this->jiami(C('SECURE_CODE').".{$uid}"), $expire);
		}

		// 记住活跃时间
		cookie('TSV3_ACTIVE_TIME',time() + 60 * 30);
		cookie('login_error_time', null);

		// 更新登陆时间
		model('User')->setField('last_login_time', $_SERVER['REQUEST_TIME'], 'uid='.$uid );
		
		// 记录登陆日志，首次登陆判断
		empty($this->rel) && $this->rel	= D('')->table(C('DB_PREFIX').'login_record')->where("uid = ".$uid)->find();
		
		// 注册session
		$_SESSION['mid'] = intval($uid);
		$_SESSION['SITE_KEY']=getSiteKey();
		
		$inviterInfo = model('User')->getUserInfo($uid);
	
		$map['uid'] = $uid;
		$map['ip'] = get_client_ip();
		$map['ctime'] = time();
		$map['locktime'] = 0;

		if($this->rel) {
			D('')->table(C('DB_PREFIX').'login_record')->where("uid = ".$uid)->save($map);
		} else {
			D('')->table(C('DB_PREFIX').'login_record')->add($map);
		}
		
		return true;
	}

	/**
	 * 注销本地登录
	 * @return void
	 */
	public function logoutLocal() {
		unset($_SESSION['mid'],$_SESSION['SITE_KEY']); // 注销session
		cookie('TSV3_LOGGED_USER', NULL);	// 注销cookie
	}

	/**
	 * 获取cookie中记录的用户ID
	 * @return integer cookie中记录的用户ID
	 */
	public function getCookieUid() {
		static $cookie_uid = null;
		if(isset($cookie_uid) && $cookie_uid !== null) {
			return $cookie_uid;
		}

		$cookie = cookie('TSV3_LOGGED_USER');
		
		$cookie = explode(".", $this->jiemi($cookie));

		$cookie_uid = ($cookie[0] != C('SECURE_CODE')) ? false : $cookie[1];
		
		return $cookie_uid;
	}

	/**
	 * 判断email地址是否合法
	 * @param string $email 邮件地址
	 * @return boolean 邮件地址是否合法
	 */
	public function isValidEmail($email) {
		return preg_match("/[_a-zA-Z\d\-\.]+@[_a-zA-Z\d\-]+(\.[_a-zA-Z\d\-]+)+$/i", $email) !== 0;
	}

	/**
	 * 加密函数
	 * @param string $txt 需加密的字符串
	 * @param string $key 加密密钥，默认读取SECURE_CODE配置
	 * @return string 加密后的字符串
	 */
	private function jiami($txt, $key = null) {
		empty($key) && $key = C('SECURE_CODE');
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-=_";
		$nh = rand(0, 64);
		$ch = $chars[$nh];
		$mdKey = md5($key.$ch);
		$mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
		$txt = base64_encode($txt);
		$tmp = '';
		$i = 0;
		$j = 0;
		$k = 0;
		for($i = 0; $i < strlen($txt); $i++) {
			$k = $k == strlen($mdKey) ? 0 : $k;
			$j = ($nh + strpos($chars, $txt [$i]) + ord($mdKey[$k++])) % 64;
			$tmp .= $chars[$j];
		}
		return $ch.$tmp;
	}

	/**
	 * 解密函数
	 * @param string $txt 待解密的字符串
	 * @param string $key 解密密钥，默认读取SECURE_CODE配置
	 * @return string 解密后的字符串
	 */
	private function jiemi($txt, $key = null) {
		empty($key) && $key = C('SECURE_CODE');
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-=_";
		$ch = $txt[0];
		$nh = strpos($chars, $ch);
		$mdKey = md5($key.$ch);
		$mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
		$txt = substr($txt, 1);
		$tmp = '';
		$i = 0;
		$j = 0;
		$k = 0;
		for($i = 0; $i < strlen($txt); $i++) {
			$k = $k == strlen($mdKey) ? 0 : $k;
			$j = strpos($chars, $txt[$i]) - $nh - ord($mdKey[$k++]);
			while($j < 0) {
				$j += 64;
			}
			$tmp .= $chars[$j];
		}
		return base64_decode($tmp);
	}
}