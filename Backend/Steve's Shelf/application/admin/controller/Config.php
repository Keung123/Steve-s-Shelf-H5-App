<?php
namespace app\admin\controller;
use think\Db;
use app\common\service\Config as ConfigService;
class Config extends Base{

	public function index(){
		if(request()->isAjax()){
			$ConfigService=new ConfigService();

			$key = input('request.key');
			$data = input('request.row/a');


            if(in_array($key, ['base', 'sms', 'apipay','express', 'commission','exchange','evaluate_yinzi','pick','update'])){

                $data = json_encode($data);
            }
            $res=$ConfigService->set($key,$data);
			return AjaxReturn($res,getErrorInfo($res));
		}else{
			return $this->fetch();
		}
	}
	
    public function guizeAdd(){
        if(request()->isAjax()){
            $ConfigService=new ConfigService();
            $data=input('post.row/a');
            foreach ($data as $key => &$val) {
                if ($key == 'jifenjieshao' || $key == "bangzhu" || $key == "order_yinzi" || $key == "setjifen"|| $key == "miaosha_time" ||$key == "jifenduihuan"||$key == "pick"||$key == "update") {
                    $val = json_encode($val);
                }
				if($key == "exchange"){
					 $val = json_encode($val);
					  $ConfigService->set($key,$val);
				}
                if($key == "full_gift"){
                   $val = json_encode($val);
                    $ConfigService->set($key,$val);
                }
				if($key == "evaluate_yinzi"){
				    $val = json_encode($val);
				    $ConfigService->set($key,$val);
                }

                if($key == "return_integral"){
                    $val = json_encode($val);
                    $ConfigService->set($key,$val);
                }
	
            }
            $res=$ConfigService->setGuize($data);
            return AjaxReturn($res,getErrorInfo($res));
        }else{

            return $this->fetch();
        }
    }
}