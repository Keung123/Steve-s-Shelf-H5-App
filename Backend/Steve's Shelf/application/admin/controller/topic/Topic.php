<?php
namespace app\admin\controller\topic;

use app\common\service\MaterialCategory;
use app\common\service\GoodsCategory;
use app\common\service\Goods as GoodsService;
use app\common\service\Store as StoreService;
use think\Db;
use app\admin\controller\Base;
class Topic extends Base{

    /*
     * 话题 列表
     */
	public function index(){
	    $tp_name=trim(input('tp_name'));
	    $tp_status=trim(input('tp_status'));
	    $type=trim(input('type'));
	    $this->assign('tp_name',$tp_name);
	    $this->assign('tp_status',$tp_status);
	    $this->assign('type',$type);

		if(request()->isAjax()){
			$sort = 'tp_id desc';
			$limit = input('get.offset').",".input('get.limit');
			$where = [];
			if(input('tp_name')){
			    $where['tp_title']=['like','%'.$tp_name.'%'];
            }
            $where['tp_status'] = ['neq',2];
			$where['tp_type']=['eq',input('type')];
            $TopicService= new \app\common\service\Topic();
			$total = $TopicService->count($where);
			$rows = $TopicService->select($where,'*',$sort,$limit);
			foreach($rows as $val){
                $val['tp_status']=$val['tp_status']==0?'正常':'关闭';
                $val['tp_addtime']=date('Y-m-d H:i:s',$val['tp_addtime']);
            }
			return json(['total'=>$total,'rows'=>$rows]);
		}else{			 
			return $this->fetch();
		} 
	}
	/*
	 * 话题添加
	 */
    public function add()
    {
		$type=trim(input('type'));
        if(request()->isAjax()){
            $row=input('post.row/a');
            if($row){
                $row['tp_addtime']=time();
            }
			if(!$row['tp_user_id']){
				$row['tp_user_id'] = 1;
			}
            $TopicService= new \app\common\service\Topic();
            $res=$TopicService->add($row);
            //添加日志记录
            $id=db('topic')->getLastInsID();
            $this->write_log('话题添加',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
			//获取店主
			$store_list =  $this->getStory();
			$this->assign('storeList',$store_list);
			 //获取分类列表
            $GoodsCategory=new GoodsCategory();
            $rows=db('goods_category')->where('pid',0)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('goodCategory',$rows);
			$category = $this->getCategory();
			$this->assign('category',$category);
			$this->assign('type',$type);
            return $this->fetch();
        }
    }
    /*
	 * 修改 话题
	 */
    public function edit()
    {
        $TopicService=new \app\common\service\Topic();
		$type=trim(input('type'));
        if(request()->isAjax()){
            $row=input('post.row/a');
            $map['tp_id']=input('post.tp_id');
			if(!$row['tp_user_id']){
				$row['tp_user_id'] = 1;
			}
            $res=$TopicService->save($map,$row);

            //添加日志记录
            $id=db('topic')->getLastInsID();
            $this->write_log('修改话题',$map['tp_id']);
            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['tp_id']=input('get.ids');
            $row=$TopicService->find($map);
			$row['user_name'] = '';	
			$row['user_id'] = '';	
			$row['user_mobile'] = '';	
			if($row['tp_user_id']){
				$user_info = Db::name('users')->where('user_id',$row['tp_user_id'])->field('user_name,user_id,user_mobile')->find();
				if($user_info){
					$row['user_name'] = $user_info['user_name'];	
					$row['user_id'] = $user_info['user_id'];	
					$row['user_mobile'] = $user_info['user_mobile'];	
				}
			}
			 
            $this->assign('rows',$row);
			$category = $this->getCategory();
			$this->assign('category',$category);
			//获取店主
			$store_list =  $this->getStory();
			$this->assign('storeList',$store_list);
			
			$goods_model = new GoodsService();
            $goodsinfo =$goods_model->find(array('goods_id' => $row['tp_goods_id']));
            $info['goryid'] = $goodsinfo['category_id'];
            $info['goods_price'] = $goodsinfo['price'];
            $info['stock']=$goodsinfo['stock'];
            $this->assign('row',$info);
            // 获取该分类下的所有商品
            $goods_list = $goods_model->select(array('category_id' => $info['goryid']));
            $this->assign('goods_list',$goods_list);
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
            $this->assign('goodCategory',$rows);
			$this->assign('type',$type);
            return $this->fetch();
        }

    }

    /*
     * 参与管理
     * */
    public function show(){
        $tp_id = input('get.tp_id');
        $this->assign('tp_id',$tp_id);
        if(request()->isAjax()){
            $tp_id = input('tp_id');
            $map['b.m_cat_id'] =['eq',$tp_id];
            $map['b.m_type'] = ['eq',2];
            $list = db('topic')->alias('a')->where($map)
                ->join('__USERS_MATERIAL__ b','a.tp_id=b.m_cat_id','LEFT')
                ->join('__USERS__ c','b.m_uid=c.user_id','LEFT')
                ->join('__GOODS__ d','b.m_goods_id=d.goods_id','LEFT')
                ->field('b.m_id,b.mate_content,b.mate_status,b.mate_zhiding,b.mate_add_time,c.user_avat,c.user_name,d.goods_name')
                ->select();
            $dataArray= [];
            foreach ($list as $val){
                if($val['mate_status']==0){
                    $val['mate_status']='否';
                }
                $val['mate_zhiding'] = $val['mate_zhiding']==0?'否':'是';
                $val['mate_add_time'] = $val['mate_add_time']?date('Y-m-d H:i:s',$val['mate_add_time']):'';
                $dataArray[] =$val;
            }
            return json(['rows'=>$dataArray]);
        }else{
            return $this->fetch();
        }

    }

    /*
     * 参与管理详情
     * */
    public function manageShow(){
        $map['m_id'] = input('get.ids');
        $list = db('users_material')->alias('a')->where($map)
            ->join('__USERS__ b','a.m_uid=b.user_id','LEFT')
            ->join('__GOODS__ c','a.m_goods_id=c.goods_id','LEFT')
            ->field('a.m_id,a.mate_content,a.mate_status,a.mate_zhiding,a.mate_add_time,b.user_avat,b.user_name,c.goods_name')
            ->find();

        $this->assign('row',$list);
        return $this->fetch();
    }
	 /*
	 * 获取店主
	 */
    public function getStory()
    {
		$store_list = db('users')->where('is_seller',1)->field('user_id,user_name')->select();
		return $store_list;
	}
    /*
	 * 删除 话题
	 */
    public function delete()
    {
        $ids=input('get.ids');
        $map['tp_id']=['in',$ids];
        // $res=db('topic')->where($map)->update(['tp_status'=>2]);
		//客户让删除 20181228
        $res=db('topic')->where($map)->delete();
		
        //删除素材
        $where['m_cat_id'] = ['in',$ids];
        $where['m_type']=['eq',2];
        $row =db('users_material')->where($where)->delete();
        //添加日志
        $this->write_log('删除话题',$ids);

        return AjaxReturn($res);
    }

    /*
     * 话题分类展示
     * */
    public function categoryIndex(){
        $cat_name = trim(input('cat_name'));
        $status = trim(input('status'));
        $this->assign('cat_name',$cat_name);
        $this->assign('status',$status);
        if(request()->isAjax()){
            //排序
            $order='cat_id desc';
            //截取
            $limit=input('get.offset').",".input('get.limit');
            $where=[];
            if(input('cat_name')){
                $where['cat_name']=['like','%'.$cat_name.'%'];
            }
            $status=input('status');
            $where['type']=['eq',2];
            if($status==null || $status=='all'){
                $where['type']=['eq',2];
            }elseif($status=='normal'){
                $where['status']=['eq','normal'];
            }else{
                $where['status']=['eq','hidden'];
            }
            $MaterialService= new MaterialCategory();
            $total=$MaterialService->count($where);

            $rows= $MaterialService->select($where,'*',$order,$limit);
            foreach($rows as $val){
                    $val['status']=$val['status']=='normal'?'正常':'隐藏';
            }
            return json(['total'=>$total,'rows'=>$rows]);
        }else{
            return $this->fetch();
        }
    }
	/*
     * 话题分类（material_category）
     * 
	 */
    public function getCategory(){
		$list = Db::name('material_category')->where(array('type'=>2,'status'=>'normal'))->field('cat_id,cat_name')->select();
		return $list;
	}
    /*
     * 话题分类添加
     * */
    public function categoryAdd(){
        if(request()->isAjax()){
            $row = input('post.row/a');
            $MaterialCategory= new MaterialCategory();
            if($row){
                $map['pid']=['eq',trim($row['pid'])];
                $map['cat_name']=['eq',trim($row['cat_name'])];
                $map['type']=['eq',2];
                $res=$MaterialCategory->find($map);
                if($res){
                    return (['code'=>0,'msg'=>'此话题分类已经存在','data'=>'']);
                }
            }
            $row['type']=2;
            $res=$MaterialCategory->add($row);

            //添加日志记录
            $id=db('material_category')->getLastInsID();
            $this->write_log('话题分类添加',$id);

            return AjaxReturn($res,getErrorInfo($res));

        }else{
            $rows=db('material_category')->where(['type'=>2])->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'cat_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            return $this->fetch();
        }
    }

    /*
     * 话题分类修改
     * */
    public function categoryEdit (){
        $MaterialService= new MaterialCategory();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $map['cat_id']=input('post.cat_id');

            $res=$MaterialService->save($map,$row);

            //添加日志
            $this->write_log('话题分类修改',$map['category_id']);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['cat_id']=input('get.ids');
            $row=$MaterialService->find($map);
            $this->assign('row',$row);
            //获取分类列表
            $where['pid']=['eq',0];
            $where['type']=['eq',2];
            $rows=db('material_category')->where($where)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'cat_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            return $this->fetch();
        }
    }

    /*
     * 删除话题分类
     * */
    public function categoryDelete (){
        $ids= input('get.ids');
        $map['cat_id']=['in',$ids];

        //判断一级分类下是否有二级分类
        $MaterialCategory = new MaterialCategory();
        $res = $MaterialCategory->jdugeCategory($ids);
        if($res){
            return (['code'=>0,'msg'=>'此分类下已经存在子分类不能删除','data'=>'']);
        }

        $res=$MaterialCategory->delete($map);

        //添加日志
        $this->write_log('话题分类删除',$ids);

        return AjaxReturn($res);
    }

}