<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/3/28 14:26
 */

namespace app\admin\controller\service;
use app\common\service\Kefu as KefuService;
use app\common\service\Admin as AdminService;
use app\common\service\User as UserService;
use app\admin\controller\Base;
class Im extends Base
{
    public function _initialize()
    {
        parent::_initialize();
        $this->service = new UserService;
    }
    /*
     *客服列表
    */
    public function kflist(){
        if(request()->isAjax()){
            $KefuService=new KefuService();
            $order = 'kefu_id asc';
            $map = [];
            $limit = '';
//			$limit=input('get.offset').",".input('get.limit');
//
//			if(input('get.search')){
//				$map['kefu_name']=['like','%'.input('get.search').'%'];
//			}
            // 供应商订单
            if(session('group_id') == 9){
                $uid = session('admin_id');
                $kf_id =$KefuService->getkefu($uid);
                if($kf_id){
                    $map['kefu_id'] = $kf_id;
                }
            }
            $total = $KefuService->count($map);
            $rows = $KefuService->select($map,'*',$order,$limit);
            if($rows){
                foreach ($rows as $val){
                    $val['status'] = $val['status'] == 0?'正常':'退出';
                }
            }
            return json(['total'=>$total,'rows'=>$rows]);
        }
        else{

            return $this->fetch();
        }
    }
    /*
     *客服添加
    */
    public function kfadd(){
        $AdminService=new AdminService();
        if(request()->isAjax()){
            $row = input('post.row/a');
            $admin_id = input('admin_id',0);
            $map['kefu_id'] = $row['kefu_id'];
            $u_id = input('u_id',0);
            /*if(!($u_id || $admin_id)){
                return json(['status'=>0,'msg'=>'参数错误 请填写完整数据！']);
            }*/
            $map2['admin_id'] = $admin_id;
            $KefuService=new KefuService();
            $res = $KefuService->add($row);
            $kefu_id = Db::name('kefu')->getLastInsID();
            if(!empty($res) && $u_id!=0){
                $AdminService->save($map2,array('kf_id'=>$kefu_id,'u_id'=>$u_id));
                $this->service->editInfo($u_id,1);
            }
            $this->write_log('增加客服人员',$kefu_id);
            return AjaxReturn($res,getErrorInfo($res));
            //添加日志
        }else{
            $kefu_id = input('get.ids');
            $row = $this->service->getKfInfo($kefu_id);
            // 用户
            $user=$AdminService->getUser();
            $this->assign('user', $user);

            // 客服组
            $map = [
                'group_id'=>9,
                'kf_id'=>['eq',0],
            ];
            $admin=$AdminService->select($map);
            $this->assign('admin', $admin);


            $this->assign('row',$row);
            return $this->fetch();
        }
    }
    /*
     *客服修改 
    */
    public function kfedit(){
        $AdminService=new AdminService();
        $KefuSer= new KefuService();
        $start_time = input('start_time');
        if(request()->isAjax()){
            $row = input('post.row/a');
            $map['kefu_id'] = $row['kefu_id'];
            $u_id = input('u_id');
            $admin_id = input('admin_id');
            $KefuService=new KefuService();
            $rs = $KefuService->save($map,$row);
            $map1['kf_id'] = $row['kefu_id'];
            $map3['admin_id'] = $admin_id;
            //判断选择的管理员是否已经关联了客服
            $is_kf_ad = Db::name('admin')->where(['admin_id'=>$admin_id,'kf_id'=>['neq',$row['kefu_id']]])->find();
            if(!empty($is_kf_ad) && $is_kf_ad['kf_id']!=0){
                return ['code'=>0,'msg'=>'该管理员已有对应的客服，请重新选择！'];
            }
            //判断用户是否有对应的客服
            $is_kf_user = Db::name('admin')->where(['u_id'=>$u_id,'kf_id'=>['neq',$row['kefu_id']]])->find();
            if(!empty($is_kf_user) && $is_kf_user['u_id']!=0){
                return ['code'=>0,'msg'=>'选择的用户已有对应客服，请重新选择'];
            }
            //修改客服
            $res = $AdminService->save($map3,array('kf_id'=>$row['kefu_id'],'u_id'=>$u_id));
//			$res = $AdminService->find($map1);
            /*	if(empty($res)){
                    $AdminService->save($map3,array('kf_id'=>$row['kefu_id'],'u_id'=>$u_id));
                    $this->service->editInfo($u_id,1);
                    return ['code'=>1,'msg'=>'修改成功'];
                }*/
            /* if($res['admin_id'] != $admin_id){
                 //判断选择的这个管理员是否已经对应了客服了；如果是，则拒绝修改


                 $map2['admin_id'] = $res['admin_id'];
                 if($res['u_id'] != $u_id){

                     $AdminService->save($map3,array('kf_id'=>$row['kefu_id'],'u_id'=>$u_id));
                     $this->service->editInfo($res['u_id']);
                     $this->service->editInfo($u_id,1);
                 }else{
 //						$AdminService->save($map2,array('kf_id'=>0));
                     $AdminService->save($map3,array('kf_id'=>$row['kefu_id']));
                 }
             }else{
                 if($res['u_id'] != $u_id){
                     $is_kf_user = $AdminService->find(['u_id'=>$u_id]);
                     if($is_kf_user['u_id']!=0){
                         return ['code'=>0,'msg'=>'修改失败','data'=>'选择的用户已经有对应客服了'];
                     }else{
                         $AdminService->save($map3,array('u_id'=>$u_id));
                         $this->service->editInfo($res['u_id']);
                         $this->service->editInfo($u_id,1);
                     }

                 }
             }

 */
            return AjaxReturn($res,getErrorInfo($res));
            //添加日志
            $this->write_log('客服修改',$map['category_id']);
        }else{
            $kefu_id = input('get.ids');

            $row = $this->service->getKfInfo($kefu_id);
            // 用户
            $user=$AdminService->getUsers();
            $this->assign('user', $user);
            // 客服组
            $map = [
                'group_id'=>9,
            ];
            $admin=$AdminService->select($map);
            $this->assign('admin', $admin);
            $this->assign('row',$row);
            return $this->fetch();
        }

    }
}