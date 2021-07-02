<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/3/28 9:39
 */
namespace app\admin\controller\activity;

use app\common\service\FullGoods;
use app\common\service\Bargain;
use app\common\service\Goods as GoodsService;
use think\Db;

class FullActivity extends Base
{
    /**
     * 满199减100商品列表
     */
    public function fullgoods()
    {

        $act_type = input('act_type');
        $goods_name = trim(input('goods_name'));
        $this->assign('goods_name',$goods_name);
        if(request()->isAjax()){
            if(input('get.search')){
                $where['goods_name']=['like','%'.input('get.search').'%'];
            }
            if(input('goods_name')){
                $where['goods_name']=['like','%'.input('goods_name').'%'];
            }
            if($act_type){
                $where ['act_type'] = ['eq', $act_type];
            }
            $FullGoodsModel = new FullGoods();
            $total = $FullGoodsModel->count($where);
            $list = $FullGoodsModel->getList($where);
            if ($list) {
                $status_list = array(0=>'正常',1=>'结束');
                foreach ($list as & $val) {
                    $goodsinfo = $FullGoodsModel->getGoodsImg($val['goods_id']);
                    $val['picture'] = $goodsinfo['picture'];
                    $val['price'] = $goodsinfo['price'];
                    $val['is_end'] = $status_list[$val['is_end']];
                }
            }
            return json(['total'=>$total,'rows'=>$list]);
        }else{
            $this->assign('act_type',$act_type);
            return $this->fetch();
        }

    }
    /*
    *
    *  活动id6，7，8商品添加
    */
    public function fullgoodsadd()
    {
        $FullGoodsModel = new FullGoods();
        $act_type = input('act_type');
        if(request()->isAjax()){
            $row=input('post.row/a');
            if(!$row ['act_type']){
                $row['act_type'] = '6';//满减活动id
            }
            if($row['goods_number']<=0){
                return (['code'=>0,'msg'=>'商品总数必须大于0','data'=>'商品总数必须大于0']);
            }
            $GoodsService = new GoodsService();
            $result = $GoodsService->checkGoods($row['goods_id']);
            if($result){
                return (['code'=>0,'msg'=>'此商品已经添加为活动商品','data'=>'此商品已经添加为活动商品']);
            }
            $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
            if($stock['stock']==0){
                return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
            }
            if($row['goods_number']>$stock['stock']){
                return (['code'=>0,'msg'=>'此商品总数不能大于库存,不能添加到活动商品','data'=>'此商品总数不能大于库存,不能添加到活动商品']);
            }
            unset($row['price']);//去掉多余的字段
            $res=$FullGoodsModel->add($row);

            //添加日志记录
            $id=db('full_goods')->getLastInsID();
            $this->write_log('活动id6，7，8商品添加',$id);

            // 6:满199减100; 7:99元3件;8:满2件打九折; 更新商品表
            // $prom_id = Db::name('full_goods')->getLastInsID();//获取最新id
            $result = $GoodsService->updateGoods($row['goods_id'],$prom_type=$row['act_type'],$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            //获取分类列表
            $rows=db('goods_category')->where('pid',0)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            $this->assign('act_type',$act_type);
            return $this->fetch();
        }
    }
    /**
     * 活动id6，7，8商品修改
     */
    public function fullgoodsedit()
    {
        $FullGoodsModel = new FullGoods();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $id = input('post.id');
            if($row['goods_number']<=0){
                return (['code'=>0,'msg'=>'商品总数必须大于0','data'=>'商品总数必须大于0']);
            }
            $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
            if($stock['stock']==0){
                return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
            }
            if($row['goods_number']>$stock['stock']){
                return (['code'=>0,'msg'=>'此商品总数不能大于库存,不能添加到活动商品','data'=>'此商品总数不能大于库存,不能添加到活动商品']);
            }
            //商品是否改变  更新商品表
            $GoodsService = new GoodsService();
            $data = $FullGoodsModel->find(array('id' => $id));
            if($row['goods_id'] != $data['goods_id']){
                $result = $GoodsService->checkGoods($row['goods_id']);
                if($result){
                    return (['code'=>0,'msg'=>'此商品已经添加为活动商品','data'=>'此商品已经添加为活动商品']);
                }
                $GoodsService->updateGoods($data['goods_id']);
                // 6:满199减100; 7:99元3件;8:满2件打九折; 更新商品表
                $where['id'] = array('in', $id);
                $data = $FullGoodsModel->find($where);
                $result = $GoodsService->updateGoods($row['goods_id'],$prom_type=$data['act_type'],$id);
                if(!$result){
                    return AjaxReturn($result,getErrorInfo($result));
                }
            }

            $res=$FullGoodsModel->save(array('id' => $id),$row);

            //添加日志记录
            $this->write_log('活动id6，7，8商品修改',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $info = $FullGoodsModel->find($map);
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
     * 活动id6，7，8商品删除
     */
    public function fullgoodsDel()
    {
        $ids=input('get.ids');
        $FullGoodsModel = new FullGoods();
        $where = array();
        $where['id'] = array('in', $ids);
        //更新商品表
        $GoodsService = new GoodsService();

        $goods_id = Db::name('full_goods')->where($where)->column('goods_id');
        $goods_str = implode(',',$goods_id);
        $map = array('in',$goods_str);
        $res = $GoodsService->updateGoods($map);

        //判断此商品是否在活动中
        $bargain=new Bargain();
        $status=$bargain->judgems($ids,'full_goods','is_end');
        if($status==1){
            return $this->error('此操作包含活动进行中商品不能删除');
            exit;
        }

        $res=$FullGoodsModel->delete($where);

        //添加日志记录
        $this->write_log('活动id6，7，8商品删除',$ids);

        return AjaxReturn($res);

    }
}