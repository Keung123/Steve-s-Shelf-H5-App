<?php

header("Access-Control-Allow-Origin:*");
header("Access-Control-Allow-Methods:GET, POST");
header("Access-Control-Allow-Headers:DNT,X-Mx-ReqToken,Keep-Alive,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type, Accept-Language, Origin, Accept-Encoding,A-Token");


if(!ini_get('date.timezone')){
	ini_set('date.timezone', 'Asia/Shanghai');
}
// [ 应用入口文件 ]

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');
defined('HT_ROOT') or define('HT_ROOT', dirname(__DIR__));
// 程序版本
define('VERSION', "1.05");
// 加载框架引导文件
require __DIR__ . '/../system/start.php';
