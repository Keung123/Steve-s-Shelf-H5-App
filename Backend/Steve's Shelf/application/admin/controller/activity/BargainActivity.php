<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/3/28 9:55
 */

namespace app\admin\controller\activity;

use app\common\service\Bargain;
use app\common\service\GoodsCategory;
use app\common\service\Goods as GoodsService;


class BargainActivity extends Base
{
    /**
     * 砍价列表
     * bargain
     */
    public function kanjia(){
        $goods_name = trim(input('goods_name'));
        $this->assign('goods_name', $goods_name);
        if(request()->isAjax()){
            //排序
            $order=input('get.sort')." ".input('get.order');

            if(input('get.search')){
                $where['goods_name']=['like','%'.input('get.search').'%'];
            }
            if(input('goods_name')){
                $where['goods_name']=['like','%'.input('goods_name').'%'];
            }

            $bargainmodel = new Bargain();
            $total = $bargainmodel->count($where);
            $list = $bargainmodel->getList($where);
            if ($list) {
                $status_list = array(0=>'正常',1=>'结束');
                foreach ($list as &$val) {
                    $val['picture'] = $bargainmodel->getGoodsImg($val['goods_id']);
                    $val['status'] = $status_list[$val['status']];
                }
            }
            $goods_model = new GoodsService();
            /* foreach($list as $key=>$val){
                $sku = $goods_model->getGui($val['goods_id']);
                $list[$key]['list'] = $sku;
            } */
            return json(['total'=>$total,'rows'=>$list]);
        }else{
            return $this->fetch();
        }
    }
    /**
     * 砍价商品添加
     */
    public function kanjiaadd(){

        $bargainmodel = new Bargain();
        if(request()->isAjax()){

            $row=input('post.row/a');
            if($row['join_number']<=0){
                return (['code'=>0,'msg'=>'砍完人数必须大于0','data'=>'砍完人数必须大于0']);
            }
            $GoodsService = new GoodsService();
            $result = $GoodsService->checkGoods($row['goods_id']);
            if($result){
                return (['code'=>0,'msg'=>'此商品已经添加为活动商品','data'=>'此商品已经添加为活动商品']);
            }
            $stock=db('goods_sku')->where('goods_id',$row['goods_id'])->where('sku_id',$row['sku_id'])->field('stock')->find();
            if($row['goods_number']>$stock['stock'])
            {
                return (['code'=>0,'msg'=>'活动库存大于商品库存','data'=>'活动库存大于商品库存']);
            }
            if($stock['stock']==0){
                return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
            }
            if($row['end_price']<=0){
                return (['code'=>0,'msg'=>'商品价格不能低于0元','data'=>'商品价格不能低于0元']);
            }
            //随机砍价
            $price = ($row['goods_price'] -  $row['end_price']);
            $join_number = $row['join_number'];
            $bargain_price = $this->randMoney($price,$join_number);
            $row['bargain_price'] =  json_encode($bargain_price);


            $res=$bargainmodel->add($row);

            //添加日志记录
            $id=db('bargain')->getLastInsID();
            $this->write_log('砍价商品添加',$id);

            //4:砍价;更新商品的 数据
            // $prom_id = Db::name('bargain')->getLastInsID();
            //获取最新id
            $result = $GoodsService->updateGoods($row['goods_id'],$prom_type=4,$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            //获取分类列表
            $GoodsCategory=new GoodsCategory();
            $map['pid']=0;
            $rows=$GoodsCategory->select($map);
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);

            return $this->fetch();
        }
    }
    /**
     *  砍价随机数
     */
    function randMoney($sum,$count){
        $arr = [];
        $hes = 0;
        $hess =0;
        for ($i=0;$i<$count;$i++){
            $rand =rand(1,1000);
            $arr[]=$rand;
            $hes+=$rand;
        }
        $arr2 =[];
        foreach ($arr as $key=>$value){
            $round = round(($value/$hes)*$sum,2);
            $arr2[] =$round;
            $hess+=$round;
        }
        if($sum !=round($hess,2)){
            $hesss =round($sum-$hess,2);
            $arr2[0]=$arr2[0]+$hesss;
        }
        return $arr2;
    }
    /**
     * 砍价商品修改
     */
    public function kanjiaedit () {

        $bargainmodel = new Bargain();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $id = input('post.id');
            if($row['join_number']<=0){
                return (['code'=>0,'msg'=>'砍完人数必须大于0','data'=>'砍完人数必须大于0']);
            }
            $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
            if($row['goods_number']>$stock['stock'])
            {
                return (['code'=>0,'msg'=>'活动库存大于商品库存','data'=>'活动库存大于商品库存']);
            }
            if($stock['stock']==0){
                return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
            }
            if($row['end_price']<=0){
                return (['code'=>0,'msg'=>'商品价格不能低于0元','data'=>'商品价格不能低于0元']);
            }
            //商品是否改变  更新商品表
            $GoodsService = new GoodsService();
            $data = $bargainmodel->find(array('id' => $id));
            if($row['goods_id'] != $data['goods_id']){
                $result = $GoodsService->checkGoods($row['goods_id']);
                if($result){
                    return (['code'=>0,'msg'=>'此商品已经添加为活动商品','data'=>'此商品已经添加为活动商品']);
                }
                $GoodsService->updateGoods($data['goods_id']);

                //添加日志记录
                $this->write_log('砍价商品修改',$data['goods_id']);

                //4:砍价;  更新商品表
                $result = $GoodsService->updateGoods($row['goods_id'],$prom_type=4,$id);
                if(!$result){
                    return AjaxReturn($result,getErrorInfo($result));
                }
            }
            //随机砍价
            $price = ($row['goods_price'] -  $row['end_price']);
            $join_number = $row['join_number'];
            $bargain_price = $this->randMoney($price,$join_number);
            $row['bargain_price'] =  json_encode($bargain_price);

            $res=$bargainmodel->save(array('id' => $id),$row);
            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $info = $bargainmodel->find($map)->toArray();
            $goods_model = new GoodsService();
            $goodsinfo =$goods_model->find(array('goods_id' => $info['goods_id']));
            $info['goryid'] = $goodsinfo['category_id'];
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
            $category=db('goods_category')->select();
            $this->assign('allcategory',$category);
            //获取分类列表
            $rows=db('goods_category')->where('pid',0)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            //商品规格
            $map = array('goods_id' => $info['goods_id']);
            $sku = $goods_model->getGui($info['goods_id']);
            $this->assign('sku',$sku);
            $this->assign('category',$rows);

            return $this->fetch();
        }
    }

    /**
     * 删除 砍价商品
     */
    public function kanjiadelete()
    {
        $ids=input('get.ids');
        $bargainmodel = new Bargain();
        $where = array();
        $where['id'] = array('in', $ids);
        //更新商品表
        $GoodsService = new GoodsService();
        $row = $bargainmodel->find($where);
        $res = $GoodsService->updateGoods($row['goods_id']);

        $res=$bargainmodel->delete($where);

        //添加日志记录
        $this->write_log('砍价商品修改',$ids);

        return AjaxReturn($res);
    }
}