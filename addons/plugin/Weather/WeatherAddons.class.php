<?php
/**
 * TS插件 - 天气预报插件
 * @author 程序_小时代
 * @version TS3.0
 */
class WeatherAddons extends NormalAddons
{
	protected $version = '2.0';
	protected $author = '程序_小时代';
	protected $site = '';
	protected $info = '天气预报，根据IP获取该城市3天内天气信息';
	protected $pluginName = '天气预报';
	protected $sqlfile = '暂无';
	protected $tsVersion = "2.8";
	
	/**
	 * 获的改插件使用了那些钩子聚合类，那些钩子是需要进行排序的
	 * @return void
	 */
	public function getHooksInfo()
	{
		$hooks['list'] = array('WeatherHooks');
		return $hooks;
	}

	/**
	 * 后台管理入口
	 * @return array 管理相关数据
	 */
	public function adminMenu()
	{
		// $menu = array('config'=>'天气管理');
		// return $menu;
	}

	public function start()
	{

	}

	public function install()
	{
		return true;
	}

	public function uninstall()
	{
		return true;
	}
}