<?php

//显示无限极分类的HTML代码
/**
 * $field = array('id','name','pid','sort')
 *   <tr>
      <td colspan="10">
        {:showCatetree($tree,$field,$_func)}
      </td>
    </tr>
 */
//TODO 可以移动到functions中
function showCatetree($data,$field,$func,$p=array()){
	$pid = empty($p) ? "0" : $p[$field['id']];	
	$pname = empty($p) ? "-" : $p[$field['name']];
	//$display = empty($p) ? "":"style='display:none'";
	$display = '';
	$html ='<table width="100%" id="table'.$pid.'" '.$display.'>';
	foreach($data as $key=>$val){	//每行操作
		$html .="<tr overstyle='on'>";
		foreach($val as $k=>$v){	
			if(!in_array($k,$field) ){ continue;}
			if($k == $field['pid']){
				$html .="<td catetd ='yes' rel='{$val[$field['id']]}' width='20%'>".$pname."</td>";
			}else{
				$html .="<td catetd ='yes' rel='{$val[$field['id']]}' width='20%'>".$v."</td>";
			}
		}
		$html .="<td><span rel='edit' cateid='".$val[$field['id']]."' func='{$func}'>".L('PUBLIC_MODIFY')."</span>
			<span rel='move' cateid='".$val[$field['id']]."' func='{$func}'>".L('PUBLIC_MOVES')."</span>	
			<span rel='del' cateid='".$val[$field['id']]."' func='{$func}'>".L('PUBLIC_STREAM_DELETE')."</span></td></tr>";
		//递归	
		if(!empty($val['_child'])){
			$html .="<tr><td colspan='10'>".showCatetree($val['_child'],$field,$func,$val)."</td></tr>";
		}
	}
	return $html.'</table>';
}
//传统形式显示无限极分类树
/**
 * 
	$field = array('id'=>'','name'=>'','pid'=>,'sort')
 *   <tr><td>ID</td><td>部门</td><td>排序</td><td>操作</td></tr>
 *   {:showTree($tree,$field,$_func)}
 * @param unknown_type $data
 * @param unknown_type $field
 * @param unknown_type $func
 * @param unknown_type $p
 */
function showTree($data,$field,$func,$p=''){
	$html ='';
	$p    = empty($p) ? '' : $p.' - ';
	$big  = empty($p) ? "style='font-weight:bold'" : ''; 
	foreach($data as $key=>$val){
		$html .="<tr {$big}><td>{$val[$field['id']]}</td>
				 <td>{$p}{$val[$field['name']]}</td>"
				 //<td>{$val[$field['sort']]}</td>
				 ."<td><span rel='edit' cateid='".$val[$field['id']]."' func='{$func}'>".L('PUBLIC_MODIFY')."</span>
			<span rel='move' cateid='".$val[$field['id']]."' func='{$func}'>".L('PUBLIC_MOVES')."</span>
			<span rel='del' cateid='".$val[$field['id']]."' func='{$func}'>".L('PUBLIC_STREAM_DELETE')."</span></td></tr>";
		if(!empty($val['_child'])){
			$html .= showTree($val['_child'],$field,$func,$p.$val[$field['name']]);
		}
	}
	return $html;
}

function formatsize($fileSize) {
	$size = sprintf("%u", $fileSize);
	if($size == 0) {
		return("0 Bytes");
	}
	$sizename = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
	return round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $sizename[$i];
}