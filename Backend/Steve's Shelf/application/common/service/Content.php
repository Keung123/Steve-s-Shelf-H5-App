<?php
namespace app\common\service;

use app\common\model\Content as ContentModel;

class Content extends Base{

	public function __construct(){
		parent::__construct();
		$ContentModel=new ContentModel();
		$this->model=$ContentModel;
	}

	/*
	 * 获取内容
	 */
	public function getContent($type){
		$where = [
			'status' => 'normal',
		];
		switch($type){
			// 用户协议
			case 'usercom' : $where['title'] = '用户协议'; break;
		}

		$info = $this->model->where($where)->field('title,content')->find();
		if(!$info){
			return ['code' => 0, 'msg' => '未找到内容'];
		}
		return ['code' => 1, 'data' => $info];
	}
	
}