<?php

namespace app\api\controller;

use app\common\service\Config as ConfigService;
use app\common\service\User as UserService;
use think\Request;

class Signin extends Common{
    protected $user;
    protected $config;
    public function __construct()
    {
        $this->user = new UserService();
        $this->config = new ConfigService();
    }

    /**
     * 签到列表
     * @param int uid
     * @param string token
     * @return Json
     */
    public function signList()
    {
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $uid = $this->getUid($token, $user_id);
        if (!$uid) {
            return $this->json([], 0, '未知参数');
        }
        $year = input('request.year');
        $month = input('request.month');
        $list = $this->user->qiandaolist($uid, $year, $month);
        return $this->json($list, 1);
    }

    /**
     * 签到
     * @param int uid
     * @param string token
     * @return Json
     */
    public function sign()
    {
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $uid = $this->getUid($token, $user_id);
        if (!$uid) {
            return $this->json([], 0, '未知参数');
        }
        $res = $this->user->is_qiandao($uid);
        if ($res) {
            return $this->json([], -1, '今日已签到');
        }
        $res = $this->user->qiandao($uid);
        if ($res) {
            return $this->json([], 1, '签到成功');
        } else {
            return $this->json([], 0, '签到失败');
        }
    }

    /**
     * 签到奖励列表
     * @param int uid
     * @param string token
     * @return Json
     */
    public function signAward()
    {
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $uid = $this->getUid($token, $user_id);
        if (!$uid) {
            return $this->json([], 0, '未知参数');
        }
        $res = $this->user->jl_qiaodao($uid);
        $month = date('m',time());
        if(!$res) {
            return $this->json([], 0, '无奖励');
        }
        return json(['data'=>$res,'status'=>1,'msg'=>'获取成功','month'=>$month]);
    }

    /**
     *  签到规则
     * @param string name
     * @return Json
     */
    public function  signRule()
    {
        $guize_name = input('request.name');
        $res = $this->config->getguiZe($guize_name);
        if(!$res){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($res);
    }
}
