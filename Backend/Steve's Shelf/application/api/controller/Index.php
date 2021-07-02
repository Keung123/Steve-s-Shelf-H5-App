<?php
namespace app\api\controller;

use app\common\service\Menu;
use scan\AipImageClassify;
use think\Request;
use think\Db;
use app\common\service\User as UserService;
use app\common\service\GoodsCategory as Cate;
use app\common\service\Adsense as Ad;
use app\common\service\Order as Order;
use app\common\service\Goods as GoodsService;
use QRcode;

class Index extends Common {
    protected $user;
    protected $goods;
    public function __construct()
    {
        $this->user = new UserService();
        $this->goods = new GoodsService();
    }

    public function scanImage(){

        //      $img =\request()->domain()."/uploads/20200711/16444867678.jpg";
     $img =\request()->domain().'/'.input('imgurl');

        $APP_ID = '21231515';

        $API_KEY = 'MiLEmpy5nyGZYqKzV8DIniWc';

        $SECRET_KEY = 'vuMfDbS6li1xBq87PPnNwPpD5w7f0F1j';

        $client =  new AipImageClassify($APP_ID,$API_KEY,$SECRET_KEY);

        $image = file_get_contents($img);

// 调用通用物体识别
        $client->advancedGeneral($image);

// 如果有可选参数

        $options = array();
        $options["baike_num"] = 5;

// 带参数调用通用物体识别
        $infos=$client->advancedGeneral($image, $options);


        $info="";
       if($infos['result_num']>0){

           foreach ($infos['result'] as $kk=>$vv){

               if($kk<3){

                   $info.= $vv['keyword']."|";
               }

           }

           $info =  trim($info,'|');

           $data['info'] = $infos['result'][0]['keyword'];

           $data['status'] = 1;

       }else{

           $data['info'] = "";

           $data['status'] = 0;

           $data['msg'] ="识别失败,请重新重新拍张识别！";

       }
        return json($data);

    }

    /**
     * 启动页
     */
    public function init(){
        $ad = new Ad();
        $img = $ad->getAdver('init');
        return $this->json($img);
    }

    /**
     * 九宫格
     */
    public function menu()
    {
        $menu = new Menu();
        $list = $menu->getList();
        return $this->json($list);
    }

    /**
     * 首页分类
     */
    public function getCate(){
        $cate = new Cate();
        $pid = input('get.pid', 0);
        $where = [
            'pid' => $pid,
            'status' => 'normal',
        ];
        $list = $cate->getCate($where,'category_id,category_name,image');
        return $this->json($list);
    }

    /**
     * 广告
     */
    public function getAdver(Request $req)
    {
        $ad = new Ad();
        $type = $req->param('type');
        $list = $ad->getAdver($type);
        if($list['code'] == '200'){
            return $this->json($list['data']);
        }
        else return $this->json([], 0, '未知参数');
    }

    /**
     * 未登录或新人
     */
    public function isFresh()
    {
        $isFresh = 0;
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $uid = $this->getUid($token, $user_id);
        if (!$uid) {
            return $this->json([], 0, '未知参数');
        }
        $order = new Order();
        $map = ['order_uid' => $uid, 'pay_status' => ['not in', '0,2']];
        $list = $order->getOrderList($map);
        $isFresh = $list ? 0 : 1;
        return $this->json(['isFresh' => $isFresh]);
    }

    /**
     * 限时秒杀
     */
    public function limitActi()
    {
        $goods = new GoodsService();
        $list = $goods->getMiaoshalist();
        if (!empty($list)) {
            return $this->json($list);
        } else {
            return $this->json([], 0, '当前没有秒杀活动');
        }

    }

    /**
     * 猜你喜欢 -- 根据足迹--常用清单为您推荐
     */
    public function mayLike()
    {
        $user_id = input('request.uid');
        if($user_id){
            $token = input('request.token');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }

        $p = input('request.p');
        $data = $this->user->mayLike($user_id, $p);
        if(!$data['list']){
            return $this->json('', 0, '获取失败');
        } else {
            foreach ($data['list'] as &$value) {
                $active_price = $this->goods->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
                $commission = $this->getCom();
                //开启 返利
                if($commission['shop_ctrl'] == 1){
                    $f_p_rate = $commission['f_s_rate'];
                }else{
                    $f_p_rate = 100;
                }
                $value['dianzhu_price'] = floor($active_price * $value['commission']/ 100 * $f_p_rate)/100;
                $value['price'] = floatval($value['price']);
                $value['dianzhu_price'] = floatval($value['dianzhu_price']);
                $value['price'] = sprintf('%0.2f', $value['price']);
                $value['price'] = floatval($value['price']);
                $value['show_price'] = sprintf('%0.2f', $value['show_price']);
                $value['show_price'] = floatval($value['show_price']);
                $value['vip_price'] = sprintf('%0.2f', $value['vip_price']);
                $value['vip_price'] = floatval($value['vip_price']);
            }
        }
        $data['list'] = $this->user->ActiveInfo($data['list']);
        return $this->json(['list' => $data['list'], 'total' => $data['total']]);
    }

    /**
     * 猜你喜欢(首页)--根据权重，有banner图
     */
    public function mayLikes()
    {
        $p = input('request.p');
        $user_id = input('request.uid');
        if($user_id){
            $token = input('request.token');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }
        $data = $this->user->mayLikes($user_id, $p);
        if(!$data['list']){
            return $this->json('', 0, '获取失败');
        } else {
            foreach ($data['list'] as &$value) {
                $active_price = $this->goods->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
                $commission = $this->getCom();
                //开启 返利
                if($commission['shop_ctrl'] == 1){
                    $f_p_rate = $commission['f_s_rate'];
                }else{
                    $f_p_rate = 100;
                }
                $value['dianzhu_price'] = sprintf('%0.2f',($active_price * $value['commission']/ 100 * $f_p_rate))/100;
                $value['price'] = floatval($value['price']);
                $value['dianzhu_price'] = floatval($value['dianzhu_price']);

                $value['price'] = sprintf('%0.2f', $value['price']);
                $value['price'] = floatval($value['price']);
                $value['show_price'] = sprintf('%0.2f', $value['show_price']);
                $value['show_price'] = floatval($value['show_price']);
                $value['vip_price'] = sprintf('%0.2f', $value['vip_price']);
                $value['vip_price'] = floatval($value['vip_price']);
                $value['price'] = floatval($value['price']);
            }
        }
        $data['list'] = $this->user->ActiveInfo($data['list']);
        return $this->json(['list' => $data['list'], 'total' => $data['total']]);
    }

    /**
     * 猜你喜欢(首页)--根据权重，无banner图
     */
    public function goodsLike(){
        $p = input('request.p');
        $user_id = input('request.uid');
        $data = $this->user->goodsLikes($user_id, $p);
        if(!$data['list']){
            return $this->json('', 0, '获取失败');
        } else {
            foreach ($data['list'] as &$value) {
                $active_price = $this->goods->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
                $commission = $this->getCom();
                //开启 返利
                if($commission['shop_ctrl'] == 1){
                    $f_p_rate = $commission['f_s_rate'];
                }else{
                    $f_p_rate = 100;
                }
                $value['dianzhu_price'] =sprintf('%0.2f',($active_price * $value['commission']/ 100 * $f_p_rate))/100;
                $value['price'] = floatval($value['price']);
                $value['dianzhu_price'] = floatval($value['dianzhu_price']);
                $value['price'] = sprintf('%0.2f', $value['price']);
                $value['price'] = floatval($value['price']);
                $value['show_price'] = sprintf('%0.2f', $value['show_price']);
                $value['show_price'] = floatval($value['show_price']);
                $value['vip_price'] = sprintf('%0.2f', $value['vip_price']);
                $value['vip_price'] = floatval($value['vip_price']);
            }
        }
        $data['list'] = $this->user->ActiveInfo($data['list']);
        return $this->json(['list' => $data['list'], 'total' => $data['total']]);
    }

    /**
     * 今日推荐商品列表
     * */
    public function todayRecomment()
    {
        if(Request()->isPost()){
            $page=Request()->post("page/d")?Request()->post("page/d"):1;
            $user_id = input('request.uid');
            $token = input('request.token');
            $uid = $this->getUid($token,$user_id);
            $goodsList=$this->goods->todayRecomment($page,$uid);
            if(empty($goodsList)) return json(['status'=>0,'msg'=>'暂无数据','data'=>[]]);
            $number=model("goods")->where(['is_recom_today'=>1,'prom_type'=>0,'status'=>0])->count("goods_id");
            $totalPage=ceil($number/10);
            return json(['status'=>1,'msg'=>'数据信息','data'=>['goods_list'=>$goodsList,'totalPage'=>$totalPage]]);
        }
        return json(['status'=>-1,'msg'=>'非法操作！','data'=>[]]);
    }

    /**
     * 升级wgt
     * @return [type] [description]
     */
    public function updateApp()
    {
        if($_GET['act']=='app_update'){
            $get_version=$_GET['ver'];
            $get_type = $_GET['type'];
            $is_new = 0;
            $version = '';
            $url="";
            $update = Db::name('config')->value('update');

            if(!empty($update)){
                $update = json_decode($update,true);
                switch($get_type){
                    case 'wgt':
                        $up = $update['wgt'];
                        $url= request()->domain()."/app/hetao.wgt";
                        break;
                    case 'apk':
                        $up = $update['apk'];
                        $url= request()->domain()."/app/hetao.apk";
                        break;
                    default:
                        $up = 0;
                        break;
                }
                if($get_version<$up){
                    $is_new=1;
                    $version = $up;
                }
            }
            echo json_encode(array('code'=>0,'data'=>$version,'url'=>$url,'is_new'=>$is_new));exit;
        }
    }

    /**
     * 生成优惠券编号
     */
    public function createCouponNo()
    {
        $no = 'YH'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        $check = Db::name('coupon_users')->where('c_no', $no)->field('c_id')->find();
        while($check){
            $no = $this->createCouponNo();
        }
        return $no;
    }

    /**
     * 获取 当天 用户是否 弹框
     */
    public function getAlert()
    {
        $uid = input('uid');
        if ($uid) {
            $alert_time = Db::name('users')->where(['user_id' => $uid])->value('alert_time');
            $time = strtotime(date('Y-m-d', time()));
            if ($alert_time < $time) {
                Db::name('users')->where(['user_id' => $uid])->update(['alert_time' => $time]);
                return $this->json();
            }
        }
        return $this->json([], 0);
    }

    /**
     * 获取 当天 用户是否 签到提醒
     */
    public function singring()
    {
        $uid = input('uid');
        $app_system = input('app_system');
        $client_id = input('client_id');
        if ($uid) {
            $sing_time = Db::name('users')->where(['user_id' => $uid])->value('sing_time');
            $time = strtotime(date('Y-m-d', time()));
            if ($sing_time < $time) {
                Db::name('users')->where(['user_id' => $uid])->update(['sing_time' => $time]);
                $res = $this->user->singRemind($uid,$client_id,$app_system);
                $data = [
                    'sing_time'=>$sing_time,
                    'uid'=>$uid,
                    'time'=>$time,
                    'res'=>$res,
                ];
                return $this->json($data);
            }
        }
        return $this->json([], 0);
    }

    /**
     * ios 审核使用
     */
    public function iosShenhe()
    {
        $shopinfo = Db::name('config')->value('shop');
        $shoparr = json_decode($shopinfo, true);
        $info = $this->user->userInfo(['user_id' => 89], 'user_id as uid, token,app_system,is_seller');
        $info['status'] = $shoparr['iosshenhe'];
        return $info;
    }

    /**
     * 签到提醒
     */
    public function singRemind ()
    {
        $uid = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $uid);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $sing_remind = Db::name('users')->where('user_id',$uid)->value('sing_remind');
        $sing_remind = $sing_remind == 0?1:0;
        $data=[
            'sing_remind'=>$sing_remind
        ];
        $res = Db::name('users')->where('user_id',$uid)->update($data);
        if($res!=false){
            return $this->json('', 1, '修改成功！');
        }
        return $this->json('', 0, '修改失败！');
    }
    /* name:推广APP二维码
     * purpose: 推广APP二维码
     * return:  推广APP二维码
     * write_time:2019/03/11 13:57
     */
    public function share_link() {
        $uid = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $uid);
        if(!$uid){
            $ret['code'] = 0;
            $ret['msg'] = '参数错误。不能生成二维码';
            return json($ret);
        }
        // 生成二维码
        $name = md5('erweima'.$uid);
        $filename = 'uploads/qr/'.substr($name,0,2).'/'.$name.'.png';

        if(!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }
        $yaoqingma = Db::name('users')->where('user_id',$uid)->value('s_invite_code');
        $value = "http://".$_SERVER['HTTP_HOST']."/api/fx/register?yaoqingma=".$yaoqingma ;
        
        $temp = 'uploads/erm.png';
        $errorCorrectionLevel = 'H';
        $matrixPointSize = 10;
        QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 2);

        $logo =$temp; //准备好的logo图片
        $QR = $filename;      //已经生成的原始二维码图
        if (file_exists($logo)) {
            $QR = imagecreatefromstring(file_get_contents($QR));    //目标图象连接资源。
            $logo = imagecreatefromstring(file_get_contents($logo));  //源图象连接资源。
            if (imageistruecolor($logo)) imagetruecolortopalette($logo, false, 65535);//解决logo失真问题
            $QR_width = imagesx($QR);      //二维码图片宽度
            $QR_height = imagesy($QR);     //二维码图片高度
            $logo_width = imagesx($logo);    //logo图片宽度
            $logo_height = imagesy($logo);   //logo图片高度
            $logo_qr_width = $QR_width / 4;   //组合之后logo的宽度(占二维码的1/5)
            $scale = $logo_width/$logo_qr_width;  //logo的宽度缩放比(本身宽度/组合后的宽度)
            $logo_qr_height = $logo_height/$scale; //组合之后logo的高度
            $from_width = ($QR_width - $logo_qr_width) / 2;  //组合之后logo左上角所在坐标点
            //重新组合图片并调整大小
            /*
             * imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
             */
            $res = imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,$logo_qr_height, $logo_width, $logo_height);
        }
        imagepng($QR, $filename);
        imagedestroy($QR);

        if (file_exists($filename)) {
         //   @unlink($temp);
            $ret['code'] = 1;
            $ret['msg'] = '分享二维码已生成';
            $ret['data'] = '/'.$filename;
            return json($ret);
        } else {
            @unlink($temp);
            $ret['code'] = 0;
            $ret['msg'] = '分享二维码生成错误，请稍后再试';
            return json($ret);
        }
    }

    /**
     *  消息  活动消息
     */
    public function activeNews(){
        $uuserService = new UserService();
        $result = $uuserService->getCenter('活动消息');
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }
}