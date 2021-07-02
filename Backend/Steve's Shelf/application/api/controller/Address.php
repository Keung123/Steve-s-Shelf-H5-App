<?php

namespace app\api\controller;

use app\common\service\User as UserService;
use think\Request;
use think\Db;

class Address extends Common{
    protected $user;
    public function __construct()
    {
        $this->user = new UserService();
    }

    /**
	 * description:我的地址
     * @param int uid
     * @param string token
     * @return json
	 */
    public function myAddr()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $addr_info = $this->user->userAddr($uid);
        return $this->json($addr_info);
    }

    /**
     * description:地址详情
     * @param int uid
     * @param string token
     * @param int addrId
     * @return json
     */
    public function addrInfo()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $addr_id = input('request.addrId');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $addr_info = $this->user->addrInfo($uid, $addr_id);
        return $this->json($addr_info);
    }

    /**
	 * description:地址新增或编辑
     * @return json
	 */
    public function addrEdit(){
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
      
      	$province = input('request.provincet');
      	$city = input('request.cityt');
      	$district = input('request.district');
      	$cont = input('request.cont');
      	
      	$area = Db::name('city')->where('id',$district)->find();
        if(!$area){
            return $this->json('', 0, '保存失败');
        }


        //TODO 2018-11-15 增加百度地图获取位置坐标 方便计算配送距离
        $baidu_geocoder = "http://api.map.baidu.com/geocoder/v2/?address=".$area['province'].$area['city'].$area['area'].$cont."&output=json&ak=fcQvGYSZ2k2fkwgqFrBH1Pa8CTK5eGQo&callback=showLocation"; //GET请求
        
        
        $baidu_json = file_get_contents($baidu_geocoder);
        $baidu_json_arr = explode("({", $baidu_json);
        $baidu_json_arr = explode("})", $baidu_json_arr[1]);
        $baidu_json_str = "{".$baidu_json_arr[0]."}";
        $baidu_json = json_decode($baidu_json_str,true);
        $lat       = $baidu_json['result']['location']['lat'];
        $lng       = $baidu_json['result']['location']['lng'];
        if(empty($lat) || empty($lng)){
            $lng = '119.834742';
            $lat = '30.250623';
        }
      
        $data = [
            'addr_province' => input('request.province'),
            'addr_city' => input('request.city'),
            'addr_area' => input('request.district'),
            'addr_cont' => input('request.cont'),
            'post_no' => input('request.postno'),
            'is_default' => input('request.is_default'),
            'addr_receiver' => input('request.receiver'),
            'addr_phone' => input('request.phone'),
            'addr_id' => input('request.addrid') ? input('request.addrid') : 0,
            'addr_add_time' => time(),
          	'lng' => $lng,
          	'lat' => $lat,
          	'city' => $area['city'],
        ];
        if(!$uid || !$data){
            return $this->json('', 0, '未知参数');
        }
        $res = $this->user->addrEdit($uid, $data);
        return $this->json($res);
        if($res){
            return $this->json('', 1, '保存成功');
        }
        else return $this->json('', 0, '保存失败');
    }

    /**
     * description:地址删除
     * @param int uid
     * @param string token
     * @param int addrId
     * @return json
     */
    public function addrDel(){
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $addr_id = input('request.addrId');
        if(!$uid || !$addr_id){
            return $this->json('', 0, '未知参数');
        }
        $res = $this->user->addrDel($uid, $addr_id);
        if($res){
            return $this->json('', 1, '删除成功');
        }
        else return $this->json('', 0, '删除失败');
    }

    /**
     * description:设置默认地址
     * @param int uid
     * @param string token
     * @param int addrId
     * @return json
     */
    public function addrDefault()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $addr_id = input('request.addrId');
        if(!$uid || !$addr_id){
            return $this->json('', 0, '未知参数');
        }
        $res = $this->user->addrDefault($uid, $addr_id);
        if($res){
            return $this->json('', 1, '设置成功');
        }
        else return $this->json('', 0, '设置失败');
    }
}