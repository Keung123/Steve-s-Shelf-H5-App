<?php
/**
 * Created by PhpStorm.
 * Content:体现管理
 * Date: 2018/11/3
 * Time: 11:35
 */

namespace app\admin\controller;
use think\Db;
use app\common\service\Sms;
use think\Session;

class Cash extends  Base
{
    /*
     * 提现列表
     * */
    public function index(){
        $user_name = trim(input('user_name'));
        $user_mobile = trim(input('user_mobile'));
        $small_money = trim(input('small_money'));
        $big_money = trim(input('big_money'));
        $cash_status = trim(input('cash_status'));

        $this->assign('user_name',$user_name);
        $this->assign('small_money',$small_money);
        $this->assign('big_money',$big_money);
        $this->assign('cash_status',$cash_status);
        $this->assign('user_mobile',$user_mobile);
        if(request()->isAjax()){
            $order = 'cash_id desc';
            $limit = input('get.offset').','.input('get.limit');
            $where=[];
            $small_money = input('small_money');
            $big_money = input('big_money');
            $cash_status = input('cash_status');
            if(input('user_name')){
                $where['b.user_name'] = ['like','%'.$user_name.'%'];
            }
            if(input('user_mobile')){
                $where['b.user_mobile'] = ['eq',$user_mobile];
            }
            if($small_money && $big_money){
                $where['a.cash_amount'] = ['between',$small_money.','.$big_money];
            }elseif($small_money){
                $where['a.cash_amount'] = ['>=',$small_money];
            }elseif($big_money){
                $where['a.cash_amount'] = ['<=',$big_money];
            }

            if($cash_status==null || $cash_status=='all'){
                $where['a.cash_stat'] = ['neq',0];
            }elseif($cash_status== 1){
                $where['a.cash_stat'] = ['eq',1];
            }elseif($cash_status== 2){
                $where['a.cash_stat'] = ['eq',2];
            }else{
                $where['a.cash_stat'] = ['eq',3];
            }
            $rows = db('cash')->alias('a')->where($where)
                    ->join('__USERS__ b','a.cash_uid=b.user_id')
                    ->field('a.*,b.user_name,b.user_mobile')
                    ->order($order)->limit($limit)->select();
            for($i=0;$i<count($rows);$i++){
                $rows[$i]['cash_addtime'] = $rows[$i]['cash_addtime']?date('Y-m-d H:i:s',$rows[$i]['cash_addtime']):'';
                $rows[$i]['cash_paytime'] = $rows[$i]['cash_paytime']?date('Y-m-d H:i:s',$rows[$i]['cash_paytime']):'';
                $rows[$i]['cash_way'] = ($rows[$i]['cash_way'] == 1?'支付宝':($rows[$i]['cash_way'] == 2 ?'微信提现':'银行卡提现'));
                $rows[$i]['cash_stat'] = ($rows[$i]['cash_stat'] == 1?'未审核':($rows[$i]['cash_stat'] == 2 ?'同意':'拒绝'));
            }
            $cashService = new \app\common\service\Cash();
            $total = $cashService->count();
            return json(['total'=>$total,'rows'=>$rows]);
        }else{
            return $this->fetch();
        }
    }

    /*
     * 打款
     * */
    public function payMoney(){
        if(request()->isAjax()){
            $row = input('post.row/a');
            $map['cash_id'] = input('post.cash_id');
            if(!$row){
                return (['code'=>0,'msg'=>'网络错误']);
            }
            if($row['cash_stat']==3){
                if(empty($row['cash_comm'])){
                    return (['code'=>0,'msg'=>'未通过原因不能为空']);
                }
            }
            $row['cash_operat'] = Session::get('admin_name');
            $row['cash_paytime']= time();
            $CashService = new \app\common\service\Cash();
            $res = $CashService->save($map,$row);
            $info = $CashService->find($map);
            $user_mobile = Db::name('users')->where(['user_id' => $info['cash_uid']])->value('user_mobile');
            if ($row['cash_stat'] == 2) {
                // 审核成功 发送短信
               // $this->sendMsg($user_mobile, $info['cash_uname'], $info['cash_amount'], true);
            } elseif ($row['cash_stat'] == 3) {
				Db::name('users')->where(['user_id' => $info['cash_uid']])->setInc('user_account',$info['cash_amount']);
				$this->reflect($info,$map['cash_id']);
                // 审核失败 发送短信
               // $this->sendMsg($user_mobile, $info['cash_uname'], $info['cash_amount'], false);
            }
            //添加日志记录
            $this->write_log('提现打款',$map['cash_id']);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $cash_id = input('get.cash_id');
            $map['cash_id'] = ['eq',$cash_id];
            $row = db('cash')->alias('a')->where($map)
                ->join('__USERS__ b','a.cash_uid=b.user_id')
                ->field('a.*,b.user_name,b.user_mobile,b.user_truename,b.user_id_no,b.s_invite_code')
                ->find();
			if($row['cash_way'] == 1){
				 $row['card_no'] =  $row['cash_ali_no'];
                $row['cash_img'] = $row['cash_ali_img'];
			}else if($row['cash_way'] == 2){
				$row['card_no'] =  $row['cash_wx_no'];
				$row['cash_img'] = $row['cash_wx_img'];
			}else{
				$row['card_no'] =  $row['cash_bank_no'];
			}
            $row['cash_way'] = ($row['cash_way'] == 1?'支付宝':($row['cash_way'] == 2 ?'微信提现':'银行卡提现'));
            $this->assign('row',$row);
            return $this->fetch();
        }
    }
    public function sendMsg($phone, $name, $money, $type)
    {
        $user_model = new \app\common\service\User();
        $user_model->sendMsg($phone, $name, $money, $type);
    }

    /*
     * 详情
     * */
    public function show(){
        $cash_id = input('get.cash_id');
        $map['cash_id'] = ['eq',$cash_id];
        $row = db('cash')->alias('a')->where($map)
            ->join('__USERS__ b','a.cash_uid=b.user_id')
            ->field('a.*,b.user_name,b.user_mobile,b.user_truename,b.user_id_no,b.s_invite_code')
            ->find();
        if($row['cash_way'] == 1){
            $row['cash_img'] = $row['cash_ali_img'];
        }else if($row['cash_way'] == 2){
            $row['cash_img'] = $row['cash_wx_img'];
        }else{
            $row['cash_img'] = $row['cash_wx_img'];
        }
        $row['cash_way'] = ($row['cash_way'] == 1?'支付宝':($row['cash_way'] == 2 ?'微信提现':'银行卡提现'));
        $this->assign('row',$row);
        return $this->fetch();
    }

    /*
     * 删除
     * */
    public function delete(){
        $ids=input('get.ids');
        $map['cash_id']=['in',$ids];
        $CashService = new \app\common\service\Cash();
        $res= $CashService->delete($map);
        //添加日志
        $this->write_log('提现删除',$ids);

        return AjaxReturn($res);
    }
	
	/*
     * 体现失败记录 未完成
     * 
	 */
    public function reflect($info,$acco_type_id){
		$data = [
			'a_uid'=> $info['cash_uid'],
			'acco_num'=>$info['cash_amount'],
			'acco_type'=>7,
			'acco_desc'=>'提现失败 返还账户',
			'acco_type_id'=>$acco_type_id,
			'acco_time'=>time(),
		];
		$res = Db::name('account_log')->insert($data);
		return $res;
	}
}