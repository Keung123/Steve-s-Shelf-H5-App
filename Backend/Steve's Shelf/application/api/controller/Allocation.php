<?php

namespace app\api\controller;

use app\common\model\Config as ConfigModel;

class Allocation extends Common {
    protected $config;
    public function __construct()
    {
        $this->config = new ConfigModel();
    }

    /**
     * 获取三方登录方式
    */
    public function apiLogin(){
        $info = $this->config->where(1)->field('base')->find();
        $list = [];
        if($info['base']){
            $list = json_decode($info, true);
        }
        return $this->json($list['base']['api_login']);
    }

    /**
     * 获取三方支付方式
     */
    public function apiPay(){
        $info = $this->config->where(1)->field('base')->find();

        $list = [];
        if($info['base']){
            $list = json_decode($info, true);
            $list = $list['base'];
            $row  = '';
            if($list['api_pay']){
                $row  = $list['api_pay'];
            }
        }
        return $this->json($list['api_pay']);
    }
}