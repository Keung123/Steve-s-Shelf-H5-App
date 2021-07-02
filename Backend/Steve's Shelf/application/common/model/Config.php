<?php
namespace app\common\model;

class Config extends Base{
    protected $type = [
        'base'      =>  'array',
        'sms'       =>  'array',
        'email'     =>  'array',
        'shop'      =>  'array',
        'app'       =>   'array',
        'apipay'    =>   'array',
        'wxpay'     =>   'array',
    ];	
}