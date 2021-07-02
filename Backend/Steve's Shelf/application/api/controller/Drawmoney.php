<?php

namespace app\api\controller;

use app\common\service\User as UserService;
use think\Db;

class Drawmoney extends Common {
    protected $user;
    public function __construct()
    {
        $this->user = new UserService();
    }

    /**
     * 用户提现
     * @param int uid
     * @param string token
     * @param double cash_amount
     * @param int cash_way 提现方式：1，支付宝提现；2，微信提现； 3，银行卡提现；
     * @param string cash_appli 申请人姓名
     * @param string cash_account 提现账号 （支付宝昵称 or 提现银行 ）
     * @param string cash_account_no 提现账号 （支付宝昵称 or 提现银行 or 微信账号 ）
     * @return json
     */
    public function myInfoCash()
    {
        $user_id = input('request.uid');
       // $token = input('request.token');
        //$uid = $this->getUid($token, $user_id);
        $uid = $user_id;
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $data = [
            'cash_uid' => input('request.uid'),//会员id
            'cash_amount' => abs(input('request.cash_amount')),//提现金额
            'cash_appli' => input('request.cash_appli'),//申请人姓名
            'cash_way' => input('request.cash_way'),//提现方式：1，支付宝提现；2，微信提现；3，银行卡提现
            'cash_stat' => 1,//处理状态：1，未处理；2，支付完成；3，申请未通过
            'cash_addtime' => time(),// 申请时间
        ];
        //账户明细
        $accountlog = [
            'a_uid' => input('request.uid'),//会员id
            'acco_num' => (0 - abs(input('request.cash_amount'))),//账户变化总额 为负值
            'acco_type' => 1,//账户变更类型：1，提现；2，购物；3，充值；4，返利；5，分享；6，买购物券
            'acco_desc' => '提现',//账户变更详情
            'acco_time' => time(),// 日志创建时间
        ];
        $cash_way  = input('request.cash_way');
        if($cash_way == 1 ){
            $data['cash_ali_name'] = input('request.cash_account');//支付宝昵称
            $data['cash_ali_no'] = input('request.cash_account_no');//支付宝账号
            $data['cash_ali_img']= input('request.cash_ali_img');
        }else if($cash_way == 2){
            $data['cash_wx_no'] = input('request.cash_account_no');//微信账号
            $data['cash_wx_img'] = input('request.cash_wx_img');//微信账号
        }else if($cash_way == 3){
            $data['cash_bank'] = input('request.cash_account'); //提现银行
            $data['cash_bank_no'] = input('request.cash_account_no');//银行卡号
        }
        $result = $this->user->Cash($uid,$data,$accountlog);
        if($result == -1){
            return $this->json('', -1, '超出提现余额');
        }else if($result == 0){
            return $this->json('', 0, '提交失败');
        }
        return $this->json('', 1, '提交成功');
    }

    /**
     *  提现规则内容
     * @param string name 提现申请规则(说明名称)
     * @return json
     */
    public function drawContent()
    {
        $category_name = trim(input('request.name'));
        $result = $this->user->getContentList($category_name);
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }
    /**
     *  提现规则内容
     * @param string name 提现申请规则(说明名称)
     * @return json
     */
    public function helpRead()
    {
        $category_name = trim(input('request.name'));
        $result = $this->user->helpRead($category_name);
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }
    /**
     * 实用户提现展示页面
     */
    public function myInfoCashShow()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $result = $this->user->showCash($uid);
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }

    /**
     * 自提信息获取
     */
    public function pcik(){
        $row  = $this->getPick();
        if($row){
            return $this->json($row, 1,'获取成功！');
        }
        return $this->json('', 0,'获取失败！');
    }

    /**
     * 获取银行名称
     */
    public function cashbank()
    {
        $res  = $this->user->cashbank();
        if($res){
            return $this->json($res, 1, '获取成功');
        }else{
            return  $this->json('', 0, '获取失败');
        }

    }

    /**
     * 提现记录
     * @param int uid
     * @param string token
     * @param int page 当前页数
     */
    public function cashHisroty()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $page = input('request.page',1);
        $size = 20;
        $start = ($page-1)*$size;
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $res = Db::name('cash')->where('cash_uid',$uid)->field('cash_id,cash_no,cash_amount,cash_addtime,cash_way,cash_stat,cash_comm')->order('cash_addtime desc')->limit($start,$size)->select();
        if($res){
            foreach ($res as &$v) {
                $v['cash_addtime'] = date('Y-m-d H:i:s',$v['cash_addtime']);
            }
        }
        return $this->json($res, 1, '获取成功');
    }
}
