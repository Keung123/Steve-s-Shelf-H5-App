<?php
namespace app\api\controller;

use app\common\service\Topic as TopicService;
use app\common\service\User as User;
use app\common\service\Order as OrderSerevice;
use think\Db;
class Topic extends Common{

	/*
	* 话题列表
	*/
	public function index(){
		$TopicModel=new TopicService();
		$p = input('request.p', 1);
        $list = $TopicModel->topicList($p);
		if(!$list){
    		return $this->json('', 0, '获取失败');
    	}
    	return $this->json($list);
	}
	
	/*
	* 话题参与详情
	*/
	public function topicInfo(){
		$uid = input('request.uid');
		$token = input('request.token');
		$tp_id = input('request.tp_id');
		$p = input('request.p', 1);
		$type = input('request.type');
		$hottest = input('request.hottest');
		/* $uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		} */
		$TopicModel=new TopicService();
        $list = $TopicModel->topicInfo($uid,$tp_id,$p,$type,$hottest);
		if(!$list){
    		return $this->json('', 0, '获取失败');
    	}
    	return $this->json($list);
		
	}
 
}