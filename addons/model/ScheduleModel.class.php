<?php
/**
 * 计划任务模型 - 数据对象模型
 * @example
 * task_to_run 		要执行任务的url
 * schedule_type 	计划类型：NOCE/MINUTE/HOURLY/DAILY/WEEKLY/MONTHLY
 * modifier 		计划频率
 * dirlist 			指定周或月的一天
 * month 			指定年的一个月
 * start_datetime  	计划生效日期
 * end_datetime 	计划过期日期
 * last_run_time 	上次运行时间
 * @author jason <yangjs17@yeah.net>
 * @version TS3.0
 */
class ScheduleModel extends Model {

	protected $tableName = 'schedule';
	protected $fields = array (0=>'id',1=>'task_to_run',2=>'schedule_type',3=>'modifier',4=>'dirlist',5=>'month',6=>'start_datetime',7=>'end_datetime',8=>'last_run_time',9=>'info');

	private $MONTH_ARRAY = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');			// 月数组
	private $WEEK_ARRAY = array('Mon','Tue','Wed','Thu','Fri','Sat','Sun');											// 周数组

	private $schedule = array();				// 计划任务字段
	private $scheduleList = array();			// 计划任务列表字段
	private $logpath = '';						// 日志路径字段
	
	/**
	 * 初始化方法
	 * 1.判断是否安装、是否运行该服务，系统服务可以不做判断
	 * 2.服务初始化
	 * @return [type] [description]
	 */
	public function _initialize() {
		$this->init();
	}

	/**
	 * 模型初始化，设置日志路径
	 * @return void
	 */	
	public function init() {
		$this->logpath = defined('LOG_PATH') ? LOG_PATH : DATA_PATH.'/schedulelogs';
		!is_dir($this->logpath) && mk_dir($this->logpath,0775);
	}

	/**
	 * 验证一个schedule是否有效
	 * @param string  $schedule 计划任务的Key值
	 * @return boolean schedule是否有效
	 */
	public function isValidSchedule($schedule = '') {
		if( empty($schedule) ) {
			$schedule = $this->schedule;
		}
		// 参数task_to_run、schedule_type、start_datetime必须存在
		if(empty($schedule['task_to_run']) || empty($schedule['schedule_type']) || empty($schedule['start_datetime'])) {
			return false;
		}
		switch(strtoupper($schedule['schedule_type'])) {
			case 'ONCE':
				return $this->_checkONCE($schedule);
			case 'MINUTE':
				return $this->_checkMINUTE($schedule);
			case 'HOURLY':
				return $this->_checkHOURLY($schedule);
			case 'DAILY':
				return $this->_checkDAILY($schedule);
			case 'WEEKLY':
				return $this->_checkWEEKLY($schedule);
			case 'MONTHLY':
				return $this->_checkMONTHLY($schedule);
			default:
				return false;
		}
	}
	
	/**
	 * 可执行的计划任务列表
	 * @param array $scheduleList 计划任务列表数组
	 * @return boolean 是否执行成功
	 */
	public function runScheduleList($scheduleList) {
		foreach( $scheduleList as $key => $schedule) {
			$date = $this->calculateNextRunTime($schedule);
			if($date != false && strtotime($date) <= strtotime(date('Y-m-d H:i:s'))) {
				$this->runSchedule($schedule);
			} else {
				continue;
			}
		}

		return true;
	}
	
	/**
	 * 执行计划任务
	 * @param string $schedule 计划任务key值
	 * @return void
	 */
	public function runSchedule($schedule) {		
		// 解析task类型, 并运行task
		$task_to_run = unserialize($schedule['task_to_run']);
	
		if(strtoupper($schedule['schedule_type']) == 'ONCE') {
			// ONCE类型的计划任务，将end_datetime设置为当前时间
			$schedule['end_datetime'] = date('Y-m-d H:i:s');
		} else {
			// 非ONCE类型的计划任务，防止由程序执行导致的启动时间的漂移
			if(in_array($schedule['schedule_type'], array('MINUTE', 'HOURLY'))) {
				// 将last_run_time设置为当前时间（秒数设为0）
				$schedule['last_run_time'] = date('Y-m-d H:i:s', $this->setSecondToZero());
			} else {
				// 将last_run_time设置为当前日期 + 预定时间
				$now_date = date('Y-m-d');
				$fixed_time = date('H:i:s', strtotime($schedule['start_datetime']));
				$schedule['last_run_time'] = $now_date.' '.$fixed_time;
			}
        }
        // 先更新数据库，再执行回调
        $this->saveSchedule($schedule);

		if($task_to_run['type'] == 'model') {
			// 组装执行代码
			$str = "D({$task_to_run['model']}, {$task_to_run['app']})->{$task_to_run['method']}(".$this->fill_params($task_to_run['params']).');';
			eval($str);
		} else if($task_to_run['type'] == 'url') {
			// URL执行待完成
		}
	
		$str_log = "schedule_id = {$schedule['id']} 已运行。任务url为: {$schedule['task_to_run']} .";
		$this->_log($str_log);
	}
	
	/**
	 * 组装参数
	 * @param string|array $params 参数信息
	 * @return string 组装后的参数
	 */
    private function fill_params($params = '') {
   		$result = '';
    	if(is_array($params)) {
    		$flag = true;
	    	foreach($params as $k => $v) {
	    		if($flag == true) {
	    			$result = $result.$this->format_params($v);
	    			$flag = false;
	    		} else {
	    			$result = $result.','.$this->format_params($v);
	    		}
	    	}
    	} else {
    		$result = $params;
    	}

    	return $result;
    }

    /**
     * 格式化参数
     * @param string|array $params 参数信息
     * @return string 格式化后的参数
     */
    private function format_params($params) {
    	if(is_array($params)) {
	    	$result = 'Array(';
	    	foreach($params as $k => $v) {
	    		$result = $result."'$k'=>'$v',";
	    	}
	    	$result .= ')';
	    	return $result;
    	} else {
    		return '\''.$params.'\'';
    	}
    }

	/**
	 * 添加一条计划任务到数据库
	 * @param string $schedule 计划任务Key值
	 * @return mix 添加失败返回false，添加成功返回新的计划任务ID
	 */
	public function addSchedule($schedule = '') {
		$schedule = $this->_formatSchedule($schedule);
		if(empty($schedule)) {
			$schedule = $this->schedule;
		}

		// 保存到数据库
		if($this->isValidSchedule($schedule)) {
			$schedule['start_datetime'] = date('Y-m-d H:i:s', $this->setSecondToZero($schedule['start_datetime']));
			return $this->add($schedule);
		} else {
			return false;
		}
	}
	
	/**
	 * 更新一条计划任务
	 * @param string $schedule 计划任务Key值
	 * @return mix 更新失败返回false，更新成功返回更新的计划任务ID
	 */
	public function saveSchedule($schedule = '') {
		if(empty($schedule)) {
			$schedule = $this->schedule;
		}
		// 更新到数据库
		if($this->isValidSchedule($schedule)) {
			$schedule['start_datetime'] = date('Y-m-d H:i:s', $this->setSecondToZero($schedule['start_datetime']));
			$res = $this->save($schedule);
			return $res;
		} else {
			return false;
		}
	}

	/**
	 * 查询数据库，获取所有的计划任务（包括过期的计划任务）
	 * @return array 计划任务列表
	 */
	public function getScheduleList() {
		$this->scheduleList = $this->order('id')->findAll();
		return $this->scheduleList;
	}

	/**
	 * 获取一个sechedule的下次执行时间
	 * @param string $schedule 计划任务Key值
	 * @return string 返回时间格式字符串，格式为Y-m-d H:i:s
	 */
	public function calculateNextRunTime($schedule) {
		// 已过期
		if(!empty($schedule['end_datetime']) && (strtotime($schedule['end_datetime']) < strtotime(date('Y-m-d H:i:s')))) {
			return false;
		}
		// 还未启动
		if(strtotime($schedule['start_datetime']) > strtotime(date('Y-m-d H:i:s'))) {
			return false;
		}
		// 已执行
		if(strtotime($schedule['last_run_time']) > strtotime(date('Y-m-d H:i:s'))) {
			return false;
		}

		switch(strtoupper($schedule['schedule_type'])) {
			case 'ONCE':
				$datetime = $this->_calculateONCE($schedule);
				break;
			case 'MINUTE':
				$datetime = $this->_calculateMINUTE($schedule);
				break;
			case 'HOURLY':
				$datetime = $this->_calculateHOURLY($schedule);
				break;
			case 'DAILY':
				$datetime = $this->_calculateDAILY($schedule);
				break;
			case 'WEEKLY':
				$datetime = $this->_calculateWEEKLY($schedule);
				break;
			case 'MONTHLY':
				$datetime = $this->_calculateMONTHLY($schedule);
				break;
			default:
				return false;
		}

		return date('Y-m-d H:i:s', $datetime);
	}	

	/**
	 * 获取计划任务日志路径
	 * @return string 计划任务日志路径
	 */
	public function getLogPath() {
		return $this->logpath;
	}

	/**
	 * 设置计划任务日志路径
	 * @param string 计划任务日志路径
	 */	
	public function setLogPath($path) {
		$this->logpath = $path;
	}
	
	/**
	 * 获取计划任务Key值
	 * @return string 计划任务Key值
	 */
	public function getSchedule() {
		return $this->schedule;
	}

	/**
	 * 设置计划任务Key值
	 * @param string 计划任务Key值
	 * @return boolean 是否设置成功
	 */
	public function setSchedule($schedule) {
		if($this->isValidSchedule($schedule)) {
			$this->schedule = $schedule;
			return  true;
		} else {
			return false;
		}
	}

	/**
	 * 设置要执行任务的url
	 * @param string $task_to_run 要执行任务的url
	 * @return void
	 */
	public function setTaskToRun($task_to_run) {
		$this->schedule['task_to_run'] = $task_to_run;
	}

	/**
	 * 设置计划类型：NOCE/MINUTE/HOURLY/DAILY/WEEKLY/MONTHLY
	 * @param string $schedule_type 计划类型：NOCE/MINUTE/HOURLY/DAILY/WEEKLY/MONTHLY
	 * @return void
	 */
	public function setScheduleType($schedule_type) {
		$this->schedule['schedule_type'] = $schedule_type;
	}

	/**
	 * 设置计划频率
	 * @param integer $modifier 计划频率
	 * @return void
	 */
	public function setModifier($modifier) {
		$this->schedule['modifier'] = $modifier;
	}

	/**
	 * 设置计划任务指定周或月的一天
	 * @param string $dirlist 计划任务指定周或月的一天
	 * @return void
	 */
	public function setDirlist($dirlist) {
		$this->schedule['dirlist'] = $dirlist;
	}

	/**
	 * 设置计划任务指定年的一个月
	 * @param string $month 计划任务指定年的一个月
	 * @return void
	 */
	public function setMonth($month) {
		$this->schedule['month'] = $month;
	}

	/**
	 * 设置计划任务开始时间
	 * @param string $start_datetime 计划任务开始时间
	 * @return void
	 */
	public function setStartDateTime($start_datetime) {
		$this->schedule['start_datetime'] = $start_datetime;
	}

	/**
	 * 设置计划任务失效时间
	 * @param string $end_datetime 计划任务失效时间
	 * @return void
	 */
	public function setEndDateTime($end_datetime) {
		$this->schedule['end_datetime'] = $end_datetime;
	}

	/**
	 * 设置计划任务上次执行时间
	 * @param string $last_run_time 计划任务上次执行时间
	 * @return void
	 */
	public function setLastRunTime($last_run_time) {
		$this->schedule['last_run_time'] = $last_run_time;
	}

	/*** 根据计划频率检查一个schedule是否合法 ***/
	/**
	 * 频率为一次
	 * @param string $schedule 计划任务Key值
	 * @return boolean schedule是否合法
	 */
	protected function _checkONCE($schedule) {
		if(!empty($schedule['start_datetime'])) {
			return (bool)strtotime($schedule['start_datetime']);
		} else {
			return false;
		}
	}

	/**
	 * 频率为每分钟
	 * @param string $schedule 计划任务Key值
	 * @return boolean schedule是否合法
	 */	
	protected function _checkMINUTE($schedule) {
		if(!empty($schedule['modifier']) && is_numeric($schedule['modifier'])) {
			return(($schedule['modifier'] >= 1) && ($schedule['modifier'] <= 1439));
		}

		return true;
	}

	/**
	 * 频率为每小时
	 * @param string $schedule 计划任务Key值
	 * @return boolean schedule是否合法
	 */
	protected function _checkHOURLY($schedule) {
		if(!empty($schedule['modifier'])) {
			return(is_numeric($schedule['modifier']) && ($schedule['modifier'] >= 1) && ($schedule['modifier'] <= 23));
		}

		return true;
	}

	/**
	 * 频率为每天
	 * @param string $schedule 计划任务Key值
	 * @return boolean schedule是否合法
	 */	
	protected function _checkDAILY($schedule) {
		if(!empty($schedule['modifier'])) {
			return(is_numeric($schedule['modifier']) && ($schedule['modifier'] >= 1) && ($schedule['modifier'] <= 365));
		}

		return true;
	}

	/**
	 * 频率为每周
	 * @param string $schedule 计划任务Key值
	 * @return boolean schedule是否合法
	 */		
	protected function _checkWEEKLY($schedule) {
		$flag = true;
		if(!empty($schedule['modifier'])) {
			if(!is_numeric($schedule['modifier'])) {
				return false;
			}
			$flag = ($schedule['modifier'] >= 1) && ($schedule['modifier'] <= 52);
		}
		if(($flag != false) && !empty($schedule['dirlist'])) {
			if($schedule['dirlist'] == '*') {
				return true;
			} else {
				$dirlist = explode(',', str_replace(' ', '', $schedule['dirlist']));
				foreach($dirlist as $v) {
					$flag = $flag && in_array($v, $this->WEEK_ARRAY);
					if($flag == false) {
						return false;
					}// End if
				}// End foreach
			}// End else
		}

		return $flag;
	}
	
	/**
	 * 频率为每月
	 * @param string $schedule 计划任务Key值
	 * @return boolean schedule是否合法
	 */	
	protected function _checkMONTHLY($schedule) {
		// modifier为LASTDAY时month必须，否则可选
		// modifier为（FIRST,SECOND,THIRD,FOURTH,LAST）之一时：dirlist必须在MON～SUN、*中
		// modifier为1～12时dirlist可选. 1～31和空为有效值（默认是1）
		if(!empty($schedule['modifier'])) {
			// modifier为LASTDAY时month必须，否则可选
			if(strtoupper($schedule['modifier']) == 'LASTDAY') {
				if(empty($schedule['month'])) {
					return false;
				}
			} else if(in_array(strtoupper($schedule['modifier']), array('FIRST','SECOND','THIRD','FOURTH','LAST'))) {
				// modifier为FIRST,SECOND,THIRD,FOURTH,LAST之一时，dirlist必须在MON～SUN、*中
				if($schedule['dirlist'] == '*') {
					;
				} else {
					$flag = true;
					$dirlist = explode(',', str_replace(' ', '', $schedule['dirlist']));
					foreach($dirlist as $v) {
						$flag = $flag && in_array($v, $this->WEEK_ARRAY);
						if($flag == false) {
							return false;
						}// End if
					}// End foreach
				}// End if...else
			} else if(is_numeric($schedule['modifier']) && ($schedule['modifier'] >= 1) && ($schedule['modifier'] <= 12)) {
				// modifier为1～12时dirlist可选. 空、1～31为有效值（‘空’默认是1）
				if(!empty($schedule['dirlist'])) {
					$flag = true;
					$dirlist = explode(',', str_replace(' ', '', $schedule['dirlist']));
					foreach($dirlist as $v) {
						$flag = $flag && (is_numeric($v) && ($v >= 1) && ($v <= 31));
						if($flag == false) {
							return false;
						}
					}// End foreach
				}
				return true;
			} else {
				// modifier错误
				return false;
			}
			// month的有效值为JAN～DEC和*(每个月).默认为*
			if(!empty($schedule['month'])) {
				if($schedule['month'] == '*') {
					return true;
				} else {
					$flag = true;
					$month = explode(',', str_replace(' ', '', $schedule['month']));
					foreach($month as $v) {
						$flag = $flag && in_array($v, $this->MONTH_ARRAY);
						if($flag == false) {
							return false;
						}
					}// End foreach
				}// End if...else
			}
		} else {
			// modifier必须
			return false;
		}

		return true;
	}

	/*** 根据计划频率计算一个schedule的下次执行时间 ***/
	/**
	 * 频率为一次
	 * @param string $schedule 计划任务Key值
	 * @return string schedule的下次执行时间
	 */	
	protected function _calculateONCE($schedule) {
		return $this->_getStartDateTime($schedule);
	}

	/**
	 * 频率为每分钟
	 * @param string $schedule 计划任务Key值
	 * @return string schedule的下次执行时间
	 */	
	protected function _calculateMINUTE($schedule) {
		// 获取计划频率
		$modifier = empty($schedule['modifier']) ? 1 : $schedule['modifier'];
		// 当last_run_time不为空且大于start_datetime时，以last_run_time为基准时间。否则，以start_datetime为基准时间.
		if(!empty($schedule['last_run_time']) && (strtotime($schedule['last_run_time']) > strtotime($schedule['start_datetime']))) {
			$date = is_string($schedule['last_run_time']) ? strtotime($schedule['last_run_time']) : $schedule['last_run_time'];
		} else {
			$date = $this->_getStartDateTime($schedule);
		}
		
		return mktime(date('H',$date),date('i',$date) + $modifier,date('s',$date),date('m',$date),date('d',$date),date('Y',$date));
	}

	/**
	 * 频率为每小时
	 * @param string $schedule 计划任务Key值
	 * @return string schedule的下次执行时间
	 */	
	protected function _calculateHOURLY($schedule) {
		// 获取计划频率
		$modifier = empty($schedule['modifier']) ? 1 : $schedule['modifier'];
		// 当last_run_time不为空时，根据last_run_time计算下次运行时间。否则，根据start_datetime计算.
		if(!empty($schedule['last_run_time']) && (strtotime($schedule['last_run_time']) > strtotime($schedule['start_datetime']))) {
			$date = is_string($schedule['last_run_time']) ? strtotime($schedule['last_run_time']) : $schedule['last_run_time'];
		} else {
			$date = $this->_getStartDateTime($schedule);
		}
		
		return mktime(date('H',$date) + $modifier,date('i',$date),date('s',$date),date('m',$date),date('d',$date),date('Y',$date));
	}

	/**
	 * 频率为每天
	 * @param string $schedule 计划任务Key值
	 * @return string schedule的下次执行时间
	 */	
	protected function _calculateDAILY($schedule) {
		// 获取计划频率
		$modifier = empty($schedule['modifier']) ? 1 : $schedule['modifier'];
		// 当last_run_time不为空时，根据last_run_time计算下次运行时间。否则，根据start_datetime计算.
		if(!empty($schedule['last_run_time']) && (strtotime($schedule['last_run_time']) > strtotime($schedule['start_datetime']))) {
			$date = is_string($schedule['last_run_time']) ? strtotime($schedule['last_run_time']) : $schedule['last_run_time'];
		} else {
			$date = $this->_getStartDateTime($schedule);
		}
		
		return mktime(date('H',$date),date('i',$date),date('s',$date),date('m',$date),date('d',$date) + $modifier,date('Y',$date));
	}

	/**
	 * 频率为每周
	 * @param string $schedule 计划任务Key值
	 * @return string schedule的下次执行时间
	 */	
	protected function _calculateWEEKLY($schedule) {
		// 获取计划频率
		$modifier = empty($schedule['modifier']) ? 1 : $schedule['modifier'];
		// 当last_run_time不为空时，以last_run_time为基准时间。否则，根据start_datetime计算基准时间.
		if( !empty($schedule['last_run_time']) && (strtotime($schedule['last_run_time']) > strtotime($schedule['start_datetime'])) ) {
			$date = is_string($schedule['last_run_time']) ? strtotime($schedule['last_run_time']) : $schedule['last_run_time'];
			$base_time_type = 'last_run_time';
		} else {
			$date = $this->_getStartDateTime($schedule);
			$base_time_type = 'start_datetime';
		}
		// 判断当前日期是否符合周数要求
		// 计算方法：((当前日期的周数 - 基准日期的周数) % modifier == 0)
		if((($this->_getWeekID() - $this->_getWeekID($date)) % $schedule['modifier']) == 0) {
			// 组装dirlist数组
			if(empty($schedule['dirlist'])) {
				// 当dirlist为空时,默认为周一
				$schedule['dirlist'] = array('Mon');
			} else if($schedule['dirlist'] == '*') {
				// 当dirlist==*时，每天执行
				$schedule['dirlist'] = $this->WEEK_ARRAY;
			} else {
				$schedule['dirlist'] = explode(',', str_replace(' ', '', $schedule['dirlist']));
			}
			// 判断今天是否在dirlist中
			if(in_array(date('D'), $schedule['dirlist'])) {
				// 判断今天是否已经执行过当前计划。如果否，根据基准时间计算执行时间（DATE为今天，TIME来自基准时间）
				if(($base_time_type == 'last_run_time') && ( date('Y-m-d',$date) == date('Y-m-d'))) {
					;
				} else {
					return mktime(date('H',$date),date('i',$date),date('s',$date),date('m'),date('d'),date('Y'));
				}
			}
		}
		// 如果当前日期不符合周数或星期的要求、或今天已经执行过，返回明天的同一时间（保证该条计划任务现在不被执行）
		return mktime(date('H',$date),date('i',$date),date('s',$date),date('m'),date('d') + 1,date('Y'));
	}

	/**
	 * 频率为每月
	 * @param string $schedule 计划任务Key值
	 * @return string schedule的下次执行时间
	 */	
	protected function _calculateMONTHLY($schedule) {
		// 当last_run_time不为空时，以last_run_time为基准时间。否则，根据start_datetime计算基准时间.
		if(!empty($schedule['last_run_time']) && (strtotime($schedule['last_run_time']) > strtotime($schedule['start_datetime']))) {
			$date = is_string($schedule['last_run_time']) ? strtotime($schedule['last_run_time']) : $schedule['last_run_time'];
			$base_time_type = 'last_run_time';
		} else {
			$date = $this->_getStartDateTime($schedule);
			$base_time_type = 'start_datetime';
		}
		// 设置month数组
		if(empty($schedule['month']) || $schedule['month'] == '*') {
			$schedule['month'] = $this->MONTH_ARRAY;
		} else {
			$schedule['month'] = explode(',', str_replace(' ', '', $schedule['month']));
		}
		// modifier为LASTDAY时
		if(strtoupper($schedule['modifier']) == 'LASTDAY') {
			// 判断月份是否符合要求、且当前日期为月的最后一天
			if(in_array(date('M'), $schedule['month']) && $this->_isLastDayOfMonth()) {
				// 判断今天是否已经执行过当前计划。如果否，根据基准时间计算执行时间（DATE为今天，TIME来自基准时间）
				if(($base_time_type == 'last_run_time') && ( date('Y-m-d',$date) == date('Y-m-d'))) {
					;
				} else {
					return mktime(date('H',$date),date('i',$date),date('s',$date),date('m'),date('d'),date('Y'));
				}
			}
		// modifier为FIRST,SECOND,THIRD,FOURTH,LAST之一时
		} else if(in_array(strtoupper($schedule['modifier']), array('FIRST','SECOND','THIRD','FOURTH','LAST'))) {
			// 判断当前月份是否符合要求
			if(in_array(date('M'), $schedule['month'])) {
				// 设置dirlist数组(星期)
				if($schedule['dirlist'] == '*') {
					$schedule['dirlist'] = $this->WEEK_ARRAY;
				} else {
					$schedule['dirlist'] = explode(',', str_replace(' ', '', $schedule['dirlist']));
				}
				// 判断星期是否符合要求
				if(in_array(date('D'), $schedule['dirlist'])) {
					// 判断第x个是否符合要求
					if($this->_isDayIDOfMonth($schedule['modifier'])) {
						// 判断今天是否已经执行过当前计划。如果否，根据基准时间计算执行时间（DATE为今天，TIME来自基准时间）
						if(($base_time_type == 'last_run_time') && ( date('Y-m-d',$date) == date('Y-m-d'))) {
							;
						} else {
							return mktime(date('H',$date),date('i',$date),date('s',$date),date('m'),date('d'),date('Y'));
						}
					}
				}
			}
		// modifier为1～12时
		} else if(is_numeric($schedule['modifier'])) {
			// 判断当前月份是否符合要求
			if(($this->_getMonthDif($date) % $schedule['modifier']) == 0) {
				// 组装dirlist数组
				if(empty($schedule['dirlist'])) {
					$schedule['dirlist'] = array('1');
				} else {
					$schedule['dirlist'] = explode(',', str_replace(' ', '', $schedule['dirlist']));
				}
				// 判断当期日期是否符合要求
				if(in_array(date('d'), $schedule['dirlist']) || in_array(date('j'), $schedule['dirlist'])) {
					// 判断今天是否已经执行过当前计划。如果否，根据基准时间计算执行时间（DATE为今天，TIME来自基准时间）
					if(($base_time_type == 'last_run_time') && ( date('Y-m-d',$date) == date('Y-m-d'))) {
						;
					} else {
						return mktime(date('H',$date),date('i',$date),date('s',$date),date('m'),date('d'),date('Y'));
					}
				}
			}
		}
		// 如果当前日期不符合月份/星期/日期的要求、或今天已经执行过，返回明天的同一时间（保证该条计划任务现在不被执行）
		return mktime(date('H',$date),date('i',$date),date('s',$date),date('m'),date('d') + 1,date('Y'));
	}

	/**
	 * 获取指定schedule的开始时间戳
	 * @param string $schedule 计划任务Key值
	 * @return integer 指定schedule的开始时间戳
	 */
	protected function _getStartDateTime($schedule) {
		if(!empty($schedule['start_datetime'])) {
			return strtotime($schedule['start_datetime']);
		} else {
			return false;
		}
	}
	
	/**
	 * 判断当前日期是否为当前月的最后一天
	 * @param string $date 时间字符串，格式Y-m-d H:i:s
	 * @return boolean 当前日期是否为当前月的最后一天
	 */
	protected function _isLastDayOfMonth($date = '') {
		if (empty($date)) {
			$date = strtotime(date('Y-m-d H:i:s'));
		}
		$date = is_string($date) ? strtotime($date) : $date;
		return ( date('m',$date) != date('m',mktime(date('H',$date),date('i',$date),date('s',$date),date('m',$date),date('d',$date) + 1,date('Y',$date))) );
	}
	
	/**
	 * 判断当前日期是否为当前月的第x个星期x
	 * @param string $key 第几个星期的Key值，FIRST、SECOND、THIRD、FOURTH、LAST
	 * @param string $date 时间字符串，格式Y-m-d H:i:s
	 * @return boolean 判断当前日期是否为当前月的第x个星期x
	 */
	protected function _isDayIDOfMonth($key, $date = '') {
		if(empty($date)) {
			$date = strtotime(date('Y-m-d H:i:s'));
		}
		$date = is_string($date) ? strtotime($date) : $date;
		
		$index = 0;
		switch(strtoupper($key)) {
			case 'FIRST':
				$index = 1;
				break;
			case 'SECOND':
				$index = 2;
				break;
			case 'THIRD':
				$index = 3;
				break;
			case 'FOURTH':
				$index = 4;
				break;
			case 'LAST':
				$index = 0;
				break;
			default:
				return false;
		}
		if($index != 0) {
			return ((date('m',$date) == date('m',mktime(date('H',$date),date('i',$date),date('s',$date),date('m',$date),date('d',$date) - (7 * ($index-1)),date('Y',$date)))) && 
			(date('m',$date) != date('m',mktime(date('H',$date),date('i',$date),date('s',$date),date('m',$date),date('d',$date) - (7 * ($index)),date('Y',$date)))));
		} else {
			return (date('m',$date) != date('m',mktime(date('H',$date),date('i',$date),date('s',$date),date('m',$date),date('d',$date) + 7,date('Y',$date))));
		}
	}

	/**
	 * 返回自2007年01月01日来的周数
	 * @param string $date 时间字符串，格式Y-m-d H:i:s
	 * @return integer 自2007年01月01日来的周数
	 */
	protected function _getWeekID($date = '') {
		$date_base = strtotime('2007-01-01');			// 2007-01-01为周一，定为第一周
		//	输入日期为空时，使用当前时间
		if(empty($date)) {
			$date = strtotime(date('Y-m-d'));
		} else {
			$date = is_string($date) ? strtotime($date) : $date;
		}

		return (int)floor(($date - $date_base)/3600/24/7) + 1;
	}
	
	/**
	 * 返回data2时间与data1时间之间的月数，data2为空则返回自2007年01月01日来的月数
	 * @param string $date1 时间字符串，格式Y-m-d H:i:s
	 * @param string $date2 时间字符串，格式Y-m-d H:i:s，默认为空
	 * @return integer 两个时间之间的月数
	 */
	protected function _getMonthDif($date1, $date2 = '') {
		$date1 = is_string($date1) ? strtotime($date1) : $date1;
		$date2 = empty($date2) ? date('Y-m-d') : $date2;
		$date2 = is_string($date2) ? strtotime($date2) : $date2;
		
		return ((date('Y',$date2) - date('Y',$date1)) * 12 + (date('n',$date2) - date('n',$date1)) );
	}
	
	/**
	 * 记录计划任务日志文件
	 * @param string $str 日志内容
	 * @return void
	 */
	protected function _log($str) {
		$filename = $this->logpath.'schedule_'.date('Y-m-d').'.log';
		
		$str = '['.date('Y-m-d H:i:s').'] '.$str;
		$str .= "\r\n";
		
		$handle = fopen($filename, 'a');
		fwrite($handle, $str);
		fclose($handle);
	}
	
	/**
	 * 将给定时间的秒数置为0; 参数为空时，使用当前时间
	 * @param string $date_time 时间字符串，格式Y-m-d H:i:s
	 * @return integer 给定时间的秒数置为0; 参数为空时，使用当前时间
	 */
	protected function setSecondToZero($date_time = NULL) {
		if(empty($date_time)) {
			$date_time = date('Y-m-d H:i:s');
		}
		$date_time = is_string($date_time) ? strtotime($date_time) : $date_time;
		return mktime(date('H', $date_time), 
					  date('i', $date_time),
					  0,
					  date('m', $date_time),
					  date('d', $date_time),
					  date('Y', $date_time));
	}

	/**
	 * 运行计划任务接口，继承实现父类函数
	 * @return 空
	 */
	public function run() {
		// 锁定自动执行 修正一下
		$lockfile = RUNTIME_PATH.'/schedule.lock';
		if(file_exists($lockfile) && (filemtime($lockfile) > $_SERVER['REQUEST_TIME'] - 60 )) {
			return ;
		} else {
			touch($lockfile);
		}
		set_time_limit(0);
		ignore_user_abort(true);
		// 执行计划任务
		$this->runScheduleList($this->getScheduleList());
		// 解除锁定
		unlink($lockfile);
		return ;
	}
	
	/**
	 * 格式化计划任务的数据
	 * @param array $post 计划任务的数据
	 * @return array 格式化后的计划任务数据
	 */
    public function _formatSchedule($post) {
		foreach($post as $k => $v) {
			if(empty($v)) {
                $post[$k] = NULL;
            } else {
                $post[$k] = t($v);
            }
		}
		
		$task = explode('/', $post['task_to_run']);
		$post['task_to_run'] = array('type'=>'model','app'=>$task[0],'model'=>$task[1],'method'=>$task[2]);
		$post['task_to_run'] = serialize($post['task_to_run']);
		unset($post['__hash__']);
		return $post;
	}
}