<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/3/28 9:53
 */
namespace app\admin\controller\activity;

use app\common\service\ActiveGoods;
use app\common\service\ActiveType;
use app\common\service\GoodsCategory;
use app\common\service\Goods as GoodsService;
use think\Db;

class Activity extends Base
{
    /**
     * 活动列表
     */
    public function actiList()
    {
        $activeModel = new ActiveType();
        $active_type_name = input('active_type_name');
        $this->assign('active_type_name',$active_type_name);
        if(request()->isAjax()){
            //排序
            $order="weigh desc";
            $map = [];
            if(input('get.search')){
                $map['active_type_name']=['like','%'.input('get.search').'%'];
            }
            if(input('active_type_name')){
                $map['active_type_name']=['like','%'.input('active_type_name').'%'];
            }
            $total=$activeModel->count($map);
            $limit=input('get.offset').",".input('get.limit');
            $rows=$activeModel->select($map,'*',$order,$limit);
            $status_list = array('进行中', '已结束');
            $type = ['','减价','打折','免邮','积分奖励','专题'];
            if ($rows) {
                foreach ($rows as &$val) {
                    $val['status'] = $status_list[$val['status']];
                    $val['active_title'] = $activeModel->getActive_title($val['active_id']);
                    $val['active_type'] = $type[$val['active_type']];
                }
            }
            return json(['total'=>$total,'rows'=>$rows]);
        }else{
            return $this->fetch();
        }
    }
    /**
     * 添加活动
     */
    public function actiAdd()
    {
        $activeModel = new ActiveType();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $row['start_time'] = strtotime($row['start_time']);
            $row['end_time'] = strtotime($row['end_time'])+3600*24-1;
            if($row['active_type_val']<0){
                return (['code'=>-1,'msg'=>'折扣（减价）不能小于0','data'=>'折扣（减价）不能小于0']);
            }
            if($row['start_time']>=$row['end_time']){
                return (['code'=>0,'msg'=>'开始时间必须小于结束时间','data'=>'开始时间必须大于结束时间']);
            }
            $res=db('active_type')->where('active_type_name',$row['active_type_name'])->find();
            if($res){
                return (['code'=>0,'msg'=>'活动名称不能重复','data'=>'活动名称不能重复']);
            }
            $res=$activeModel->add($row);

            //添加日志记录
            $id=db('active_type')->getLastInsID();
            $this->write_log('添加活动',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $active_list = $activeModel->getActive();
            $this->assign('active_list', $active_list);
            return $this->fetch();
        }
    }
    /**
     * 活动删除
     */
    public function actidel()
    {
        $ids=input('get.ids');
        //19：精选聚会和20：今日特卖两个活动不允许删除
        if(($ids == 19)||($ids == 20)){
            return (['code'=>0,'msg'=>'此活动不可删除','data'=>'']);
        }
        $map['id']=['in',$ids];

        $activeModel = new ActiveType();
        //判断此活动下是否有商品
        $active_ids = explode(',',$ids);
        $res=$activeModel->judge($active_ids);

        if(!$res){
            return (['code'=>0,'msg'=>'此活动下有商品不可删除','data'=>'']);
        }
        $res=$activeModel->delete($map);

        //添加日志记录
        $this->write_log('活动删除',$ids);

        return AjaxReturn($res);
    }
    /**
     * 自定义活动修改
     */
    public function actiEdit()
    {
        $activeModel = new ActiveType();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $id = input('post.id');
            $row['start_time'] = strtotime($row['start_time']);
            $row['pay_start_time'] = strtotime($row['pay_start_time']);
            $row['pay_end_time'] = strtotime($row['pay_end_time']);
            $row['end_time'] = strtotime($row['end_time'])+3600*24-1;
            if($row['active_type_val']<0){
                return (['code'=>-1,'msg'=>'折扣（减价）不能小于0','data'=>'折扣（减价）不能小于0']);
            }
            if($row['start_time']>=$row['end_time']){
                return (['code'=>0,'msg'=>'开始时间必须小于结束时间','data'=>'开始时间必须大于结束时间']);
            }
            $res=db('active_type')->where(array('id'=>['neq',$id],'active_type_name'=>$row['active_type_name']))->select();
            if(count($res)>1){
                return(['code'=>0,'msg'=>'活动名称不能重复','data'=>'活动名称不能重复']);
            }
            if($res){
                return (['code'=>0,'msg'=>'活动名称不能重复','data'=>'活动名称不能重复']);
            }

            $res=$activeModel->save(array('id' => $id),$row);

            //添加日志记录
            $this->write_log('自定义活动修改',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $info = $activeModel->find($map);
            $info['start_time'] = date('Y-m-d',$info['start_time']);
            $info['end_time'] = date('Y-m-d',$info['end_time']);
            $info['pay_start_time'] = date('Y-m-d',$info['pay_start_time']);
            $info['pay_end_time'] = date('Y-m-d',$info['pay_end_time']);
            $this->assign('row',$info);
            $active_list = $activeModel->getActive();

            $this->assign('active_list', $active_list);

            if($map['id'] <=9 ){
                return $this->fetch('actiEdit2');
            }
            return $this->fetch();
        }
    }

    /**
     * 活动商品列表
     */
    public function activeGoodsList(){
        $goods_name = input('goods_name');
        $active_id = input('active_id');
        $active_type_name = input('active_type_name');
        $this->assign('active_type_name',$active_type_name);
        $this->assign('goods_name',$goods_name);
        $this->assign('active_id',$active_id);
        if(request()->isAjax()){

            if(input('get.search')){
                $where['goods_name']=['like','%'.input('get.search').'%'];
            }
            if(input('active_type_name')){
                $where['b.active_type_name']=['like','%'.input('active_type_name').'%'];
            }
            if(input('goods_name')){
                $where['c.goods_name']=['like','%'.input('goods_name').'%'];
            }
            if(input('active_id')){
                $where['a.active_type_id']=['eq',input('active_id')];
            }
            $ActiveGoodsmodel = new ActiveGoods();
            $total = count($ActiveGoodsmodel->getLisths($where));
            //排序
            $order="sort desc";
            //limit
            $limit=input('get.offset').",".input('get.limit');

            $list = $ActiveGoodsmodel->getListh($where,'*',$order,$limit);
            if ($list) {
                $status_list = array(0=>'进行中',1=>'已结束');
                foreach ($list as &$val) {
                    $goodsinfo = $ActiveGoodsmodel->getGoodsinfo($val['goods_id']);
                    $val['picture'] = $goodsinfo['picture'];
                    $val['goods_name'] = $goodsinfo['goods_name'];
                    $val['price'] = $goodsinfo['price'];
                    $val['status'] = $status_list[$val['status']];
                    $active_info = $ActiveGoodsmodel->getActiveinfos($val['active_type_id']);
                    if ($active_info['active_type'] == 1) {
                        $val['active_price'] = $val['price'] - $active_info['active_type_val'];
                    } else if($active_info['active_type'] == 2) {
                        $val['active_price'] = $val['price'] * $active_info['active_type_val'] / 100;
                    } else {
                        $val['active_price'] = $val['price'];
                    }

                    $val['active_name'] = $active_info['active_type_name'];
                }
            }
            return json(['total'=>$total,'rows'=>$list]);
        }else{
            //自定义活动列表
            $ActiveGoodsmodel = new ActiveGoods();
            $activelist = $ActiveGoodsmodel->getActive();
            $this->assign('activelist',$activelist);
            return $this->fetch();
        }
    }
    /**
     * 活动商品添加
     */
    public function activeGoodsAdd () {

        $ActiveGoodsmodel = new ActiveGoods();
        $GoodsService = new GoodsService();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $row['add_time'] = time();
            if($row['goods_num']<=0){
                return (['code'=>0,'msg'=>'商品总数必须大于0','data'=>'商品总数必须大于0']);
            }
            $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
            if($stock['stock']==0){
                return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
            }
            if($stock['stock']>=0 && $row['goods_num']<=$stock['stock'])
            {

                $res=$ActiveGoodsmodel->add($row);
            }else{
                return (['code'=>0,'msg'=>'参与活动的商品数量大于此商品库存,不能添加到活动商品','data'=>'参与活动的商品数量大于此商品库存,不能添加到活动商品']);
            }



            $prom_id = Db::name('active_goods')->getLastInsID();//获取最新id

            //添加日志记录
            $this->write_log('活动商品添加',$prom_id);

            $result = $GoodsService->updateGoods($row['goods_id'],$row['active_type_id'],$prom_id);
            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $activeList = $ActiveGoodsmodel->getActive();
            $this->assign('activeList', $activeList);
            //获取分类列表
            $GoodsCategory=new GoodsCategory();
            $id['pid']=0;
            $rows=$GoodsCategory->select($id);
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            return $this->fetch();
        }
    }
    /**
     * 判断是否展示排序
     */
    public function showStyle(){
        if(Request()->isAjax()){
            $type=db("active_type")->where("id",input("activeId/d"))->value("active_type");
            if($type==5){
                return json(['status'=>1,'msg'=>'操作']);
            }else{
                return json(['status'=>0,'msg'=>'暂无']);
            }
        }

    }
    /**
     * 活动商品修改
     */
    public function activeGoodsEdit () {

        $ActiveGoodsmodel = new ActiveGoods();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $id = input('post.id');

            if($row['goods_num']<0){
                return (['code'=>0,'msg'=>'商品总数必须大于0','data'=>'商品总数必须大于0']);
            }
            $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
            if($row['show_status'] !=0){
                if($stock['stock']==0){
                    return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
                }
            }
            if($stock['stock']<$row['goods_num']){
                return (['code'=>0,'msg'=>'此商品添加数量不能大于库存,不能添加到活动商品','data'=>'此商品添加数量不能大于库存,不能添加到活动商品']);
            }
            //商品是否改变  更新商品表
            $GoodsService = new GoodsService();
            $data = $ActiveGoodsmodel->find(array('id' => $id));
            if($row['goods_id'] != $data['goods_id']){
                $result = $GoodsService->checkGoods($row['goods_id']);
                if($result){
                    return (['code'=>0,'msg'=>'此商品已经添加为活动商品','data'=>'此商品已经添加为活动商品']);
                }
                $GoodsService->updateGoods($data['goods_id']);
                // 5:抢购/秒杀;  更新商品表
                $result = $GoodsService->updateGoods($row['goods_id'],$row['active_type_id'],$id);
                if(!$result){
                    return AjaxReturn($result,getErrorInfo($result));
                }
            }
            $result = $GoodsService->updateGoods($row['goods_id'],$row['active_type_id'],$id);
            $res=$ActiveGoodsmodel->save(array('id' => $id),$row);

            //添加日志记录
            $this->write_log('活动商品修改',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $activeList = $ActiveGoodsmodel->getActive();
            $this->assign('activeList', $activeList);
            $map['id']=input('get.ids');
            $info = $ActiveGoodsmodel->find($map);
            $goods_model = new GoodsService();
            $goodsinfo =$goods_model->find(array('goods_id' => $info['goods_id']));
            $info['goryid'] = $goodsinfo['category_id'];
            $info['goods_price'] = $goodsinfo['price'];
            $info['goods_name'] = $goodsinfo['goods_name'];
            $info['category_id'] = $goodsinfo['category_id'];
            $info['stock'] = $goodsinfo['stock'];
            $this->assign('row',$info);
            //获取一级分类
            $id=$info['category_id'];
            $category_id=db('goods_category')->field('pid')->where(['category_id'=>$id])->find();
            $this->assign('category_id',$category_id);
            //获取二级分类
            $category=db('goods_category')->select();
            $this->assign('allcategory',$category);
            //获得活动类型信息
            $style=db("active_type")->where("id",$info['active_type_id'])->value("active_type");
            $this->assign('type',$style);
            // 获取该分类下的所有商品
            $goods_list = $goods_model->select(array('category_id' => $info['goryid']));
            $this->assign('goods_list',$goods_list);
            //获取分类列表
            $rows=db('goods_category')->where('pid',0)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            return $this->fetch();
        }
    }
    /**
     * 删除 活动商品
     */
    public function activeGoodsDel()
    {
        $ids=input('get.ids');
        $ActiveGoodsmodel = new ActiveGoods();
        $where = array();
        $where['id'] = array('in', $ids);
        //检测此活动商品是否在进行中
        $status=db('active_goods')->field('status')->where($where)->select();
        $array=[];
        foreach($status as $key=>$val){
            $array[]=$val['status'];
        }
        if(in_array(0,$array)){
            return $this->error('此操作包含活动进行中商品不能删除');
            exit;
        }
        $goods_id =  Db::name('active_goods')->where($where)->column('goods_id');
        $goods_str = implode(',',$goods_id);

        //商品改为无活动状态
        if($goods_str){
            $data=[
                'prom_type'=>0,
                'prom_id'=>0
            ];
            Db::name('goods')->where(array('goods_id'=>['in',$goods_str]))->update($data);
        }
        $res=$ActiveGoodsmodel->delete($where);

        //添加日志记录
        $this->write_log('删除活动商品',$ids);

        return AjaxReturn($res);
    }
}