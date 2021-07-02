<?php

namespace app\api\controller;

use app\common\service\User as UserService;
use think\Db;
use think\response\Json;
use app\common\service\ActiveGoods;

class Setting extends Common {
    protected $user;
    public function __construct()
    {
        $this->user = new UserService();
    }

    /**
     * 应用设置
     * @param int uid
     * @param string token
     * @return json
     */
    public function appSetting()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $result = $this->user->appSetting($uid);
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }

    /**
     * 应用设置 状态修改
     * @param int uid
     * @param string token
     * @param int id 设置id
     * @param int status  0 开启 , 1 失败
     * @return json
     */
    public function editSetting()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uset_id = input('request.id');
        $status = input('request.status');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $result = $this->user->appSetEdit($uid,$uset_id,$status);
        if($result == 0){
            return $this->json('', 0, '修改失败');
        }
        return $this->json('',1,'修改成功');
    }

    /**
	 * 设置 意见反馈
     * @param int uid
     * @param string token
     * @param int op_type 反馈类型
     * @param string op_content
     * @param string op_contact
     * @return Json
	 */
    public function myFeedback()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $data =[
            'op_uid'  => $uid,
            'op_type'  => input('request.op_type'),//反馈类型
            'op_content' => input('request.op_content'),//反馈内容
            'op_contact'  =>  input('request.op_contact'),//会员联系方式
            'op_add_time'  => time(),//反馈时间
            'img' => input('request.img'),
        ];
        $result = $this->user->Feedback($data);
        if($result == 0){
            return $this->json('', 0, '提交失败');
        }
        return $this->json($result);
    }

    /**
     * 设置 意见反馈类型
     * @param int uid
     * @param string token
     * @return json
     */
    public function myFeedtype()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $result = $this->user->FeedbackType();
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }

    /**
     * 设置 帮助中心
     * @param int uid
     * @param string token
     * @return json
     */
    public function helpCenter()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $result = $this->user->helpCenter();
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }

    /**
     * 帮助中心内容
     * @param int uid
     * @param string token
     * @param int contentId 内容id
     * @return json
     */
    public function helpCenterRead()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $content_id = input('request.contentId');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $result = $this->user->helpRead($content_id);
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }

    /**
     * 设置 关于我们
     * @param int uid
     * @param string token
     * @return json
     */
    public function aboutUs()
    {
        $result = $this->user->aboutUs();
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }

    /**
     * 设置支付密码
     * @param string mobile
     * @param string code 验证码
     * @param string payPWD 支付密码
     * @return json
     */
    public function setPayPwd()
    {
        $code = trim(input('request.code'));
        if(!$code){
            return $this->json('', 0, '验证码不能为空');
        }

        $mobile = input('request.mobile');
        $stat = $this->user->checkCode($mobile, $code, 4);
        //验证
        if(!$stat){
            return $this->json('', 0, '验证码不正确');
        }
        if($stat == -1){
            return $this->json('', 0, '验证码已过期');
        }

        $pwd = input('request.payPWD');
        if(!$pwd){
            return ['code' => 0, 'msg' => '支付密码不能为空'];
        }
        $result = $this->user->setPaypwd($mobile, $pwd);
        return $this->json('', $result['code'], $result['msg']);
    }

    /**
     * 修改支付密码
     * @param string mobile 手机号
     * @param string code 验证码
     * @param string oldPayPWD 旧密码
     * @param string newPauPWD 新密码
     * @return json
    */
    public function resetPayPwd()
    {
        $code = trim(input('request.code'));
        if(!$code){
            return $this->json('', 0, '验证码不能为空');
        }

        $mobile = input('request.mobile');
        $stat = $this->user->checkCode($mobile, $code, 4);
        //验证
        if(!$stat){
            return $this->json('', 0, '验证码不正确');
        }
        if($stat == -1){
            return $this->json('', 0, '验证码已过期');
        }

        $old_pwd = input('request.oldPayPWD');
        $pwd = input('request.newPayPWD');
        if(!$pwd){
            return $this->json('', 0, '支付密码不能为空');
        }
        $result = $this->user->resetPaypwd($mobile, $old_pwd, $pwd);
        return $this->json('', $result['code'], $result['msg']);
    }

    /**
     * 验证支付密码
     * @param int uid
     * @param string token
     * @param string pwd 支付密码
     * @return Json
     */
    public function checkPayPwd()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }

        $pwd = input('request.payPWD');
        $result = $this->user->checkPayPwd($uid, $pwd);
        return $this->json('', $result['code'], $result['msg']);
    }

    /**
     * 是否设置支付密码
     * @param int uid
     * @param string token
     * @return json
     */
    public function isPayPwd()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }

        $info = Db::name('users')->where('user_id', $uid)->field('user_pay_pwd')->find();
        if(!$info['user_pay_pwd']){
            return $this->json('', -1, '未设置支付密码');
        }
        return $this->json('', 1, '已设置支付密码');
    }

    /**
     * 静默更新
     * @param string act app_version
     * @param string version 版本号
     */
    public function updateVersion()
    {
        if($_GET['act']=='app_version'){
            $get_version=$_GET['version'];
            $version = $this->user->getVersion();
            if($get_version<$version){
                $is_new=1;
            }else{
                $is_new=0;
            }
            $url= request()->domain()."/app/hangma.wgt";
            echo json_encode(array('code'=>0,'data'=>$version,'url'=>$url,'is_new'=>$is_new));exit;
        }
    }

    /**
     * 获取自定义活动规则
     * @param integer activeId 活动id
     * @return json
     */
    public function getActiveRuler()
    {
        $active_type_id = input('activeId');
        $activeModel = new ActiveGoods();
        $res = $activeModel->getActiveRuler($active_type_id);
        return $this->json($res);
    }
}
