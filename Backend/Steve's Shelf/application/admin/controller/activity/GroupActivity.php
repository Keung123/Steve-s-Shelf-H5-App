<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/3/28 9:49
 */
namespace app\admin\controller\activity;

use app\common\service\GroupGoods;
use app\common\service\Bargain;
use app\common\service\GoodsCategory;
use app\common\service\Goods as GoodsService;
use think\Db;


class GroupActivity extends Base
{
    /**
     * 团购商品列表
     */
    public function groupgou()
    {	$goods_name = trim(input('goods_name'));
        $this->assign('goods_name',$goods_name);
        if(request()->isAjax()){

            if(input('get.search')){
                $where['goods_name']=['like','%'.input('get.search').'%'];
            }
            if(input('goods_name')){
                $where['goods_name']=['like','%'.input('goods_name').'%'];
            }
            $Groupgoodsmodel = new GroupGoods();

            $limit=input('get.offset').",".input('get.limit');
            $total = $Groupgoodsmodel->count($where);
            $order = 'id desc';
            $list = $Groupgoodsmodel->getList($where,'*',$order,$limit);
            if ($list) {
                $status_list = array(0=>'正常',1=>'结束');
                foreach ($list as &$val) {
                    $goodsinfo = $Groupgoodsmodel->getGoodsImg($val['goods_id']);
                    $val['picture'] = $goodsinfo['picture'];
                    $val['price'] = $goodsinfo['price'];
                    $val['is_end'] = $status_list[$val['is_end']];
                    $val['time'] = date('Y-m-d H:i:s',$val['start_time']).'  -- '.date('Y-m-d H:i:s',$val['end_time']);
                }
            }
            return json(['total'=>$total,'rows'=>$list]);
        }else{
            return $this->fetch();
        }
    }
    /**
     * 团购商品添加
     */
    public function groupgouadd()
    {
        $Groupgoodsmodel = new GroupGoods();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $row['start_time'] = strtotime( $row['start_time']);
            $row['end_time'] = strtotime( $row['end_time']);
            if($row['price_reduce']<=0){
                return (['code'=>0,'msg'=>'折扣（减价）必须大于0','data'=>'折扣（减价）必须大于0']);
            }
            if($row['goods_number']<=0){
                return (['code'=>0,'msg'=>'商品总数必须大于0','data'=>'商品总数必须大于0']);
            }
            if($row['start_time']>=$row['end_time']){
                return (['code'=>0,'msg'=>'开始时间必须小于结束时间','data'=>'开始时间必须大于结束时间']);
            }
            $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
            if($stock['stock']==0){
                return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
            }
            if($stock['stock']<$row['goods_number']){
                return (['code'=>0,'msg'=>'此商品添加数量不能大于库存,不能添加到活动商品','data'=>'此商品添加数量不能大于库存,不能添加到活动商品']);
            }
            //0:减价 1：折扣
            if($row['price_type'] == 1){
                if($row['price_reduce'] == 0){
                    $row['group_price'] = $row['price'];
                }else{
                    $row['group_price'] = ($row['price'] * $row['price_reduce']/100);
                }

            }else{
                $row['group_price'] = abs($row['price'] - $row['price_reduce']);
            }

            unset($row['price']);//去掉多余的字段

            //查询商品信息
            $GoodsService = new GoodsService();
            $result = $GoodsService->checkGoods($row['goods_id']);
            if($result){
                return (['code'=>0,'msg'=>'此商品已经添加为活动商品','data'=>'此商品已经添加为活动商品']);
            }

            $res=$Groupgoodsmodel->add($row);

            //添加日志记录
            $id=db('group_goods')->getLastInsID();
            $this->write_log('团购商品添加',$id);

            //1:团购; 更新商品表
            $result = $GoodsService->updateGoods($row['goods_id'],$prom_type=1,$id);

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
     * 团购商品修改
     */
    public function groupgouedit()
    {
        $Groupgoodsmodel = new GroupGoods();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $id = input('post.id');
            $row['start_time'] = strtotime( $row['start_time']);
            $row['end_time'] = strtotime( $row['end_time']);
            if($row['price_reduce']<=0){
                return (['code'=>0,'msg'=>'折扣（减价）必须大于0','data'=>'折扣（减价）必须大于0']);
            }
            if($row['goods_number']<=0){
                return (['code'=>0,'msg'=>'商品总数必须大于0','data'=>'商品总数必须大于0']);
            }
            if($row['start_time']>=$row['end_time']){
                return (['code'=>0,'msg'=>'开始时间必须小于结束时间','data'=>'开始时间必须大于结束时间']);
            }
            $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
            if($stock['stock']==0){
                return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
            }
            if($stock['stock']<$row['goods_number']){
                return (['code'=>0,'msg'=>'此商品添加数量不能大于库存,不能添加到活动商品','data'=>'此商品添加数量不能大于库存,不能添加到活动商品']);
            }
            //0:减价 1：折扣
            if($row['price_type'] == 1){
                if($row['price_reduce'] == 0){
                    $row['group_price'] = $row['price'];
                }else{
                    $row['group_price'] = ($row['price'] * $row['price_reduce']/100);
                }

            }else{
                $row['group_price'] = abs($row['price'] - $row['price_reduce']);
            }

            $GoodsService = new GoodsService();
            //商品是否改变  更新商品表
            $data = $Groupgoodsmodel->find(array('id' => $id));
            if($row['goods_id'] != $data['goods_id']){
                $result = $GoodsService->checkGoods($row['goods_id']);
                if($result){
                    return (['code'=>0,'msg'=>'此商品已经添加为活动商品','data'=>'']);
                }
                $GoodsService->updateGoods($data['goods_id']);
                //1:团购;  更新商品表
                $result = $GoodsService->updateGoods($row['goods_id'],$prom_type=1,$id);
                if(!$result){
                    return AjaxReturn($result,getErrorInfo($result));
                }
            }

            unset($row['price']);//去掉多余的字段
            $res=$Groupgoodsmodel->save(array('id' => $id),$row);

            //添加日志记录
            $this->write_log('团购商品修改',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $info = $Groupgoodsmodel->find($map);
            $goods_model = new GoodsService();
            $goodsinfo =$goods_model->find(array('goods_id' => $info['goods_id']));
            $info['goryid'] = $goodsinfo['category_id'];
            $info['goods_price'] = $goodsinfo['price'];
            $info['stock'] = $goodsinfo['stock'];
            $this->assign('row',$info);
            // 获取该分类下的所有商品
            $goods_list = $goods_model->select(array('category_id' => $info['goryid']));
            $this->assign('goods_list',$goods_list);
            //获取一级分类
            $id=$info['goryid'];
            $category_id=db('goods_category')->field('pid')->where(['category_id'=>$id])->find();
            $this->assign('category_id',$category_id);
            //获取二级分类
            $category=db('goods_category')->where('pid',$category_id['pid'])->select();
            $this->assign('allcategory',$category);
            //获取分类列表
            $rows=db('goods_category')->where('pid',0)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            return $this->fetch();
        }

    }
    /**
     * 团购商品删除
     */
    public function groupgoudelete()
    {
        $ids=input('get.ids');
        $Groupgoodsmodel = new GroupGoods();
        $where = array();
        $where['id'] = array('in', $ids);

        //更新商品表
        $GoodsService = new GoodsService();

        $goods_id = Db::name('group_goods')->where($where)->column('goods_id');
        $goods_str = implode(',',$goods_id);
        $map = array('in', $goods_str);

        $res = $GoodsService->updateGoods($map);

        //判断此商品是否在活动中
        $res=new Bargain();
        $status=$res->judgems($ids,'group_goods','is_end');
        if($status==1){
            return $this->error('此操作包含活动中商品不能删除');
            exit;
        }

        $res=$Groupgoodsmodel->delete($where);

        //添加日志记录
        $this->write_log('团购商品删除',$ids);

        return AjaxReturn($res);

    }
}