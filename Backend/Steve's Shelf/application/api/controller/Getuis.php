<?php
namespace app\api\controller;

use app\common\service\Push as PushService;

use getui\Pushs;

class Getuis extends Common{
    protected $push;
    public function __construct()
    {
        $this->push = new PushService();
    }
    /**
     * 群推接口案例
     */
    public function pushMessageToApp($mes,$listId)
    {
		$res = $this->push->pushMessageToApp($mes,$listId);
		print_r($res);
	}
	  //群推接口案例
    public function IGtTransmissionTemplateDemos($mes,$listId)
    {
		$res = $this->push->IGtTransmissionTemplateDemos($mes,$listId);
		print_r($res);
	}  
	//单接口推送
    public function pushMessageToSingle($msg,$data)
    {
		$datas =[
			'client_id' => 'c54421c1acde3a5ac0c3a3d6b4610e26'
			];
		$msgs = [
			'content'=>'欢迎你',
			'title' =>'合陶家欢迎你',
		];
		$res = $this->push->pushMessageToSingle($msgs,$datas);
		print_r($res);
	}
	//多接口推送  
    public function pushMessageToList($client_id)
    {
        $datas =[
			 0=>['client_id'=>'c54421c1acde3a5ac0c3a3d6b4610e26'],
			];
		$msgs = [
			'content'=>'欢迎你！',
			'title' =>'合陶家欢迎你！',
		];
		$res = $this->push->pushMessageToList($msgs,$datas);
		print_r($res);
	}
	//自动判断
    public function getTypes()
    {
        $datas =[
			 0=>['client_id'=>'c54421c1acde3a5ac0c3a3d6b4610e26'],
			];
		$msgs = [
			'content'=>'欢迎你！',
			'title' =>'合陶家欢迎你！',
		];
		$res = $this->push->pushMessageToList($msgs,$datas);
		print_r($res);
		return $this->json($res);
	}
	//测试个推
    public function cesgetui()
    {
		//测试数据
		$msg = [
			'content'=>'单推接口案例（透传内容）',//透传内容
			'title'=>'通知栏标题',//通知栏标题
			'text'=>'通知栏内容',//通知栏内容
			'curl'=> request()->domain(),//通知栏链接
		];
		 $data=array(
			0=>['client_id'=>'1e170fd89af0e838d132e09592d4f3c1'],
			1=>['client_id'=>'ed032bc13ddaf827520a07215b1ec84a'],
			'system'=>2,//1为ios
		);
		$Pushs = new Pushs();
		$res = $Pushs->getTypes($msg,$data);
		return $this->json($res);
	}
	 
}