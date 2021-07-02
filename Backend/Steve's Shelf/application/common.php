<?php
//关闭错误警告
error_reporting(E_ERROR | E_PARSE );
//加载异常信息语言包
\think\Lang::load(APP_PATH . 'common'.DS.'lang'.DS.'error.php');
//获取http类型
define("ROOT", getRoot());
define("HTTP", getHttpType());

function AjaxReturn($err_code, $data = []){
	$err_code=(int)$err_code;
	$err_code=$err_code>1?1:$err_code;
    $rs = ['code'=>$err_code,'msg'=>getErrorInfo($err_code)];
    if(!empty($data))$rs['data'] = $data;
    return $rs;
}

function getHttpType(){
	return ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
}

//获取文件夹路径
function getRoot(){
	$_temp = explode('.php', $_SERVER['PHP_SELF']);
	$_php_self=rtrim(str_replace($_SERVER['HTTP_HOST'], '', $_temp[0] . '.php'), '/');
	$_root = rtrim(dirname($_php_self), '/');
	return (('/' == $_root || '\\' == $_root) ? '' : $_root);
}

/** 
* 数字转换为中文 
* @param  string|integer|float  $num  目标数字 
* @param  integer $mode 模式[true:金额（默认）,false:普通数字表示] 
* @param  boolean $sim 使用小写（默认） 
* @return string 
*/  
function number2chinese($num,$mode = true,$sim = true){  
    if(!is_numeric($num)) return '含有非数字非小数点字符！';  
    $char    = $sim ? array('零','一','二','三','四','五','六','七','八','九')  
    : array('零','壹','贰','叁','肆','伍','陆','柒','捌','玖');  
    $unit    = $sim ? array('','十','百','千','','万','亿','兆')  
    : array('','拾','佰','仟','','萬','億','兆');  
    $retval  = $mode ? '元':'点';  
  
    //小数部分  
    if(strpos($num, '.')){  
        list($num,$dec) = explode('.', $num);  
        $dec = strval(round($dec,2));  
        if($mode){  
            $dec['0']&&$retval .= "{$char[$dec['0']]}角";  
            $dec['1']&&$retval .= "{$char[$dec['1']]}分";
        }else{  
            for($i = 0,$c = strlen($dec);$i < $c;$i++) {  
                $retval .= $char[$dec[$i]];  
            }  
        }  
    }
    //整数部分  
    $str = $mode ? strrev(intval($num)) : strrev($num);  
    for($i = 0,$c = strlen($str);$i < $c;$i++) {  
        $out[$i] = $char[$str[$i]];  
        if($mode){  
            $out[$i] .= $str[$i] != '0'? $unit[$i%4] : '';  
                if($i>1 and $str[$i]+$str[$i-1] == 0){  
                $out[$i] = '';  
            }  
                if($i%4 == 0){  
                $out[$i] .= $unit[4+floor($i/4)];  
            }  
        }  
    }  
    $retval = join('',array_reverse($out)) . $retval;  
    if($retval!="零元"&&mb_substr($retval,-2,2,"UTF-8")=="零元"){
        $retval=str_replace("零元","元整",$retval);
    }
    return $retval;  
}  

function unicode_encode($str) {
    $json=json_encode(['str'=>$str]);
    $st =stripos($json,'{"str":"');
    $ed =stripos($json,'"}');
    $new_str=substr($json,($st+8),($ed-$st-8));
    return $new_str;
}

function rcopy($src, $dest) {
    if (is_dir($src)) {
        @mkdir($dest);
        $base = opendir($src);
        while($file = readdir($base)) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            rcopy($src.DS.$file, $dest.DS.$file);
        }
        closedir($base);
    }
    if (is_file($src)) {
        copy($src, $dest);
    }
    return true;
}

function rmdirs($dir) {
    if (is_dir($dir)) {
        $dirs = scandir($dir);
        foreach ($dirs as $d) {
            if ($d == '.' || $d == '..') {
                continue;
            }
            rmdirs($dir . DS . $d);
        }
        rmdir($dir);
    } else {
        @unlink($dir);
    }
}
