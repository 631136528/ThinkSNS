<?php
/**
     * ***ProtocolModel 
     * 提供给TS核心调用的协议类
     *
     */
class WeibaProtocolModel extends Model {
	// 假删除用户数据
	function deleteUserAppData($uidArr) {
		$this->_deal($uidArr, 'deleteUserAppData');
	}
	// 恢复假删除的用户数据
	function rebackUserAppData($uidArr) {
		$this->_deal($uidArr, 'rebackUserAppData');
	}
	// 彻底删除用户数据
	function trueDeleteUserAppData($uidArr) {
		if (empty ( $uidArr ))
			return false;
		
		$uidStr = implode(',', $uidArr);
		
		M ( 'weiba' )->where ( "uid in ($uidStr) or admin_uid in ($uidStr)" )->delete();
		M ( 'weiba_post' )->where ( "post_uid in ($uidStr) or last_reply_uid in ($uidStr)" )->delete();
		M ( 'weiba_reply' )->where ( "uid in ($uidStr) or post_uid in ($uidStr)" )->delete();
		M ( 'weiba_follow' )->where ( "follower_uid in ($uidStr)" )->delete();
	}
	
	//共同处理方法
	function _deal($uidArr, $type) {
		if (empty ( $uidArr ))
			return false;
		
		$uidStr = implode(',', $uidArr);
	
		$value = 0;
		if($type=='deleteUserAppData'){
			$value = 1;
		}
	
		M ( 'weiba' )->where ( "uid in ($uidStr) or admin_uid in ($uidStr)" )->setField ( 'is_del', $value );
		M ( 'weiba_post' )->where ( "post_uid in ($uidStr) or last_reply_uid in ($uidStr)" )->setField ( 'is_del', $value );
		M ( 'weiba_reply' )->where ( "uid in ($uidStr) or post_uid in ($uidStr)" )->setField ( 'is_del', $value );
		M ( 'weiba_follow' )->where ( "follower_uid in ($uidStr)" )->setField ( 'is_del', $value );

	}	
}
