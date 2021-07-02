<?php
namespace app\api\controller;

use app\common\service\Business as BusinessService;
use app\common\service\Bill as BillService;
use think\Db;

class Business extends Common{
    public $uid;
    protected $business;
    public function __construct(){
        parent::__construct();
        $this->business = new  BusinessService();
        $token = input('token');
        $uid = input('uid');
        if(!$token || !$uid){
            $ajaxReturn = json_encode(array(['data'=>[],'status'=>0,'msg'=>'参数错误']));
            echo $ajaxReturn;
            exit;
        }
        $user_id = $this->getUid($token, $uid);
        if ($user_id) {
            $this->uid = $user_id;
        } else {
            $ajaxReturn = json_encode(array(['data'=>[],'status'=>-1,'msg'=>'参数错误']));
            echo $ajaxReturn;
            exit;
        }
    }

    /**
     * 营业执照上传
     */
    public function uploadLicense()
    {
        $uid = $this->uid;
		$company_name = input('company_name');
		$corporation_name = input('corporation_name');
		$img = input('img');
		$number = input('number');
		$taxes = input('taxes');
		 
		if(empty($uid) ||empty($company_name) || empty($corporation_name) ||empty($img) ||empty($number) || empty($taxes)){
			return $this->json('', 0, '参数错误');
		}
        $b_data = $this->business->getLicenseData($uid);
		$data = [
			'b_uid'=> $uid, //用户ID'
			'company_name'=> $company_name, //公司名称
			'corporation_name'=> $corporation_name, //法人姓名
			'img'=> $img, //营业执照照片
			'number'=> $number, //税务登记号
			'taxes'=> $taxes, //税率
			'status'=> 0, //审核状态：0:未审核；1：已通过；2：未通过；
			'submit_time'=> time(), //提交时间
		];
		if($b_data){
		    if($b_data['status']==1 && $b_data['taxes']!=$taxes){
		        //变更税率，记录到日志
                $tax_data = [
                    'user_id'=> $uid,
                    'before_tax'=> $b_data['taxes'],
                    'now_tax'=> $taxes,
                    'add_time'=> time(), //提交时间
                ];
                Db::name('tax_log')->insert($tax_data);
            }
			$res = $this->business->updateLicense($data,$b_data['b_id']);
		}else{ 
			$res = $this->business->addLicense($data);
		} 

		if($res){
			return $this->json('', 1, '提交成功');
		}
		return $this->json('', 0, '提交失败');
    }
    /**
    *获取营业执照相关信息
    */
    public function getBusinessInfo()
    {
        $uid = $this->uid;
        $res = Db::name('business')->where('b_uid',$uid)->find();
        return $this->json($res, 1, 'success');
    }
	/**
     * 营业执照上传判断
     */
    public function judgeLicense(){
        $uid = $this->uid;
		$res = Db::name('business')->where('b_uid',$uid)->find();
		$data = ['bus_status'=>0];
		if(!empty($res) && $res['status']==1){
		    $data['bus_status'] = 1;
        }
		return $this->json($data, 1, '');
	}
	
	/**
     * 发票上传
     */
    public function uploadInvoice(){
		$uid = $this->uid;
        $b_balance = input('b_balance');
        $b_img = input('b_img');

        if(empty($uid) || empty($b_balance) || empty($b_img)){
            return $this->json('', 0, '参数错误');
        }
        $data = [
            'b_balance'=> $b_balance, //发票余额
            'user_id'=> $uid, //用户id
            'b_img'=> $b_img, //发票图片
            'add_time'=> time(), //提交时间
        ];
        $res = $this->business->uploadInvoice($data);
        if($res){
            return $this->json('', 1, '上传成功！');
        }
        return $this->json('', 0, '上传失败！');

	}

	/**
	 * 发票历史记录
	 */
	public function getInvoicesHistory()
    {
        $uid = $this->uid;
        $page = input('p');
        if(empty($uid) || empty($page)){
            return $this->json('', 0, '参数错误');
        }
        $bs = new BillService();
        $data = $bs->getHistorys($uid,$page);
        return $this->json($data, 1, 'success');
    }

}