<?php

namespace app\api\controller;

use app\common\service\User as UserService;
use app\common\service\Goods;
use think\Request;

class Searchbox extends Common{
    protected $user;
    protected $goods;
    public function __construct()
    {
        $this->user = new UserService();
        $this->goods = new Goods();
    }

    /**
     * 搜索界面
     * @param int uid
     * @param string token
     * @return json
     */
    public function search()
    {
        $user_id = input('request.uid');
        if($user_id){
            $token = input('request.token');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }
        $info = $this->user->getSearch($user_id);
        return $this->json($info);
    }

    /**
     * 商品搜索
     * @param int uid
     * @param string token
     * @param string key 关键词,多个以空格分隔
     * @return json
     */
    public function goodsSearch()
    {
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
        $list = $this->goods->goodsSearch($user_id, $key,$p);
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

    /**
     * 搜索历史删除
     * @param int uid
     * @param string token
     * @param int historyId 记录id,清空是传0
     * @return json
     */
    public function searchDel()
    {
        $user_id = input('request.uid');
        if($user_id){
            $token = input('request.token');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }
        $key_id = input('request.historyId');
        $result = $this->user->searchDel($user_id, $key_id);
        if(!$result){
            return $this->json('', 0, '删除失败');
        }
        return $this->json('', 1, '删除成功');
    }
}