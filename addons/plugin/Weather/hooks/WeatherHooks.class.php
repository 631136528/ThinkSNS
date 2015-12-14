<?php
/**
 * 天气预报钩子
 * @author 程序_小时代
 * @version TS3.0
 */
class WeatherHooks extends Hooks
{
	/**
     * 主页右上方钩子，加载换肤插件按钮
     * @return void
     */
    public function home_index_right_top()
    {
        $SA_IP = $this->_getClientIP();
        $cityCode = $this->_getIPLoc_sina($SA_IP);
        $this->assign('cityCode', $cityCode);
        $this->display('weather');
    }

    public function proxy()
    {
        $city = t($_REQUEST['city']);
        $data = file_get_contents('http://php.weather.sina.com.cn/iframe/index/w_cl.php?day=2&code=js&cbf=show&city='.$city);
        $data = auto_charset($data, 'gb2312', 'utf8');
        echo $data;
    }

    /**
     * 获取客户端IP
     * @return string 客户端IP
     */
    private function _getClientIP()
    {
	    if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {   
	        $SA_IP = getenv('HTTP_CLIENT_IP');   
	    } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {   
	        $SA_IP = getenv('HTTP_X_FORWARDED_FOR');   
	    } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {   
	        $SA_IP = getenv('REMOTE_ADDR');   
	    } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {   
	        $SA_IP = $_SERVER['REMOTE_ADDR'];   
	    }

	    return $SA_IP;
    }

    /**
     * 通过IP获取地区信息，新浪接口，使用需要开启php.ini中的curl
     * @param string $queryIP 查询的IP地址
     * @return string IP的相关地区信息
     */
    private function _getIPLoc_sina($queryIP)   
    {   
        // 获取地区信息API接口数据
        $location = file_get_contents('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip='.$queryIP);
        $location = json_decode($location);   
        $loc = "";   
        if($location === false) {
            return "";   
        }   
        if(empty($location->desc)) {   
            $loc = $location->city;   
            $full_loc = $location->province.$location->city.$location->district.$location->isp;   
        } else {   
            $loc = $location->desc;   
        }
        
        return $loc;   
    }
}