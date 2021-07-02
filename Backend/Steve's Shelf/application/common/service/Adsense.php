<?php
namespace app\common\service;

use app\common\model\Adsense as AdsenseModel;

class Adsense extends Base{
	public function __construct(){
		parent::__construct();
		$AdsenseModel=new AdsenseModel();
		$this->model=$AdsenseModel;
	}

	/*
	 * 获取广告
	 */
	// public function getAdver($map, $field = '*', $order = '', $limit = '', $join = ){
	public function getAdver($type){
//		$list = $this->model->alias('a')->field($field)->join($join)->where($map)->order($order)->limit($limit)->select();
		switch($type){
			case 'init' : $map = ['b.title' => '启动页']; break;		//启动页
			case 'sideshow' : $map = ['b.title' => '轮播图']; break;	//首页轮播
			case '限时秒杀上方' : $map = ['b.title' => '限时秒杀上方']; break; 	//今日推荐下方banner
			case '精选聚惠' : $map = ['b.title' => '精选聚惠']; break; 	//精选聚惠下方banner
			case '今日特卖' : $map = ['b.title' => '今日特卖']; break; 	//今日特卖下方banner
			case 'today_recomm' : $map = ['b.title' => '今日推荐']; break; 	//今日推荐首页
			case 'brand_recomm' : $map = ['b.title' => '品牌推荐']; break;	//品牌推荐
			case 'bargain' : $map = ['b.title' => '砍价']; break;	//砍价
			case 'limit' : $map = ['b.title' => '秒杀']; break;	//秒杀
			case 'group' : $map = ['b.title' => '拼团']; break;	//拼团
			case 'vip' : $map = ['b.title' => '邀请VIP']; break;	//邀请VIP
			case 'intro' : $map = ['b.title' => '引导页']; break;	//引导页
			case 'gift' : $map = ['b.title' => '开店礼包']; break;	//开店礼包
			case 'open' : $map = ['b.title' => '一键开店页面']; break;	//一键开店
			case 'window' : $map = ['b.title' => '消息弹窗']; break;	//一键开店
			default : return ['code' => 0];
		}
		
		$list = $this->model->where($map)->alias('a')->join('__POSITION__ b', 'a.pid=b.id', 'RIGHT')
            ->field('a.id,a.title,a.image,a.parame,a.weigh,a.type')
            ->order('a.weigh desc')
            ->select();
		return ['data' => $list, 'code' => 200];
	}
}