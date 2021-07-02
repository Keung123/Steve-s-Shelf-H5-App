<?php
namespace app\api\controller;

use app\common\service\User as UserService;
use app\common\service\Yinzi as YinziService;
class Gold extends Common{
    protected $silver;
    public function __construct()
    {
        $this->silver = new YinziService();
    }

    /**
     * 查询元宝
     * @param int uid
     * @param string token
     * @param int status 默认值 :0 ; 0可以使用 , 1失效
     * @return json
     */
   public function myGold()
   {
		$rech_uid = input('request.uid');
		$token = input('request.token');
		$status = input('request.status');//元宝类型 0，可以使用；1，失效
		$uid = $this->getUid($token, $rech_uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$this->silver->timeYinzi($uid);//过期时间 过滤
		$this->silver->rfYinzi($uid);//无人领取退还
		$avai_list = $this->silver->showYinzi($uid);
		$unavai_list = $this->silver->sxYinzis($uid);

		if($status == 1 ){
			$res = $unavai_list;
		}else{
			$res = $avai_list;
		}
		if(!$res){
			return $this->json("", 0, '获取失败');
		}
		foreach($res as &$val){
			$val['yin_add_times'] = date('Y.m.d H:i',$val['yin_add_time']);
			$val['yin_die_time'] = date('Y.m.d H:i',$val['yin_die_time']);
			
		}
		//元宝规则
		$rules = [
			'vip_num' => 5,
			'seller_num' => 100,
			'login_num' => 5,  
		];

		return  json(['data'=>$res,'status'=>1,'msg'=>'获取成功','total'=>count($avai_list), 'other_total' => count($unavai_list), 'rules' => $rules]);;
	}
	/**
	 * 删除失效元宝
	 */
	public function deleteyz()
    {
		$rech_uid = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $rech_uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$res = $this->silver->delYinzi($uid);
		if(!$res){
			return $this->json("", 0, '删除失败');
		}
		return $this->json("", 1, '删除成功');
	}
	/**
	 * 元宝分享成功
	 */
	public function shareyz()
    {
		$rech_uid = input('request.uid');
		$token = input('request.token');
		$yin_id = input('request.yin_id');//元宝id 
		$uid = $this->getUid($token, $rech_uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$res = $this->silver->shaerYinzi($uid,$yin_id);
		if(!$res){
			return $this->json("", 0, '分享失败');
		}
		return $this->json("", 0, '分享成功');;
	}

	/**
	 * 获取我的邀请码
	 */
	public function getMyCode()
    {
		$user_id = input('request.uid');
		$token = input('request.token');
		$uid = $this->getUid($token, $user_id);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
		$info = $this->silver->getMyCode($uid);
		if(!$info){
			return $this->josn('', 0, '获取失败');
		}
		return $this->json($info);
	}
	
	/**
	 * 领取元宝
	 */
   public function getGold()
   {
		$yin_id = input('request.yin_id');
		$share_id = input('request.share_id');
		$now_id = input('request.now_id');
		$type = input('request.type');
		//获取元宝
		if($type == 1){
			$res = $this->silver->getWing($share_id,$now_id,$yin_id);
			if($res == -1){
                return $this->json('', 0, '不能领取自己的分享');
			}else if($res){
                return $this->json('', 1, '领取成功！');
			}
			return $this->json('', 0, '领取失败！');
		}else{
			$row = $this->silver->getWingInfo($yin_id);
			$row['yin_add_time']  = date('Y-m-d H:i',$row['yin_add_time']);
			$row['yin_die_time']  = date('Y-m-d H:i',$row['yin_die_time']);
			if($row){
				return $this->json($row, 1, '获取成功！');
			}
			return$this->json('', 0, '获取失败！');
		}
		    
   }

    /**
     * 我的元宝
     * @param int uid
     * @param string token
     * @param int page
     * @param int type 1使用 2 不可使用
     * @return json
     */
    public function myYz()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $type = input('request.type');
        $p = input('request.page', 1);
        $user = new UserService();
        $list = $user->myYz($uid, $p, $type);
        if(!$list['code']){
            return $this->json('', 0, '获取失败');
        }
        else return $this->json($list['data']);
    }
}
