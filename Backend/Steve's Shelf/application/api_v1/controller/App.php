<?php
namespace app\api\controller;

use app\common\service\Config;
use app\common\service\Adsense as AdsenseService;
use app\common\service\Goods as GoodsService;
use app\common\service\GoodsCategory as GoodsCategoryService;

class App extends Common{

	/*
	* APP首页内容
	*/
	public function index(){
		//获取广告位
		$AdsenseService=new AdsenseService();
		$data['adsense']=$AdsenseService->select(['pid'=>1]);
		//获取特价商品
		$GoodsService=new GoodsService();
		$map['status']='normal';
		$map['recommend']=['like','%1%'];

		$rows=$GoodsService->select($map,'*','weigh desc',12);
		$data['goods1']=$rows;

		//获取热销商品
		$map['recommend']=['like','%2%'];
		$rows=$GoodsService->select($map,'*','weigh desc',12);
		$data['goods2']=$rows;

		//获取推荐商品
		$map['recommend']=['like','%3%'];
		$rows=$GoodsService->select($map,'*','weigh desc',12);
		$data['goods3']=$rows;
		//获取前七个分类
		$GoodsCategoryService=new GoodsCategoryService();
		$rows=$GoodsCategoryService->select([],"*","weigh desc",7);
		$data['category']=$rows;
		return $this->json($data);
		
	}

	/*
	* 获取最新版本号及下载地址
	*/
	public function version(){
		$ConfigService=new Config();
		$config=$ConfigService->find();
		return $this->json($config['app']);
	}
}