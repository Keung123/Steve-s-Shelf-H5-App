<?php
/**
 * Created by PhpStorm.
 * Date: 2018/10/29
 * Time: 14:44
 */
namespace app\common\service;
use think\Db;
class Business extends Base{

    public function __construct(){
        $BusinessModel = new \app\common\model\Business();
        $this->model = $BusinessModel;
    }
	/* 
    *  营业执照上传 判断
	*   $data
    */
    public function judgeLicense($uid){
		$res = $this->model->where('b_uid',$uid)->find();
		//最近的一条发票信息
		$bill =  Db::name('bill')->where('user_id',$uid)->order('add_time desc')->find();
	
		//sh_status 发票审核状态：0：发票异常；1：发票正常  2 待审核  -1 未提交
		if($bill){
			$res['bill_status'] = $bill['sh_status'];
			$b_img = trim($bill['b_img'],',');
			$bill['b_img']  = explode(',',$b_img);
			$res['bill']  = $bill;
			//上一条 发票审核通过 可以提交下一条
			if($bill['sh_status'] == 1){
				$res['bill_status'] = -1;
			}
		}else{
			$res['bill_status'] = -1;
			$res['bill']  =  '';
		}
		return $res;
	}
	/* 
    *  营业执照上传
	*   $data
    */
    public function addLicense($data){
		$res = $this->model->insert($data);
		return $res;
	}
	/* 
    *  获取营业执照信息
	*   $uid
    */
    public function getLicenses($uid){
		$row = Db::name('business_guakao')
			->alias('a')
			->join('business b','a.b_id = b.b_id')
			->where('a.bg_uid',$uid)
			->field('a.bg_id,b.company_name,b.corporation_name,b.img,b.taxes,b.number')
			->find();
		if(!$row){
			$row = $this->model->where('b_uid',$uid)->find();
		}
		//最近的一条发票信息
		$bill =  Db::name('bill')->where('user_id',$uid)->order('add_time desc')->find();
		//sh_status 发票审核状态：0：发票异常；1：发票正常  2 待审核  -1 未提交
		
		if($bill){
			$row['bill_status'] = $bill['sh_status'];
			$b_img = trim($bill['b_img'],',');
			$bill['b_img']  = explode(',',$b_img);
			$row['bill'] = $bill;
			//上一条 发票审核通过 才可以提交下一条
			if($row['bill_status'] ==1){
				$row['bill_status'] = -1;
			}
		}else{
			$row['bill_status'] = -1;
			$row['bill'] = '';
		}
		return $row;
	}
	/* 
    * 上传发票
	*    
    */
    public function uploadInvoice($data){
		$res = Db::name('bill')->insert($data);
		return $res;
	}
	/* 
    * 修改营业执照
	*    
    */
    public function updateLicense($data,$b_id){
		$res = $this->model->where('b_id',$b_id)->update($data);
		return $res;
	}
	/* 
    * 获取营业执照id
	*    
    */
    public function getLicenseId($uid){
		$b_id = $this->model->where('b_uid',$uid)->value('b_id');
		return $b_id;
	}

	/*
	 * 获取营业执照信息
	 */
	public function getLicenseData($uid)
    {
        $res = $this->model->where('b_uid',$uid)->find();
        return $res;
    }
}
