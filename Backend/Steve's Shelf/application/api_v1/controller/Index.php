<?php
namespace app\api\controller;
use app\common\service\Coupon;
use app\common\service\FlashGoods;
use app\common\service\ActiveGoods;
use app\common\service\ActiveType;
use app\common\service\Menu;
use app\common\service\User;
use mysql_xdevapi\Result;
use think\Request;
use think\Db;
use app\common\service\User as UserService;
use app\common\service\GoodsCategory as Cate;
use app\common\service\Adsense as Ad;
use app\common\service\Order as Order;
use app\common\service\Goods as GoodsService;
use app\common\service\Config as ConfigService;
use app\common\service\ApiPay as ApiPay;

class Index extends Common{

    /*
     * 启动页
     */
    public function init(){
        $ad = new Ad();
        $img = $ad->getAdver('init');
        return $this->json($img);
    }

    /*
     * 九宫格
     */
    public function menu()
    {
        $menu = new Menu();
        $list = $menu->getList();
        return $this->json($list);
    }

    /*
     * 首页分类
     */
    public function getCate(){
        $cate = new Cate();
        $pid = input('get.pid', 0);
        $where = [
            'pid' => $pid,
            'status' => 'normal',
            // 'is_recom' => 1
        ];
        $list = $cate->getCate($where,'category_id,category_name,image');
        return $this->json($list);
    }

    /*actiArea
     * 全部分类
     */
    public function allCate(){
        $cate = new Cate();
        $list = $cate->allCate();
        return $this->json($list);
    }

    /*
     * 分类商品
     */
    public function cateGoods(){
        $user_id = input('request.uid');
        if($user_id){
            $uid = $this->getUid(input('request.token'), $user_id);
            if(!$uid){
                return $this->json('', 0, '未知参数');
            }
        }
        else{
            $uid = 0;
        }
        $order['order_sv'] = input('request.sv');
        $order['order_new'] = input('request.newest');
        $order['order_price'] = input('request.price');
        $where['goods_brand'] = input('request.brandid');
        $p = input('request.p');
        $p = $p ? $p : 1;
        $cate_id = input('request.cateid');   //全部分类商品传入一级id
        $goods = new GoodsService();
        $list = $goods->getListByCate($cate_id, $uid, $p, $where, $order);
        return $this->json($list);
    }

    /*
     * 分类商品筛选
     */
    public function brandSelect(){
        $cate_id = input('request.cateid');
        $goods = new GoodsService();
        $list = $goods->brandSelect($cate_id);
        return $this->json($list);
    }
    /*
     * 品牌推荐 
     */
    public function brandtj(){
        $goods = new GoodsService();
        $page = input('request.page');
        $list = $goods->brandTui($limit = 10,$page);
        return $this->json($list);
    }
    /*
     * 品牌详情 
     */
    public function brandGoods(){
        $goods = new GoodsService();
        $brandid = input('request.id');
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token,$user_id);
        $GoodsModel=new GoodsService();
        $list = $goods->brandGoods($brandid);
        if(!$list){
            return $this->json('', 0, '获取失败');
        } else {
            foreach ($list['list']  as &$value) {
                $res = $GoodsModel->getstore($uid, $value['goods_id']);
                $goodsService = new goodsService();
                $active_price = $goodsService->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
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
                $value['is_put'] = $res;
                $value['price'] = floatval($value['price']);
            }
        }
        return $this->json($list);
    }
    /*
     * 广告
     */
    public function getAdver(Request $req){
        $ad = new Ad();
        $type = $req->param('type');
        $list = $ad->getAdver($type);
        if($list['code'] == '200'){
            return $this->json($list['data']);
        }
        else return $this->json([], 0, '未知参数');
    }

    /*
     * 未登录或新人
     */
    public function isFresh(){
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

    /*
     * 限时秒杀
     */
    public function limitActi(){
        $goods = new GoodsService();
        $list = $goods->getMiaoshalist();
        if (!empty($list)) {
            return $this->json($list);
        } else {
            return $this->json([], 0, '当前没有秒杀活动');
        }

    }
    /*
     * 签到列表
     */
    public function qianDaoList(Request $req){
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $uid = $this->getUid($token, $user_id);
        if (!$uid) {
            return $this->json([], 0, '未知参数');
        }
        $year = input('request.year');
        $month = input('request.month');
        $usersmodel = new UserService();
        $list = $usersmodel->qiandaolist($uid, $year, $month);
        return $this->json($list, 1);
    }

    /*
     * 签到
     */
    public function qianDao(Request $req){
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $uid = $this->getUid($token, $user_id);
        if (!$uid) {
            return $this->json([], 0, '未知参数');
        }
        $usersmodel = new UserService();
        $res = $usersmodel->is_qiandao($uid);
        if ($res) {
            return $this->json([], -1, '今日已签到');
        }
        $res = $usersmodel->qiandao($uid);
        if ($res) {
            return $this->json([], 1, '签到成功');
        } else {
            return $this->json([], 0, '签到失败');
        }
    }
    /*
     * 签到奖励列表
     */
    public function qdjiangli(Request $req){
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $uid = $this->getUid($token, $user_id);
        if (!$uid) {
            return $this->json([], 0, '未知参数');
        }
        $usersmodel = new UserService();
        $res = $usersmodel->jl_qiaodao($uid);
        $month = date('m',time());
        if(!$res) {
            return $this->json([], 0, '无奖励');
        }
        return json(['data'=>$res,'status'=>1,'msg'=>'获取成功','month'=>$month]);
    }
    /*
     * 券列表
     */
    public function getCouponlist(Request $req){
        $type = strtolower($req->param('type'));
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $user_id = $this->getUid($token, $user_id);
        if (in_array($type,array(1,2,3))) {
            $where['coupon_type'] = $type;
        }
        $where['coupon_aval_time'] = ['gt', time()];
        $couponmodel = new Coupon();
        $list = $couponmodel->getList($where);
        if ($list) {
            foreach ($list as &$val) {
                $val['coupon_aval_time'] = date('Y.m.d', $val['coupon_aval_time']);
                if ($user_id) {
                    $val['is_yongyou'] = $couponmodel->is_yongyou($val['coupon_id'], $user_id);
                } else {
                    $val['is_yongyou'] = 0;
                }
            }
        }
        return $this->json($list);
    }
    /*
     * 领券
     */
    public function getCoupon(Request $req){
        $token = trim(input('token'));
        $user_id = trim(input('uid'));
        $coupon_id = trim(input('coupon_id'));
        $user_id = $this->getUid($token, $user_id);
        if (!$user_id) {
            return $this->json([], 0, '未知参数');
        }
        $couponmodel = new Coupon();
        $res = $couponmodel->getCoupon($user_id, $coupon_id);
        if ($res == 1) {
            return $this->json([], 1, '领取成功');
        } elseif($res == -1) {
            return $this->json([], 0, '没有优惠券信息');
        } elseif($res == -2) {
            return $this->json([], 0, '余额不足');
        }
        else if($res == -5){
            return $this->json('', 0, '已达到每人限领张数');
        }
        else {
            return $this->json([], 0, '领取失败');
        }
    }

    /*
     * 活动专区
     */
    public function actiArea(){
        $activeModel = new ActiveType();
        $order="weigh desc";

        $where = [];
        $where['start_time'] = array('lt', time());
        $where['end_time'] = array('gt', time());
        $where['status'] = 0;
//        $where['active_type'] = ['neq',5];
        $where['active_type'] = ['eq',0];
        $where['id'] = ['neq',5];//去除秒杀
        $limit = 4;//取多少个
        $rows=$activeModel->select($where,'*',$order, $limit);
        if($rows){
            // 隐藏活动
//            return $this->json($rows,1);
            return $this->json([],0);
        } else {
            return $this->json([], 0, '未知参数');
        }
    }

    /**
     * 根据活动id 获取商品
     */
    public function getActiveGoods()
    {
        $active_type_id = input('active_type_id');
        $goods_id = input('goodsid');
        $goods_id = $goods_id ?$goods_id:0;
        $limit = input('limit');
        $p = input('p');
        $time = input('time');
        // if(empty($time))
        // {
        //     $time='00:00,23:00';
        // }
        $activeModel = new ActiveGoods();
        $info = $activeModel->getActiveinfo($active_type_id);

        if ($info['status'] ==1 || !$info) {
            return $this->json([], 0, '该活动不存在或已结束');
        }
        $goodsModel = new GoodsService();
        $activeTypeModel = new ActiveType();
        $type_name = $activeTypeModel->getActive_label($active_type_id);
        $type_banner = $activeTypeModel->getActive_banner($active_type_id);
        $list = $goodsModel->getActiveGoods($active_type_id, $limit, $p, $time,$goods_id);
        $total = $goodsModel->getActiveGoods($active_type_id,'', '', $time,$goods_id);
        $total  = count($total);
        if ($list) {
            foreach ($list as $key=>&$value) {
                $goods  = Db::name('goods')->where('goods_id',$value['goods_id'])->find();
                $goods_info = $goodsModel->getInfoById($value['goods_id'], 'goods_name, picture');
                $value['goods_name'] = $goods_info['goods_name'];
                $value['picture'] = $goods_info['picture'];

                $commission = $this->getCom();
                //开启 返利
                if($commission['shop_ctrl'] == 1){
                    $f_p_rate = $commission['f_s_rate'];
                }else{
                    $f_p_rate = 100;
                }
                $value['profit'] = floor($value['profit'] * $f_p_rate)/100;
                $value['price'] = floatval($value['price']);
                $value['profit'] = floatval($value['profit']);

                $value['vip_price'] = sprintf('%0.2f', $value['vip_price']);
                $value['vip_price'] = floatval($value['vip_price']);
                $value['show_price'] = sprintf('%0.2f', $value['show_price']);
                $value['show_price'] = floatval($value['show_price']);
                $value['active_price'] = sprintf('%0.2f', $value['active_price']);
                $value['active_price'] = floatval($value['active_price']);	$value['goods_price'] = sprintf('%0.2f', $value['goods_price']);
                $value['goods_price'] = floatval($value['goods_price']);
                $value['active_id'] = $active_type_id;

            }
        }

        $data['list'] =$list;

        $ends = 0;
        if ($active_type_id == 5) {
            $date_time_list = $activeModel->getMiaoshatimes();
            if ($date_time_list) {
                foreach ($date_time_list as &$val) {
                    $hour = time();
                    $val['status'] = 0;
                    if ($ends == 1) {
                        $val['status'] = 2;
                    }
                    if ($val['start_time'] <= $hour && $val['end_time'] > $hour) {

                        $val['status'] = 1;
                        $ends = 1;
                    } else if($val['start_time'] > $hour) {
                        $val['status'] = 2;
                        $ends = 1;
                    }
                    $val['start_time'] = date('H:00', $val['start_time']);
                    $val['end_time'] = date('H:00', $val['end_time']);
                }
            }
            $data['miaosha_info'] = $date_time_list;
        }

        return json(['status'=>1,'msg'=>'获取成功','data'=>$data,'active_type_name'=>$type_name,'active_banner'=>$type_banner,'total'=>$total]);
    }
    /*
     * 猜你喜欢
     */
    public function mayLike(){
        $user_id = input('request.uid');
        if($user_id){
            $token = input('request.token');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }

        $p = input('request.p');
        $user = new UserService();
        $data = $user->mayLike($user_id, $p);
        if(!$data['list']){
            return $this->json('', 0, '获取失败');
        } else {
            foreach ($data['list'] as &$value) {
                $goodsService = new goodsService();
                $active_price = $goodsService->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
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
        $data['list'] = $user->ActiveInfo($data['list']);

        return $this->json(['list' => $data['list'], 'total' => $data['total']]);
    }
    /*
     * 猜你喜欢(首页)
     */
    public function mayLikes(){
        $p = input('request.p');
        $user_id = input('request.uid');
        if($user_id){
            $token = input('request.token');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }
        $user = new UserService();
        $data = $user->mayLikes($user_id, $p);
        if(!$data['list']){
            return $this->json('', 0, '获取失败');
        } else {
            foreach ($data['list'] as &$value) {
                $goodsService = new goodsService();
                $active_price = $goodsService->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
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
                /*    $value['dianzhu_price'] = floor($value['price'] * $value['commission'])/ 100; */
                $value['price'] = floatval($value['price']);
            }
        }
        $data['list'] = $user->ActiveInfo($data['list']);

        return $this->json(['list' => $data['list'], 'total' => $data['total']]);
    }
    /*
     * 猜你喜欢(首页)
     */
    public function goodsLike(){
        $p = input('request.p');
        $user = new UserService();
        $data = $user->goodsLikes($user_id, $p);
        if(!$data['list']){
            return $this->json('', 0, '获取失败');
        } else {
            foreach ($data['list'] as &$value) {
                $goodsService = new goodsService();
                $active_price = $goodsService->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
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
        $data['list'] = $user->ActiveInfo($data['list']);

        return $this->json(['list' => $data['list'], 'total' => $data['total']]);
    }
    /*
     * 发现
     */
    public function mateZone(){
        $user_id = input('request.uid', 0);
        if($user_id){
            $token = input('request.token');
            $cat_id = input('request.cat_id');
            $type = input('request.type');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }
        $p = input('request.p');
        $p = $p ? $p : 1;
        $user = new UserService();
        $mate_info = $user->userMaterial($user_id, $p,$cat_id,$type);
        return $this->json($mate_info);
    }

    /*
     * 搜索界面
     */
    public function search(){
        $user_id = input('request.uid');
        if($user_id){
            $token = input('request.token');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }
        $user = new UserService();
        $info = $user->getSearch($user_id);
        return $this->json($info);
    }

    /*
     * 商品搜索
     */
    public function goodsSearch(){
        $user_id = input('request.uid');
        $p=input('request.p');
        if($user_id){
            $token = input('request.token');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }
        $key = input('request.key');
        $goods = new GoodsService;
        $list = $goods->goodsSearch($user_id, $key,$p);
        foreach ($list['list'] as &$value) {
            $value['dianzhu_price'] = floor($value['price'] * $value['commission'])/ 100;
            if($value['goods_name'] == '开店大礼包'){
                unset($value);
            }
            if($value['prom_type'] == 5 && empty($value['commission'])){
                $value['dianzhu_price'] = 0.01;
            }
            $value['dianzhu_price'] = sprintf('%0.2f', $value['dianzhu_price']);
            $value['dianzhu_price'] = floatval($value['dianzhu_price']);
            $value['vip_price'] = sprintf('%0.2f', $value['vip_price']);
            $value['vip_price	'] = floatval($value['vip_price']);
            $value['show_price'] = sprintf('%0.2f', $value['show_price']);
            $value['show_price'] = floatval($value['show_price']);
            $value['price'] = sprintf('%0.2f', $value['price']);
            $value['price'] = floatval($value['price']);
        }
        return $this->json($list);
    }

    /*
     * 搜索历史删除
     */
    public function searchDel(){
        $user_id = input('request.uid');
        if($user_id){
            $token = input('request.token');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }
        $user = new UserService();
        $key_id = input('request.key_id');
        // if(!$key_id){
        //     return $this->json('', 0, '未知参数');
        // }
        $result = $user->searchDel($user_id, $key_id);
        if(!$result){
            return $this->json('', 0, '删除失败');
        }
        return $this->json('', 1, '删除成功');
    }

    /*
     * 今日推荐商品列表
     * */
    public function todayRecomment(){
        if(Request()->isPost()){
//            $this->checkEmploy(Request()->post(),['page']);
            $goods = new GoodsService();
            $page=Request()->post("page/d")?Request()->post("page/d"):1;
            $user_id = input('request.uid');
            $token = input('request.token');
            $uid = $this->getUid($token,$user_id);
            $goodsList=$goods->todayRecomment($page,$uid);
            if(empty($goodsList)) return json(['status'=>0,'msg'=>'暂无数据','data'=>[]]);
            $number=model("goods")->where(['is_recom_today'=>1,'prom_type'=>0,'status'=>0])->count("goods_id");
            $totalPage=ceil($number/10);
            return json(['status'=>1,'msg'=>'数据信息','data'=>['goods_list'=>$goodsList,'totalPage'=>$totalPage]]);
        }
        return json(['status'=>-1,'msg'=>'非法操作！','data'=>[]]);
    }
    /**
     * 静默更新
     */

    public function updateVersion()
    {
        if($_GET['act']=='app_version'){
            $get_version=$_GET['ver'];
            $userModel = new UserService();
            $version = $userModel->getVersion();
            if($get_version<$version){
                $is_new=1;
            }else{
                $is_new=0;
            }
            $url= request()->domain() . '/app/app.wgt';
            echo json_encode(array('code'=>0,'data'=>$version,'url'=>$url,'is_new'=>$is_new));exit;
        }
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
                        $url= request()->domain() . '/app/app.wgt';
                        break;
                    case 'apk':
                        $up = $update['apk'];
                        $url= request()->domain() . '/app/app.apk';
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
     *  签到规则
     */
    public function  guizeshow()
    {
        $guize_name = input('request.name');
        $ConfigService = new ConfigService();
        $res = $ConfigService->getguiZe($guize_name);
        if(!$res){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($res);
    }

    /*
     * 优惠券购买接口
     */
    public function buyCoupon(){
        $uid = $this->getUid(input('request.token'), input('request.uid'));
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }

        $coupon_id = input('request.coupon_id');
        //优惠券模板信息
        $coupon_t_info = Db::name('coupon')->where('coupon_id', $coupon_id)->field('coupon_title,coupon_thumb,coupon_price,coupon_buy_price,coupon_use_limit,coupon_get_limit,coupon_type,coupon_type_id,coupon_total,coupon_s_time,coupon_aval_time,coupon_stat')->find();
        if(!$coupon_t_info || $coupon_t_info['coupon_stat']){
            return $this->json('', 0, '优惠券不存在');
        }
        if($coupon_t_info['coupon_total'] == 0){
            return $this->json('', 0, '优惠券剩余不足');
        }
        $my_coupon = Db::name('coupon_users')->where(['coupon_id' => $coupon_id, 'c_uid' => $uid, 'coupon_stat' => ['neq', 5]])->count();
        if($coupon_t_info['coupon_get_limit'] && $my_coupon >= $coupon_t_info['coupon_get_limit']){
            return $this->json('', 0, '此优惠券每人限领'.$coupon_t_info['coupon_get_limit'].'张');
        }
        $c_no = $this->createCouponNo();
        try{
            $coupon_user_data = [
                'coupon_id' => $coupon_id,
                'c_uid' => $uid,
                'coupon_stat' => 5,
                'c_coupon_title' => $coupon_t_info['coupon_title'],
                'c_coupon_type' => $coupon_t_info['coupon_type'],
                'c_coupon_price' => $coupon_t_info['coupon_price'],
                'c_coupon_buy_price' => $coupon_t_info['coupon_use_limit'],
                'coupon_type_id' => $coupon_t_info['coupon_type_id'],
                'coupon_aval_time' => $coupon_t_info['coupon_aval_time'],
                'c_no' => $c_no,
            ];
            Db::name('coupon_users')->insert($coupon_user_data);
            Db::name('coupon')->where('coupon_id', $coupon_id)->setDec('coupon_total', 1);
            Db::commit();
        }
        catch(\Exception $e){
            Db::rollback();
            return $this->json('', 0, '购买失败');
        }
        $apipay = new ApiPay();
        $pay_code = input('request.paycode');
        if($pay_code == 'balance'){
            $user_info = Db::name('users')->where('user_id', $uid)->field('user_account')->find();
            if($user_info['user_account'] < $coupon_t_info['coupon_buy_price']){
                return $this->json('', 0, '账户余额不足');
            }
            $user_service = new UserService();
            $result = $user_service->changeAccount($uid, 6, -$coupon_t_info['coupon_buy_price']);
            if($result){
                $coupon_info = Db::name('coupon_users')->where(['c_no' => $c_no, 'coupon_stat' => 5])->field('c_id')->find();
                if($coupon_info){
                    Db::name('coupon_users')->where('c_id', $coupon_info['c_id'])->update(['add_time' => time(), 'coupon_stat' => 1]);
                }
                return $this->json('', 1, '余额支付成功');
            }
            else{
                return $this->json('', 0, '余额支付失败');
            }
        }
        else{
            switch($pay_code){
                //支付宝支付
                case 'alipay' :
                    $data = $apipay->Alipay($c_no, $coupon_t_info['coupon_buy_price'], '购买 '.$coupon_t_info['coupon_title']);
                    break;
                //微信支付
                case 'wxpay' :
                    $coupon_t_info['coupon_buy_price'] *= 100;
                    $data = $apipay->WxPay($c_no, $coupon_t_info['coupon_buy_price'], '合陶家-'.$coupon_t_info['coupon_title']);
                    break;
                //银联支付
                case 'unionpay' :
                    $coupon_t_info['coupon_buy_price'] *= 100;
                    $data = $apipay->UnionPay($c_no, $coupon_t_info['coupon_buy_price']);
                    break;
            }

            if(!$data['code']){
                return $this->json('', 0, $data['msg']);
            }
            return $this->json($data['data']);
        }
    }

    /*
     * 生成优惠券编号
     */
    public function createCouponNo(){
        $no = 'YH'.date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
        $check = Db::name('coupon_users')->where('c_no', $no)->field('c_id')->find();
        while($check){
            $no = $this->createCouponNo();
        }
        return $no;
    }
    /*
     * 获取大礼包 商品
     */
    public function getLibao()
    {
        $goods_modle = new GoodsService();

        $list = $goods_modle->getLibao();
        return $this->json($list);
    }
    /*
     * 获取大礼包 商品详情
     */
    public function getLibaoInfo()
    {
        $goods_id = input('goods_id/d');
        if (empty($goods_id)) {
            return $this->json([], 0, '参数错误');
        }
        $goods_modle = new GoodsService();

        $list = $goods_modle->getLibaoInfo($goods_id);
        return $this->json($list);
    }
    /*
     * 获取自定义活动规则
     */
    public function getActiveRuler()
    {
        $active_type_id = input('active_type_id');
        $activeModel = new ActiveGoods();
        $res = $activeModel->getActiveRuler($active_type_id);
        return $this->json($res);
    }

    /*
     * 获取 当天 用户是否 弹框
     */
    public function getAlert(){
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

    /*
     * 获取 当天 用户是否 签到提醒
     */
    public function singring(){
        $uid = input('uid');
        $app_system = input('app_system');
        $client_id = input('client_id');
        if ($uid) {
            $sing_time = Db::name('users')->where(['user_id' => $uid])->value('sing_time');
            $time = strtotime(date('Y-m-d', time()));
            if ($sing_time < $time) {
                Db::name('users')->where(['user_id' => $uid])->update(['sing_time' => $time]);
                $usersmodel = new UserService();
                $res = $usersmodel->singRemind($uid,$client_id,$app_system);
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
    /*
     * ios 审核使用
     */
    public function iosShenhe()
    {
        $shopinfo = Db::name('config')->value('shop');
        $shoparr = json_decode($shopinfo, true);
        $usersmodel = new UserService();
        $info = $usersmodel->userInfo(['user_id' => 89], 'user_id as uid, token,app_system,is_seller');
        $info['status'] = $shoparr['iosshenhe'];
        return $info;
    }
    /*
    * 签到提醒
    */
    public function singRemind (){
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
}