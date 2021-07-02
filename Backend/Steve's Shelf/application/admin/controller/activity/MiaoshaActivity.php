<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/3/28 9:57
 */
namespace app\admin\controller\activity;

use app\common\service\FlashActive;
use app\common\service\FlashGoods;
use app\common\service\GoodsCategory;
use app\common\service\Goods as GoodsService;
use think\Db;

class MiaoshaActivity extends Base
{
    /**
     * 秒杀设置时段
     */
    public function miaoshatimeset()
    {
        if(request()->isAjax()){

            $FlashActivemodel = new FlashActive();
            $total = $FlashActivemodel->count();
            $limit=input('get.offset').",".input('get.limit');
            $list = $FlashActivemodel->select('','*','',$limit);
            if ($list) {
                $status_list = array(0=>'正常',1=>'结束');
                foreach ($list as &$val) {
                    $val['status'] = $status_list[$val['status']];
                    $val['start_time'] = date('Y-m-d H:i', $val['start_time']);
                    $val['end_time'] = date('Y-m-d H:i', $val['end_time']);
                    $val['time'] =  $val['start_time'].' -- '.$val['end_time'];
                }
            }
            return json(['total'=>$total,'rows'=>$list]);
        }else{
            return $this->fetch();
        }
        return $this->fetch();
    }
    /**
     * 秒杀设置时段修改
     */
    public function miaoshatimeedit()
    {
        if(request()->isAjax()){
            $row = input('post.row/a');
            $id = input('post.id');
            $row['start_time'] = strtotime($row['start_time']);
            $row['end_time'] = strtotime($row['end_time']);
            if($row['start_time']>=$row['end_time']){
                return (['code'=>0,'msg'=>'开始时间必须小于结束时间']);
            }
            $day1 = date('d', $row['start_time']);
            $day2 = date('d', $row['end_time']);
            if ($day1 != $day2) {
                return (['code'=>0,'msg'=>'开始和结束时间必须为同一天']);
            }
            $FlashActivemodel = new FlashActive();
            $res = $FlashActivemodel->save(array('id' => $id),$row);
            //日志记录
            $result = Db::name('flash_active')->order('id desc')->find();
            $add['uid'] = session('admin_id');
            $add['ip_address'] = request()->ip();
            $add['controller'] = request()->controller();
            $add['action'] = request()->action();
            $add['remarks'] = '秒杀设置时段修改';
            $add['number'] = $id;
            $add['create_at'] = time();
            db('web_log')->insert($add);

            return AjaxReturn($res,getErrorInfo($res));

        }else{
            $FlashActivemodel = new FlashActive();
            $id = input('get.ids');
            $row = $FlashActivemodel->find(['id'=>$id]);
            $row['start_time'] = date('Y-m-d H:i', $row['start_time']);
            $row['end_time'] = date('Y-m-d H:i', $row['end_time']);
            $this->assign('row', $row);
            return $this->fetch();
        }
    }
    /**
     * 秒杀设置时段添加
     */
    public function miaoshatimeadd()
    {
        $FlashActivemodel = new FlashActive();
        if(request()->isAjax()){
            $row = input('post.row/a');
            $row['start_time'] = strtotime($row['start_time']);
            $row['end_time'] = strtotime($row['end_time']);
            if($row['start_time'] >= $row['end_time']){
                return (['code'=>0,'msg'=>'开始时间必须小于结束时间','data'=>'开始时间必须大于结束时间']);
            }
            $day1 = date('d', $row['start_time']);
            $day2 = date('d', $row['end_time']);
            if ($day1 != $day2) {
                return (['code'=>0,'msg'=>'开始和结束时间必须为同一天']);
            }
            $res = $FlashActivemodel->add($row);
            //日志记录
            $result = Db::name('flash_active')->order('id desc')->find();
            $add['uid'] = session('admin_id');
            $add['ip_address'] = request()->ip();
            $add['controller'] = request()->controller();
            $add['action'] = request()->action();
            $add['remarks'] = '秒杀设置时段添加';
            $add['number'] = $result['id'];
            $add['create_at'] = time();
            db('web_log')->insert($add);
            return AjaxReturn($res,getErrorInfo($res));
        }else{

            return $this->fetch();
        }
    }
    /**
     * 秒杀设置时段删除
     */
    public function miaoshaTimeDel()
    {
        $ids=input('get.ids');
        $FlashActivemodel = new FlashActive();
        $where = array();
        $where['id'] = array('in', $ids);
        //多个id
        /*$flash_ids = explode(',',$ids);
         $res = $FlashActivemodel->getGoodsinfo($flash_ids);
        if(!$res){
            return (['code'=>0,'msg'=>'此秒杀时段下有商品!不能删除！','data'=>'']);
        }*/

        //删除所属商品
        $FlashGoodsService = new FlashGoods();
        $map['flash_id']=['in',$ids];
        $data=$FlashGoodsService->delete($map);
        if(!$data){
            return (['code'=>0,'msg'=>'此秒杀时段商品未成功删除！','data'=>'此秒杀时段商品未成功删除！']);
        }

        $res=$FlashActivemodel->delete($where);


        return AjaxReturn($res);
    }
    /**
     * 秒杀列表
     * flash_goods
     */
    public function miaosha(){

        $goods_name = trim(input('goods_name'));
        $start_time = input('start_time');
        $end_time = input('end_time');
        $this->assign('goods_name',$goods_name);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        if(request()->isAjax()){
            if(input('get.search')){
                $where['goods_name']=['like','%'.input('get.search').'%'];
            }
            if(input('goods_name')){
                $where['goods_name']=['like','%'.input('goods_name').'%'];
            }

            if(input('start_time')){
                $start_time = str_replace('+',' ',input('start_time'));
            }
            if(input('end_time')){
                $end_time = str_replace('+',' ',input('end_time'));
            }
            if($start_time && $end_time){
                $where['start_time'] = ['>=',strtotime($start_time)];
                $where['end_time'] = ['<=',strtotime($end_time)];
            }elseif ($start_time) {
                $where['start_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $where['end_time'] = array('<=', strtotime($end_time));
            }
            $Flashmodel = new FlashGoods();
            // $total = $Flashmodel->count();


            $limit=input('get.offset').",".input('get.limit');
            $list = $Flashmodel->getLists1($where,$limit);
            $total_num = Db::name('flash_active')->
            alias('a')->
            join('flash_goods b','a.id=b.flash_id')->
            join('goods c','b.goods_id=c.goods_id')->where(['c.prom_type'=>5])->
            select();
          
          	
            $total=count($total_num);

            if ($list) {
                $status_list = array(0=>'正常',1=>'结束');
                foreach ($list as  &$val) {
                    $goodsinfo = $Flashmodel->getGoodsImg($val['goods_id']);
                    $val['picture'] = $goodsinfo['picture'];
                    if ($val['sku_id']){
                        //获取sku价格
                        $pricess = Db::name('goods_sku')->where('sku_id',$val['sku_id'])->value('price');
                        $val['price'] = $pricess;
                    }else{
                        $val['price'] = $goodsinfo['price'];
                    }

                    $val['is_end'] = $status_list[$val['is_end']];
                    $active_info = $Flashmodel->getActiveInfo($val['flash_id']);
                    $val['time'] =  date('Y-m-d H:i:s',$active_info['start_time']).' -- '.date('Y-m-d H:i:s',$active_info['end_time']);

                }
            }
            return json(['total'=>$total,'rows'=>$list]);
        }else{

            return $this->fetch();
        }
    }
    /**
     * 秒杀商品添加
     */
    public function miaoshaadd () {

        $Flashmodel = new FlashGoods();
        if(request()->isAjax()){
            $row=input('post.row/a');
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
                return (['code'=>0,'msg'=>'此商品添加数量大于库存,不能添加到活动商品','data'=>'此商品添加数量大于库存,不能添加到活动商品']);
            }

            //0:减价 1：折扣

            if($row['price_type'] == 1){
                if($row['price_reduce'] == 0){
                    $row['limit_price'] = $row['price'];
                }else{
                    $row['limit_price'] = ($row['price'] * $row['price_reduce']/100);
                }

            }else{
                $row['limit_price'] = abs($row['price'] - $row['price_reduce']);
            }

            unset($row['price']);//去掉多余的字段
            $data=db('flash_goods')->where(['goods_id'=>$row['goods_id']])->find();
            if($data){
                $res=db('flash_goods')->where(['goods_id'=>$row['goods_id']])->update($row);
                //$id=db('flash_goods')->getLastInsID();
				$id = $data['id'];
                //db('goods')->where(['goods_id'=>$row['goods_id']])->update(['prom_type' => 5]);
            }else{
                $res=$Flashmodel->add($row);
                $id=db('flash_goods')->getLastInsID();

            }

            //添加日志记录

            $this->write_log('秒杀商品添加',$id);

            //5:抢购/秒杀;  更新商品的 数据
//			$prom_id = Db::name('flash_goods')->getLastInsID();//获取最新id
            $result = $GoodsService->updateGoods($row['goods_id'],5,$id);


            return AjaxReturn($res,getErrorInfo($res));
        }else{
            //获取分类列表
            $GoodsCategory=new GoodsCategory();
            $rows=db('goods_category')->where('pid',0)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            $active_hour_list = $Flashmodel->getActiveList();
            $this->assign('active_hour_list', $active_hour_list);
            return $this->fetch();
        }
    }
    /**
     * 秒杀商品修改
     */
    public function miaoshaedit () {

        $Flashmodel = new FlashGoods();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $id = input('post.id');
            //商品是否改变  更新商品表
            if($row['goods_number']<=0){
                return (['code'=>0,'msg'=>'商品总数必须大于0','data'=>'商品总数必须大于0']);
            }
            $stock=db('goods')->where('goods_id',$row['goods_id'])->field('stock')->find();
            if($stock['stock']==0){
                return (['code'=>0,'msg'=>'此商品库存为0,不能添加到活动商品','data'=>'此商品库存为0,不能添加到活动商品']);
            }
            if($stock['stock']<$row['goods_number']){
                return (['code'=>0,'msg'=>'此商品添加数量大于库存,不能添加到活动商品','data'=>'此商品添加数量大于库存,不能添加到活动商品']);
            }
            $GoodsService = new GoodsService();
            $data = $Flashmodel->find(array('id' => $id));
            if($row['goods_id'] != $data['goods_id']){
                $result = $GoodsService->checkGoods($row['goods_id']);
                if($result){
                    return (['code'=>0,'msg'=>'此商品已经添加为活动商品','data'=>'此商品已经添加为活动商品']);
                }
                //$GoodsService->updateGoods($data['goods_id']);
                // 5:抢购/秒杀;  更新商品表
                $result = $GoodsService->updateGoods($row['goods_id'],5,$id);
                if(!$result){
                    return AjaxReturn($result,getErrorInfo($result));
                }
            }
            //0:减价 1：折扣
            if($row['price_type'] == 1){
                if($row['price_reduce'] == 0){
                    $row['limit_price'] = $row['price'];
                }else{
                    $row['limit_price'] = ($row['price'] * $row['price_reduce']/100);
                }

            }else{
                $row['limit_price'] = abs($row['price'] - $row['price_reduce']);
            }
            unset($row['price']);//去掉多余的字段
            $res=$Flashmodel->save(array('id' => $id),$row);

            //添加日志记录
            $this->write_log('秒杀商品修改',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $info = $Flashmodel->find($map);
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

            $info['stock']=$goodsinfo['stock'];
            $this->assign('row',$info);
            // 获取该分类下的所有商品
            $goods_list = $goods_model->select(array('category_id' => $info['goryid']));
            $this->assign('goods_list',$goods_list);
            //获取当前查看商品的sku
            $skus =Db::name('goods_sku')->where('goods_id',$info['goods_id'])->field('sku_id,sku_name,price')->select();
            $this->assign('skus',$skus);
            //获取分类列表
            $GoodsCategory=new GoodsCategory();
            $id['pid']=0;
            $rows=$GoodsCategory->select($id);
            //获取一级分类
            $id=$info['goryid'];
            $category_id=db('goods_category')->field('pid')->where(['category_id'=>$id])->find();
            $this->assign('category_id',$category_id);
            //获取二级分类
            $category=db('goods_category')->select();
            $this->assign('allcategory',$category);
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            $active_hour_list = $Flashmodel->getActiveList();
            $this->assign('active_hour_list', $active_hour_list);
            return $this->fetch();
        }
    }
    /**
     * 删除 秒杀商品
     */
    public function miaoshadelete()
    {
        $ids=input('get.ids');
        $Flashmodel = new FlashGoods();
        $where = array();
        $where['id'] = array('in', $ids);
        $res = $Flashmodel->judgems($ids);
        if($res){
            return (['code'=>0,'msg'=>'活动未结束不可删除','data'=>'']);
        }
        //更新商品表
        $GoodsService = new GoodsService();
        $row = $Flashmodel->find($where);
        $res = $GoodsService->updateGoods($row['goods_id']);

        //添加日志记录
        $this->write_log('删除秒杀商品',$row['goods_id']);

        // $res=$Flashmodel->delete($where);
        return AjaxReturn($res);
    }
}