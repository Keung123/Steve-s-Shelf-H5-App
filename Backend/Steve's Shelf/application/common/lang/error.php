<?php

/**
 * 返回值格式
 */
//定义返回值字母格式     基础1000-1999，  用户：2000-2999 商品：3000-3999， 订单：4000-4999

//基础变量定义
define('SUCCESS', 1);
define('ERROR',0);
define('ADD_FAIL',-1000);
define('UPDATA_FAIL',-1001);
define('DELETE_FAIL',-1002);
define('SYSTEM_DELETE_FAIL',-1003);
define('WEIXIN_AUTH_ERROR', -1004);
define('NO_AITHORITY', -1005);
define('UPLOAD_ERROR', -1006);
define('CAPTCHA_ERROR',-1007);
//用户
define('USER_EMPTY',-2000);
define('PASSWORD_ERROR',-2001);
define('USERNAME_NOT',-2003);
define('PASSWORD_NOT',-2004);
define('REGISTER_ERROR',-2005);
define('NO_GOODS',-2006);
define('EROOR_PWD',-2007);
//订单
define('ORDER_ADD_FAIL',-4000);
define('ORDER_PAY_UPDATE_FAIL',-4001);

function getErrorInfo($error_code){
    $system_error_arr = [
        //基础变量
        SUCCESS  => '操作成功',
        ERROR=>'操作失败',
        LOGIN_ERROR=>'请填写6-16位字符,不能包含空格',
        JS_FALSE=>'结算失败',
        ADD_FAIL => '添加失败',
        UPDATA_FAIL => '修改失败',
        DELETE_FAIL => '删除失败',
        SYSTEM_DELETE_FAIL => '当前模块下存在子模块,不能删除!',
        NO_AITHORITY => '当前用户无权限',
        UPLOAD_ERROR=>'上传失败',
      
        //用户变量定义
        USER_EMPTY => '用户不存在',
        PASSWORD_ERROR=>'密码不正确',
        USERNAME_NOT=>'用户名不能为空',
        PASSWORD_NOT=>'密码不能为空',
        REGISTER_ERROR=>'注册失败',
        CAPTCHA_ERROR => '验证码错误',
        NO_GOODS =>'商品不存在',
        EROOR_PWD=>'密码为6~12位',
        //订单
        ORDER_ADD_FAIL=>'订单创建失败',
        ORDER_PAY_UPDATE_FAIL=>'订单支付状态修改失败',
        //活动
        ACTIVITY=>'活动时间输入非法'

    ];
    if(array_key_exists($error_code, $system_error_arr)){
        return $system_error_arr[$error_code];
    }elseif($error_code > 0){
        return '操作成功';
    }else{
        return '操作失败';
    }
}