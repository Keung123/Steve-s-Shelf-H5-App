<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/3/28 9:43
 */
namespace app\admin\controller\activity;

use app\common\service\Goods as GoodsService;
use app\common\service\TeamActivity;
use app\common\service\Bargain;
use think\Db;


class TeamspellActivity extends Base
{
    /**
     * 拼团活动商品
     */
    public function teamSpell()
    {
        $goods_name = trim(input('goods_name'));
        $this->assign('goods_name',$goods_name);
        if(request()->isAjax()){
            if(input('get.search')){
                $where['goods_name']=['like','%'.input('get.search').'%'];
            }
            if(input('goods_name')){
                $where['goods_name']=['like','%'.input('goods_name').'%'];
            }
            $Teamactivitymmodel = new TeamActivity();
            $total = $Teamactivitymmodel->count($where);
            $list = $Teamactivitymmodel->getList($where);
            if ($list) {
                $status_list = array(0=>'正常',1=>'结束');
                foreach ($list as &$val) {
                    $goodsinfo = $Teamactivitymmodel->getGoodsImg($val['goods_id']);
                    $val['picture'] = $goodsinfo['picture'];
                    if ($val['sku_id']){
                        $pricess = Db::name('goods_sku')->where('sku_id',$val['sku_id'])->value('price');
                        $val['price'] = $pricess;
                    }else{
                        $val['price'] = $goodsinfo['price'];
                    }
                    $val['status'] = $status_list[$val['status']];
                }
            }
            return json(['total'=>$total,'rows'=>$list]);
        }else{
            return $this->fetch();
        }
    }
    /**
     * 拼团活动商品添加
     */
    public function teamspellAdd()
    {
        $Teamactivitymmodel = new TeamActivity();
        if(request()->isAjax()){
            $row=input('post.row/a');
            //0:减价 1：折扣
            if($row['price_type'] == 1){
                if($row['price_reduce'] == 0){
                    $row['team_price'] = $result['price'];
                }
                $row['team_price'] = ($row['price'] * $row['price_reduce']/100);
            }else{
                $row['team_price'] = ($row['price'] - $row['price_reduce']);
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
            if($stock['stock']<$row['goods_number']){
                return (['code'=>0,'msg'=>'此商品添加数量不能大于库存,不能添加到活动商品','data'=>'此商品添加数量不能大于库存,不能添加到活动商品']);
            }
            unset($row['price']);//去掉多余的字段
            $res=$Teamactivitymmodel->add($row);

            //添加日志记录
            $id=db('team_activity')->getLastInsID();
            $this->write_log('拼团活动商品添加',$id);

            // 3:拼团;  更新商品表
            // $prom_id = Db::name('team_activity')->getLastInsID();//获取最新id
            $result = $GoodsService->updateGoods($row['goods_id'],$prom_type=3,$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            //获取分类列表
            $rows=db('goods_category')->where('pid',0)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);

            return $this->fetch();
        }
    }
    /**
     * 拼团活动商品修改
     */
    public function teamspellEdit()
    {
        $Teamactivitymmodel = new TeamActivity();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $id = input('post.id');

            //0:减价 1：折扣
            if($row['price_type'] == 1){
                if($row['price_reduce'] == 0){
                    $row['team_price'] = $row['price'];
                }else{
                    $row['team_price'] = ($row['price'] * $row['price_reduce']/100);
                }

            }else{
                $row['team_price'] = abs($row['price'] - $row['price_reduce']);
            }
            if($row['goods_number']<=0){
                return (['code'=>0,'msg'=>'商品总数必须大于0','data'=>'商品总数必须大于0']);
            }
            $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
            if($stock['stock']==0){
                return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
            }
            if($stock['stock']<$row['goods_number']){
                return (['code'=>0,'msg'=>'此商品添加数量不能大于库存,不能添加到活动商品','data'=>'此商品添加数量不能大于库存,不能添加到活动商品']);
            }
            //商品是否改变  更新商品表
            $GoodsService = new GoodsService();
            $data = $Teamactivitymmodel->find(array('id' => $id));
            if($row['goods_id'] != $data['goods_id']){
                $result = $GoodsService->checkGoods($row['goods_id']);
                if($result){
                    return (['code'=>0,'msg'=>'此商品已经添加为活动商品','data'=>'此商品已经添加为活动商品']);
                }
                $GoodsService->updateGoods($data['goods_id']);
                if(!$result){
                    return AjaxReturn($result,getErrorInfo($result));
                }
            }

            unset($row['price']);//去掉多余的字段
            $res=$Teamactivitymmodel->save(array('id' => $id),$row);
            $result = $GoodsService->updateGoods($row['goods_id'],$prom_type=3,$id);

            //添加日志记录
            $this->write_log('拼团活动商品修改',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $info = $Teamactivitymmodel->find($map);
            $goods_model = new GoodsService();
            $goodsinfo =$goods_model->find(array('goods_id' => $info['goods_id']));
            $info['goryid'] = $goodsinfo['category_id'];
            //获取sku价格
            $pricess = Db::name('goods_sku')->where('sku_id',$info['sku_id'])->value('price');
            //
            if ($pricess){
                $info['goods_price'] = $pricess;
            }else{
                $info['goods_price'] = $goodsinfo['price'];
            }
           // $info['goods_price'] = $goodsinfo['price'];
            $info['stock'] = $goodsinfo['stock'];
            $this->assign('row',$info);
            // 获取该分类下的所有商品
            $goods_list = $goods_model->select(array('category_id' => $info['goryid']));
            $this->assign('goods_list',$goods_list);
            //获取当前查看商品的sku
            $skus =Db::name('goods_sku')->where('goods_id',$info['goods_id'])->field('sku_id,sku_name,price')->select();
            $this->assign('skus',$skus);
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
     * 拼团商品删除
     */
    public function teamspellDel()
    {
        $ids=input('get.ids');
        $Teamactivitymmodel = new TeamActivity();
        $where = array();
        $where['id'] = array('in', $ids);
        //更新商品表
        $GoodsService = new GoodsService();

        $goods_id = Db::name('team_activity')->where($where)->column('goods_id');
        $goods_str = implode(',',$goods_id);
        $map = array('in',$goods_str);
        $res = $GoodsService->updateGoods($map);

        //判断此商品是否在活动中
        $res= new Bargain();
        $status=$res->judgems($ids,'team_activity','status');
        if($status==1){
            return $this->error('此操作包含活动中商品不能删除');
            exit;
        }


        $res=$Teamactivitymmodel->delete($where);

        //添加日志记录
        $this->write_log('拼团活动商品删除',$ids);

        return AjaxReturn($res);

    }
}