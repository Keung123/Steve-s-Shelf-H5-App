<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/3/28 9:31
 */
namespace app\admin\controller\activity;

use app\common\service\GoodsActivity as GoodsActivityServer;
use app\common\service\Goods as GoodsService;
use app\common\service\GoodsActivity;
use app\common\service\GoodsCategory;
use think\Db;

class PresaleActivity extends  Base
{
    function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 预售活动信息列表
     */
    function pre_sell_list(){
        $goods_name = input('goods_name');
        $this->assign('goods_name',$goods_name);
        if(request()->isAjax()){


            if(input('get.search')){
                $map['act_name']=['like','%'.input('get.search').'%'];
            }

            if(input('goods_name')){
                $map['goods_name']=['like','%'.input('goods_name').'%'];
            }

//            $lists = model("GoodsActivity")->field("act_id,act_name,is_finished,start_time,end_time,deposit,goods_name,price,deposit_use,is_end")->order("act_id desc")->where($map)->select();
            $GoodsActivity= new GoodsActivity();
            $order='act_id desc';
            $limit=input('get.offset').",".input('get.limit');
            $field='act_id,act_name,is_finished,start_time,end_time,deposit,goods_name,price,deposit_use,is_end';
            $lists=$GoodsActivity->select($map,$field,$order,$limit);
            if($lists){
                foreach ($lists as $key=>$value){
                    $status_list = array(0=>'正常',1=>'删除');
                    $lists[$key]['is_finished']=$this->getPreStatus($value);
                    $lists[$key]['is_end']= $status_list[$value['is_end']];
                    $lists[$key]['start_time']= date("Y-m-d H:i",$value['start_time']);
                }
            }
            $total=model("goods_activity")->count();
            return json(['total'=>$total,'rows'=>$lists]);
        }
        return $this->fetch();
    }
    /**
     * 编辑预售活动商品信息
     */
    function edit_pre_sell(){
        $GoodsCategory=new GoodsCategory();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $map['act_id']=input('post.act_id');
            if($row){
                $row['act_name'] =  $row['goods_name'];//活动name 当前为商品名称
                $row['start_time'] = strtotime($row['start_time']);
            }
            if($row['total_goods']<=0){
                return (['code'=>0,'msg'=>'预售库存必须大于0','data'=>'预售库存必须大于0']);
            }

            $GoodsActivity = new  GoodsActivityServer();
            $res = $GoodsActivity->save($map,$row);

            //日志记录
            $this->write_log("预售活动编辑",$map['act_id']);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['act_id']=input('get.ids');
            $row= model("GoodsActivity")->find($map);
            $goodsinfo = model("Goods")->find(array('goods_id' => $row['goods_id']));
            $row['goryid'] = $goodsinfo['category_id'];
            $row['goods_price'] = $goodsinfo['price'];
            $row['stock'] = $goodsinfo['stock'];

            //获取二级分类
            $id=$goodsinfo['category_id'];
            $category_id=db('goods_category')->field('pid')->where(['category_id'=>$id])->find();
            $this->assign('category_id',$category_id);

            //获取二级分类
            $category=db('goods_category')->where('pid',$category_id['pid'])->select();
            $this->assign('allcategory',$category);

            // 获取该分类下的所有商品
            $goods_list = model("Goods")->where(array('category_id' => $row['goryid']))->select();
            $this->assign('goods_list',$goods_list);

            //获取分类列表
            $list=db('goods_category')->where('pid',0)->select();
            $list=\util\Tree::makeTreeForHtml(collection($list)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$list);
            $this->assign('row',$row);
            return $this->fetch();
        }
    }
    /**
     * 添加预售活动商品
     */
    function add_pre_sell(){
        if(request()->isAjax()){
            $row = input('post.row/a');
            $GoodsService = new GoodsService();
            $result = $GoodsService->checkGoods($row['goods_id']);
            if($result){
                return (['code'=>0,'msg'=>'此商品已经添加为活动商品','data'=>'此商品已经添加为活动商品']);
            }
            if($row){
                $row['start_time'] = time();
                $row['act_name'] =  $row['goods_name'];//活动name 当前为商品名称
            }
            if($row['total_goods']<=0){
                return (['code'=>0,'msg'=>'预售库存必须大于0','data'=>'预售库存必须大于0']);
            }
            $res = model("GoodsActivity")->save($row);
            if($res){
                //2:预售;  更新商品的 数据
                $prom_id = Db::name('goods_activity')->getLastInsID();//获取最新id
                $result = $GoodsService->updateGoods($row['goods_id'],$prom_type=2,$prom_id);
            }
            return AjaxReturn($res,getErrorInfo($res));
        }else{
            //获取分类列表
            $GoodsCategory=new GoodsCategory();
            $rows=db('goods_category')->where('pid',0)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            return $this->fetch();
        }
    }
    /**
     * 删除预售活动商品
     */
    function del_pre_sell(){
        $ids=input("ids/a");
        //动态改变商品活动
        $goodsId = model("goods_activity")->where(['act_id'=>['in',$ids]])->find();
        if($goodsId){
            $extInfo=unserialize($goodsId['ext_info']);
            $goodsId['retainage_end']=$extInfo['retainage_end'];
            $nowTime=time();
//			//状态未结束时 进行删除判断
            if($goodsId['is_end'] == 0){
                return json(['code'=>0,'msg'=>'该活动正在进行中，请先结束活动！']);exit;
            }

            $res = model("GoodsActivity")->where(['act_id'=>['in',$ids]])->delete();
            $this->write_log("预售活动删除",$goodsId['act_id']);
            if($res){
                //更新商品表
                $GoodsService = new GoodsService();
                $res = $GoodsService->updateGoods($goodsId['goods_id']);
            }

            return ajaxReturn($res,getErrorInfo($res));
        }
        return json(['code'=>0,'msg'=>'非法操作！']);
    }
    //处理操作信息
    function handelActivity(){
        if(Request()->isAjax()){
            $row=input("row/a");
            $act=$row['act'];

            $active_info = model("active_type")->where('id', 2)->find();
            $startTime=$active_info['start_time'];
            $endTime=$active_info['end_time'];
            if($startTime>$endTime){
                return  AjaxReturn(0,getErrorInfo(15));
            }
            $payStartTime=$active_info['pay_start_time'];
            $payEndTime=$active_info['pay_end_time'];
            if($payStartTime>$payEndTime){
                return  AjaxReturn(0,getErrorInfo(15));
            }
//            if($endTime>$payStartTime){
//               // return AjaxReturn(0,getErrorInfo(16));
//                return json(['code'=>0,'msg'=>'尾款支付时间不能小于活动结束时间']);exit;
//            }
//            if($row['group_num']<$row['total_goods']){
//                return json(['code'=>0,'msg'=>'预售库存不能超过商品总库存！']);exit;
//            }
            if($row['deposit_use']>=$row['price']){
                return json(['code'=>0,'msg'=>'定金抵扣金额不能超过实际抵用金额']);exit;
            }
            if($row['deposit']>$row['deposit_use']){
                return json(['code'=>0,'msg'=>'定金金额不能大于实际抵用金额']);exit;
            }
            if($row['deposit']>$row['price']){
                return json(['code'=>0,'msg'=>'定金金额不能大于商品金额！']);exit;
            }

            $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
            if($stock['stock']==0){
                return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
            }
            if($stock['stock']<$row['total_goods']){
                return (['code'=>0,'msg'=>'此商品添加数量大于库存,不能添加到活动商品','data'=>'此商品添加数量大于库存,不能添加到活动商品']);
            }


            $insertData=[
                'act_name'=>$row['goods_name'],
                'act_desc'=>'',
                'act_type'=>2,
                'goods_id'=>$row['goods_id'],
                'goods_name'=>$row['goods_name'],
                'start_time'=>$startTime,
                'end_time'=>$endTime,
                'is_finished'=>0,
                'deposit'=>$row['deposit'],
                'price'=>$row['price'],
                'total_goods'=>$row['total_goods'],
                'deposit_use'=>$row['deposit_use'],
                'is_end'=>$row['is_end']
            ];
            //把数据复制给extinfo数组
            $extInfo=$insertData;
            $extInfo['retainage_end']=$payEndTime;
            $extInfo['retainage_start']=$payStartTime;
            $extInfo['deliver_desc']=$row['deliver_desc'];
            $extInfo['total_goods']=$row['total_goods'];
            $extInfo['deposit_use']=$row['deposit_use'];
            $insertData['ext_info']=serialize($extInfo);
            switch ($act){
                case'add'://进行添加操作
                    $res=model("GoodsActivity")->insertGetId($insertData);
                    model("Goods")->where(['goods_id'=>$row['goods_id']])->update(['prom_type'=>2,'prom_id'=>$res]);
                    //日志记录
                    $this->write_log("预售活动添加",111);
                    $res=$res?1:0;
                    //改变商品的状态
                    break;
                case 'edit'://进行编辑
                    $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
                    if($stock['stock']==0){
                        return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
                    }
                    //编辑操作
                    $id=$row['act_id'];
                    $res=model("GoodsActivity")->where(['act_id'=>$id])->update($insertData);
                    //日志记录
                    $this->write_log("预售活动编辑",$id);
                    //修改商品的信息
                    model("Goods")->where(['goods_id'=>$row['goods_id']])->update(['prom_type'=>2,'prom_id'=>$id]);
                    break;
            }
            return AjaxReturn($res,getErrorInfo($res));
        }
    }
    //搜索商品
    function search_goods(){
        $where['status']=0;
        $where['prom_type']=0;
        if(input('keywords')){
            $where['goods_name']=['like','%'.input("keywords").'%'];
        }
        $goodsList=model("Goods")
            ->where($where)
            ->field("goods_id,goods_name,price,stock")
            ->order('goods_id desc')
            ->paginate(10);
        $this->assign('goodsList',$goodsList);

        return $this->fetch();
    }
    //判断活动状态信息
    function getPreStatus($goodItem){
        //dump($goodItem['start_time']."__".$goodItem['end_time']);exit;
        switch($goodItem['is_finished']){
            case 0:
                if($goodItem['start_time'] > time()){
                    $activityStatus = '未开始';
                }else if($goodItem['start_time'] <= time() && $goodItem['end_time'] > time()){
                    $activityStatus = '预售中';
                }else{
                    $activityStatus = '结束未处理';
                }
                break;
            case 1:
                $activityStatus='成功结束';break;
            case 2:
                $activityStatus='失败结束';break;
            default:
                $activityStatus='';break;
        }
        return $activityStatus;
    }
}