<?php
namespace app\admin\controller;

use app\common\service\Active;
use app\common\service\ActiveGoods;
use app\common\service\ActiveType;
use app\common\service\Bargain;
use app\common\service\FlashActive;
use app\common\service\FlashGoods;
use app\common\service\GroupActive;
use app\common\service\GroupGoods; 
use app\common\service\FullGoods;
use app\common\service\GoodscateBrand;
use app\common\service\GoodsCategory;
use app\common\service\Goods as GoodsService;
use app\common\service\Supplier;
use app\common\service\TeamActivity;
use think\Db;

class Goods extends Base{

    /*
    * 分类管理
    */
    public function category(){
		$category_name = trim(input('category_name'));
        $this->assign('category_name',$category_name);
		if(request()->isAjax()){
			//排序
			$order=input('get.sort')." ".input('get.order');

			if(input('get.search')){
				$map['category_name']=['like','%'.input('get.search').'%'];
			}	
			if(input('category_name')){
				$map['category_name']=['like','%'.input('category_name').'%'];
			}

			$GoodsCategory=new GoodsCategory();
			$total=$GoodsCategory->count($map);
			$rows=$GoodsCategory->select($map,'*',$order);
			//转为树形
			$rowsdata=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
			//名称加上分级
			foreach ($rowsdata as &$value) {
				$value['category_name']='|—'.str_repeat('—',$value['level']).$value['category_name'];
				$data[]=$value;
			}
			if(!$data){
				$data = $rows;
			}
			return json(['total'=>$total,'rows'=>$data]);
		}else{
			return $this->fetch();
		}
    }

    /*
    * 添加分类
    */
    public function categoryAdd(){
    	$GoodsCategory=new GoodsCategory();
		if(request()->isAjax()){
			$row = input('post.row/a');
			if($row){
				$map['pid']=['eq',trim($row['pid'])];
				$map['category_name']=['eq',trim($row['category_name'])];
				$res=$GoodsCategory->find($map);
				if($res){
					return (['code'=>0,'msg'=>'此商品分类已经存在','data'=>'']);
				}
			}
			$res=$GoodsCategory->add($row);

			//添加日志记录
            $id=db('goods_category')->getLastInsID();
            $this->write_log('商品分类添加',$id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			//获取分类列表	
			$rows=db('goods_category')->select();
			//转为树形
			$rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);			
			$this->assign('category',$rows);
			return $this->fetch();
		}    	
    }

    /*
    * 分类编辑
    */
    public function categoryEdit(){
    	$GoodsCategory=new GoodsCategory();

		if(request()->isAjax()){
			$row=input('post.row/a');
			$map['category_id']=input('post.category_id');

			$res=$GoodsCategory->save($map,$row);

			//添加日志
            $this->write_log('商品分类修改',$map['category_id']);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			$map['category_id']=input('get.ids');
			$row=$GoodsCategory->find($map);
			$this->assign('row',$row);
			//获取分类列表	
			$rows=db('goods_category')->where('pid',0)->select();
			//转为树形
			$rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);			
			$this->assign('category',$rows);			
			return $this->fetch();
		}	    	
    }

    /*
    * 删除分类
    */
    public function categoryDelete(){
		$ids=input('get.ids');
		$map['category_id']=['in',$ids];
		//判断此分类下是否有子类
		$GoodsCategory=new GoodsCategory();
		$res = $GoodsCategory->jdugeCategory($ids);
		if($res){
			return (['code'=>0,'msg'=>'该分类下有子类，不允许删除！','data'=>'']);
		}

		$res=$GoodsCategory->delete($map);

        //添加日志
        $this->write_log('商品分类删除',$ids);

		return AjaxReturn($res);
    }
    /*
     * 分类品牌
     */
    public function catebrand(){
		$title = input('title');
        $this->assign('title',$title);

        if(request()->isAjax()){
 
			//limit
			$limit=input('get.offset').",".input('get.limit');
            if(input('get.search')){
                $map['title']=['like','%'.input('get.search').'%'];
            }  
			if(input('title')){
                $map['title']=['like','%'.input('title').'%'];
            }
            $order = "id desc";
            $GoodsBrandmodel=new GoodscateBrand();
            $total=$GoodsBrandmodel->count($map);
            $rows=$GoodsBrandmodel->select($map,'*',$order,$limit);
            $GoodsCategory=new GoodsCategory();
            foreach ($rows as $key=>$val) {
                $goryinfo = $GoodsCategory->find(array('category_id' => $val['goryid']));
                $val['goryname'] = $goryinfo['category_name'];
                $val['status'] =   $val['status'] == 0 ? '展示':'隐藏';
				//供应商名称
				$res = $GoodsBrandmodel->supplierName($val['supplier_id']);
				if($res){
					 $val['supplier_id']  = $res['supplier_title'];
				}
            }
          	return json(['total'=>$total,'rows'=>$rows]);
        }else{
            return $this->fetch();
        }
    }
    /*
   * 添加分类品牌
   */
    public function catebrandAdd(){
        $GoodsCatebrand=new GoodscateBrand();
        if(request()->isAjax()){
            $row=input('post.row/a');
			if($row){
				$map = [
					'title' => trim($row['title']),
					'goryid' => trim($row['goryid']),
				];
				if($row['goryid']){
						$res = Db::name('goods_brand')->where($map)->find();
						if($res){
							return (['code'=>0,'msg'=>'此分类下已经存在此品牌','data'=>'']);
						}
				}
			
				$map = [
					'title' => trim($row['title']),
				];
				if($row['supplier_id']){
					$map['supplier_id'] = trim($row['supplier_id']);
				}
				/* $res = Db::name('goods_brand')->find($map);
				if($res){
					return (['code'=>0,'msg'=>'一个品牌只能对应一个供应商','data'=>'']);
 
				} */
			}
            $res=$GoodsCatebrand->add($row);
            //添加日志
            $id=db('goods_brand')->getLastInsID();
            $this->write_log('分类品牌添加',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $GoodsCategory=new GoodsCategory();
            //获取分类列表
            $rows=db('goods_category')->where('pid',0)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
			
			// 供应商
            $supplierModel = new Supplier();
            $suppplier=$supplierModel->select();
			$this->assign('supplier', $suppplier);			
			
            $this->assign('category',$rows);
            return $this->fetch();
        }
    }

    /*
    * 分类编辑品牌
    */
    public function catebrandEdit(){
        $GoodsCatebrand=new GoodscateBrand();

        if(request()->isAjax()){
            $row=input('post.row/a');
            $map['id']=input('post.id');
			 if($row){
				$where = [
					'title' => trim($row['title']),
					'goryid' => trim($row['goryid']),
					'id' => $map['id'],
				];
				if($row['goryid']){
					$res = Db::name('goods_category')->where('category_id',$row['goryid'])->field('pid')->find();
					if($res['pid'] == 0){
						$res = Db::name('goods_brand')->find($map);
						if($res){
							return (['code'=>0,'msg'=>'此分类下已经存在此品牌','data'=>'']);
		 
						}
					}
				}
				$map2 = [
					'title' => trim($row['title']),
				];
				if($row['supplier_id']!=''){
					$map2['supplier_id'] = trim($row['supplier_id']);
				}

				
			}
            $res=$GoodsCatebrand->save($map,$row);

            //添加日志
            $this->write_log('分类品牌修改',$map['id']);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $row=$GoodsCatebrand->find($map);
            $this->assign('row',$row);
			// 供应商
            $supplierModel = new Supplier();
            $suppplier=$supplierModel->select();
			$this->assign('supplier', $suppplier);
          
            //获取一级分类名
            $id=$row['goryid'];
            $category_id=db('goods_category')->field('pid')->where(['category_id'=>$id])->find();
            $this->assign('category_id',$category_id);
			  //获取分类列表
            $category=db('goods_category')->where(['pid'=>$category_id['pid']])->select();
            $this->assign('allcategory',$category);
			
            //获取一级分类列表
            $rows=db('goods_category')->where('pid',0)->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            return $this->fetch();
        }
    }
    /*
    * 删除分类品牌
    */
    public function catebrandDelete(){
        $ids=input('get.ids');
        $map['id']=['in',$ids];

        $GoodsCatebrand=new GoodscateBrand();
        $res=$GoodsCatebrand->delete($map);

        //添加日志
        $this->write_log('分类品牌删除',$ids);

        return AjaxReturn($res);
    }

    /*
    * 商品管理
    */
    public function index(){
    	$GoodsService=new GoodsService();
		 $goods_numbers = trim(input('goods_numbers'));
		 $goods_name =  trim(input('goods_name'));
		 $status =  trim(input('status', 'all'));
		 $supplier_title =  trim(input('supplier_title'));
		 $category_name =  trim(input('category_name'));
		 $this->assign('goods_name',$goods_name);
		 $this->assign('category_name',$category_name);
		 $this->assign('supplier_title',$supplier_title);
		 $this->assign('goods_numbers',$goods_numbers);
		 $this->assign('status',$status);
		if(request()->isAjax()){
			//排序
			$order="weigh desc,goods_id desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
			if (session('group_id') == 3) {
				$admin_id = session('admin_id');
				$supplier_id = Db::name('admin')->where('admin_id',$admin_id)->value('supplier_id');
                if (!empty($supplier_id)) {
                    $supplier_id = Db::name('supplier')->where(array('id' => $supplier_id))->value('id');
                    if (!empty($supplier_id)) {
                        $map['supplier_id'] = $supplier_id;
                    } else {
                        return json(['total'=>0,'rows'=>[]]);
                    }
                } else {
                    return json(['total'=>0,'rows'=>[]]);
                }
            }

			if(input('get.search')){
				$map['goods_name']=['like','%'.input('get.search').'%'];
			}
			if(input('goods_name')){
				$map['goods_name']= ['like','%'.input('goods_name').'%'];
			}
			if(input('goods_numbers')){
				$map['goods_numbers']= input('goods_numbers');
			}
			//回收站

			$status_where = input('status');
			if($status_where === '0' || $status_where == 1){
				$map['status']=  ['eq',$status_where];
			} else {
                $map['status']=['neq',3];
            }
			if(input('category_name')){
				$where = [
				'category_name'=>['like','%'.input('category_name').'%']
				];
				$res = Db::name('goods_category')->where($where)->find();
				if($res){
					$map['category_id'] = $res['category_id'];
				}
			}
			if(input('supplier_title')){
				$where2 = [
					'supplier_title'=>['like','%'.input('supplier_title').'%']
				];
				$res = Db::name('supplier')->where($where2)->find();
				 if($res){
					$map['supplier_id']=  $res['id']; 
				}
			}
			
			$total=$GoodsService->count($map);
			$rows=$GoodsService->select($map,'*',$order,$limit);
			if ($rows) {
                $status_list = array(0=>'立即上架',1=>'放入仓库');
                foreach ($rows as &$val) {
                    $val['status'] = $status_list[$val['status']];
                }
            } 
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}    	
    }/*
    * 商品回收站管理
    */
    public function recycle(){
    	$GoodsService=new GoodsService();
		if(request()->isAjax()){
			//排序
			$order="weigh desc,goods_id desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
			if (session('group_id') == 2) {
                $phone = session('mobile');
                if (!empty($phone)) {
                    $supplier_id = Db::name('supplier')->where(array('supplier_phone' => $phone))->value('id');
                    if (!empty($supplier_id)) {
                        $map['supplier_id'] = $supplier_id;
                    } else {
                        return json(['total'=>0,'rows'=>[]]);
                    }
                } else {
                    return json(['total'=>0,'rows'=>[]]);
                }
            }

			if(input('get.search')){
				$map['goods_name']=['like','%'.input('get.search').'%'];
			}
			//回收站
			$map['status']=['eq',3];
			$total=$GoodsService->count($map);
			$rows=$GoodsService->select($map,'*',$order,$limit);
			 foreach ($rows as &$val) {
                    $val['status'] = '回收站';
                }
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}    	
    }

    /*
    * 添加商品
    */
    public function add(){

    	$GoodsService=new GoodsService();
		if(request()->isAjax()){
			$row=input('post.row/a');
			$sku=input('post.sku/a');
			$subject_key = input('post.subject_key/a');
            $subject_val = input('post.subject_val/a');
            $subject_value = input('post.subject_value/a');
            $subject['subject_id'] = $subject_value;
            $subject['key'] = $subject_key;
            $subject['val'] = $subject_val;
            $row['subject_values'] = json_encode($subject);
            // 这里判断是否选择规格
            if ($sku) {
                foreach ($sku as $val) {
                    if (count($val) < 5) {
                        return AjaxReturn(0, '未选择规格');
                    }
                }
            } else {
                return AjaxReturn(0, '未设置规格');
            }
			$res=$GoodsService->add($row, $sku);

            //日志记录
            // $result = $GoodsService->order('goods_id desc')->find();
			 $result = Db::name('goods')->order('goods_id desc')->find();
            $add['uid'] = session('admin_id');
            $add['ip_address'] = request()->ip();
            $add['controller'] = request()->controller();   
            $add['action'] = request()->action();
            $add['remarks'] = '添加商品';
            $add['number'] = $result['goods_id'];
            $add['create_at'] = time(); 
            db('web_log')->insert($add); 

			return AjaxReturn($res>0?1:0,getErrorInfo($res));
		}else{
			//获取分类列表
			$GoodsCategory=new GoodsCategory();
			$GoodsCatebramd = new GoodscateBrand();
			$map['status']='normal';
			$map['pid']=0;
            $rows=$GoodsCategory->select($map);
            // 根据第一个分类获取品牌
            $brandlist = array();
            if ($rows) {
                $goryid = $rows[0]->category_id;
                if ($goryid) {
                    $brandlist = $GoodsCatebramd->select(array('goryid' => $goryid));
                }
            }
            $this->assign('brandlist',$brandlist);
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
			$this->assign('category',$rows);

			//获取类型列表
			$attr=$GoodsService->getAttributeList([],'*','weigh desc');
			$this->assign('attr',$attr['rows']);
			// 供应商
            $supplierModel = new Supplier();

            $suppplier=$supplierModel->select();
            $this->assign('supplier', $suppplier);
			return $this->fetch();

		}
    }  

    /*
    * 商品编辑
    */  
   	public function edit(){

   		$GoodsService=new GoodsService();

		if(request()->isAjax()){
			$row=input('post.row/a');
			$sku=input('post.sku/a');

			$goods_id=input('post.goods_id');
            $subject_key = input('post.subject_key/a');
            $subject_val = input('post.subject_val/a');
            $subject_value = input('post.subject_value/a');
            $subject['subject_id'] = $subject_value;
            $subject['key'] = $subject_key;
            $subject['val'] = $subject_val;
            $row['subject_values'] = json_encode($subject);
            if($row['status']==1){
                $prom_type = db::name('goods')->where('goods_id',$goods_id)->value('prom_type');
                if($prom_type>0){
                    $this->error('该商品正在活动中，请先结束活动');
                }
            }
			$res=$GoodsService->save($goods_id,$row,$sku);
            //日志记录
            $add['uid'] = session('admin_id');
            $add['ip_address'] = request()->ip();
            $add['controller'] = request()->controller();   
            $add['action'] = request()->action();
            $add['remarks'] = '商品编辑';
            $add['number'] = $goods_id;
            $add['create_at'] = time(); 
            db('web_log')->insert($add);

			return AjaxReturn($res>0?1:0,getErrorInfo($res));
		}else{
			//获取分类列表
			$GoodsCategory=new GoodsCategory();
			$map['status']='normal';
			$map['pid']=0;
			$category=$GoodsCategory->select($map);
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($category)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
//			$this->assign('category',$category);
			//商品详情
			$map=[];
			$map['goods_id']=input('get.ids');
            $row=$GoodsService->find($map);
			$row['sku']=collection($row['sku'])->toArray();
			$this->assign('row',$row);
			//查询一级分类
            $category_id=db('goods_category')->field('pid')->where(['category_id'=>$row['category_id']])->find();
            $this->assign('category_id',$category_id);
            //获取分类列表
            $category=db('goods_category')->where('pid', $category_id['pid'])->select();
            $this->assign('allcategory',$category);
			//处理sku信息
			$sku=[];

			foreach ($row['sku'] as $key => $value) {
				$sku[$value['attr_value']]=$value;
			}
			$this->assign('sku',$sku);
			// 获取品牌
            $GoodsCatebramd = new GoodscateBrand();
            $brandlist = array();
            if ($category) {
                $goryid = $row['category_id'];
                if ($goryid) {
                    $brandlist = $GoodsCatebramd->select(array('goryid' => $goryid));
                }
            }
            $subject = json_decode($row['subject_values'], true);
            $this->assign('subject',$subject);
            $this->assign('brandlist',$brandlist);
			//获取类型列表
			$attr=$GoodsService->getAttributeList([],'*','weigh desc');
			$this->assign('attr',$attr['rows']);
            // 供应商
            $supplierModel = new Supplier();

            $suppplier=$supplierModel->select();
            $this->assign('supplier', $suppplier);
			return $this->fetch();
		}   		
   	}
   	/*
   	 * 运费设置
   	 */
   	public function editaddr()
    {
        $GoodsService=new GoodsService();
        $goods_id = input('goods_id');
        if(request()->isAjax()){
            $row = input('row/a');
            $where['goods_id'] = $row['goods_id'];
            $data['province'] = $row['province'] ? implode(',', $row['province']) : '';
            $data['basic_freight'] = json_encode($row['basic_freight']);
            $data['is_free_shipping'] = $row['is_free_shipping'];
            $data['other_freight'] = $row['other_freight'];
            $res = Db::name('goods')->where($where)->update($data);

            //添加日志记录
            $this->write_log('修改运费',$where['goods_id']);

            return AjaxReturn($res !== false?1:0,getErrorInfo($res));
        }else{
            $provice_list = $GoodsService->getProvince();
            $this->assign('provice_list', $provice_list);
            $goods_info = $GoodsService->getGoodsinfo($goods_id);
            $goods_info['province'] = explode(',', $goods_info['province']);
            $goods_info['basic_freight'] = json_decode( $goods_info['basic_freight'], true);
            $this->assign('row', $goods_info);
            return $this->fetch();
        }
    }

	/*
    * 商品详情查看
    */  
   	public function goodsShow(){
   		$GoodsService=new GoodsService();	 
		//获取分类列表
		$GoodsCategory=new GoodsCategory();
		$map['status']='normal';
		$category=$GoodsCategory->select($map);
		//转为树形
		$rows=\util\Tree::makeTreeForHtml(collection($category)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
		$this->assign('category',$rows);
//			$this->assign('category',$category);
		//商品详情
		$map=[];
		$map['goods_id']=input('get.goods_id');
		$row=$GoodsService->find($map);
		$row['sku']=collection($row['sku'])->toArray();
		$this->assign('row',$row);
		//处理sku信息
		$sku=[];
		foreach ($row['sku'] as $key => $value) {
			$sku[$value['attr_value']]=$value;
		}
		$this->assign('sku',$sku);
		// 获取品牌
		$GoodsCatebramd = new GoodscateBrand();
		$brandlist = array();
		if ($category) {
			$goryid = $row['category_id'];
			if ($goryid) {
				$brandlist = $GoodsCatebramd->select(array('goryid' => $goryid));
			}
		}
		$subject = json_decode($row['subject_values'], true);
		$this->assign('subject',$subject);
		$this->assign('brandlist',$brandlist);
		//获取类型列表
		$attr=$GoodsService->getAttributeList([],'*','weigh desc');
		$this->assign('attr',$attr['rows']);
		// 供应商
		$supplierModel = new Supplier();

		$suppplier=$supplierModel->select();
		$this->assign('supplier', $suppplier);
		return $this->fetch();
   	}
   	/*
   	* 回收站商品（恢复）
   	*/
   	public function restore(){
   		$ids=input('get.id');
		$map['goods_id']=['in',$ids];
		$GoodsService=new GoodsService();
		$row['status']= 0;
		$res=$GoodsService->save($map['goods_id'],$row);

		//添加日志记录
        $this->write_log('回收站商品还原',$map['goods_id']);

		// $res=$GoodsService->delete($map);
		return AjaxReturn($res);     		
   	}
	/*
   	* 回收站商品 删除
   	*/
   	public function goodsRemove(){
   		$ids=input('get.ids');
		$map['goods_id']=['in',$ids];
		$GoodsService=new GoodsService();
		$res=$GoodsService->delete($map);

        //添加日志记录
        $this->write_log('回收站商品删除',$map['goods_id']);

		return AjaxReturn($res);     		
   	}
	/*
   	* 删除商品（加入回收站）
   	*/
   	public function delete(){
   		$ids=input('get.ids');
		$map['goods_id']=['in',$ids];

		$GoodsService=new GoodsService();
		$list = $GoodsService->select(['goods_id' => ['in', $ids]]);
		foreach ($list as $val) {
		    if ($val['prom_type'] > 0) {
		        return $this->error('删除失败，商品中有活动商品');
		        exit;
            }
        }
		//$row['status']= 3;
		//$res=$GoodsService->save($map['goods_id'],$row);
		$res=$GoodsService->delete($map);
        //日志记录
        $add['uid'] = session('admin_id');
        $add['ip_address'] = request()->ip();
        $add['controller'] = request()->controller();   
        $add['action'] = request()->action();
        $add['remarks'] = '删除商品';
        $add['number'] = $ids;
        $add['create_at'] = time(); 
        db('web_log')->insert($add);

		return AjaxReturn($res);     		
   	}

   	/*
   	* 商品操作
   	*/
   	public function multi(){		
   		$action=input('action');
   		$ids=input('get.ids/a');
		$array = explode(",",$ids[0]);
		
		if(!$action){
			return AjaxReturn(UPDATA_FAIL);   
		}
		$val = input('params');
		//商品列表
		if($action=='status'){
			$data['status'] = $val;
			for($i=0;$i<count($array);$i++){
			    $res=db('goods')->where('goods_id',$array[$i])->update($data);
            }
//			$res= Db::name('goods')->where($map)->update($data);
		}else if($action=='miaosha'){
			//秒杀商品列表
			$map['id']=['in',$array];
			$data['is_end'] = $val;
			$res= Db::name('flash_goods')->where($map)->update($data);

		}else if($action=='bargain'){
			//砍价商品列表
			$map['id']=['in',$array];
			$data['status'] = $val;
			$res= Db::name('bargain')->where($map)->update($data);

		}else if($action=='active_goods'){
			//自定义商品列表
			$map['id']=['in',$array];
			$data['status'] = $val;//状态0正常1停止
			$res= Db::name('active_goods')->where($map)->update($data);

		}else if($action=='groupgou'){
			//团购商品列表
			$map['id']=['in',$array];
			$data['is_end'] = $val;//状态0正常1停止
			$res= Db::name('group_goods')->where($map)->update($data);

		}else if($action=='teamspell'){
			//拼团商品列表
			$map['id']=['in',$array];
			$data['status'] = $val;//状态0正常1停止
			$res= Db::name('team_activity')->where($map)->update($data);

		}else if($action=='fullgoods'){
			//拼团商品列表
			$map['id']=['in',$array];
			$data['is_end'] = $val;//状态0正常1停止
			$res= Db::name('full_goods')->where($map)->update($data);

		}
		if($res !==false){
			return AjaxReturn(true);
		}
		return AjaxReturn($res);
		
   	}
   	/*
   	 * 修改 商品标签
   	 */
   	public function editbiaoqian()
    {
        if (request()->isAjax()) {
            $biaoqian = input('request.biaoqian', '');
            $data['active_name'] = $biaoqian;
            $map = ['goods_id' => ['gt', 0]];
            $res= Db::name('goods')->where($map)->update($data);

			$map['goods_id'] = implode(',',$map['goods_id']);

            //添加日志记录
            $this->write_log('修改商品标签',$map['goods_id']);
			
			
            return AjaxReturn($res);
        } else {
			$row=db('goods')->order('goods_id desc')->field('active_name')->find();
			$active_name = $row['active_name'];
			$this->assign('active_name',$active_name);
            return $this->fetch();
        }

    }

   	/*
   	* 商品规格
   	*/
   	public function spec(){
    	$GoodsService=new GoodsService();
		$spec_name = trim(input('spec_name'));
        $this->assign('spec_name',$spec_name);
		if(request()->isAjax()){
			//排序
			$order=input('get.sort')." ".input('get.order');
			//limit
			$limit=input('get.offset').",".input('get.limit');
			if(input('spec_name')){
					$map['spec_name'] = ['like','%'.input('spec_name').'%'];
			}
			$data=$GoodsService->getSpecList($map,'*',$order,$limit);
			return json($data);
		}else{
			return $this->fetch();
		}
   	}
    /*
    * 商品参数
    */
    public function subject(){
        $title=input('title');
        $this->assign('title',$title);
        $GoodsService=new GoodsService();
        if(request()->isAjax()){
            //排序
            $order=input('get.sort')." ".input('get.order');
            //limit
            $limit=input('get.offset').",".input('get.limit');
            if(input('get.search')){
                $map['title']=['like','%'.input('get.search').'%'];
            }
            if(input('title')){
                $map['title']=['like','%'.input('title').'%'];
            }
            $data=$GoodsService->getSubjectList($map,'*',$order,$limit);
            return json($data);
        }else{
            return $this->fetch();
        }
    }
   	/*
   	* 添加商品规格
   	*/
   	public function specAdd(){
		if(request()->isAjax()){
			$row=input('post.row/a');
			$value = input('post.value/a');
			//属性值去重
			if(count($value) != count(array_unique($value))){
				return (['code'=>-1,'msg'=>'类型属性重复','data'=>' ']);
			}
			$GoodsService=new GoodsService();
			$res=$GoodsService->addSpec($row,$value);

            //添加日志记录
            $id=db('goods')->getLastInsID();
            $this->write_log('添加商品规格',$id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{

			return $this->fetch();
		}   		
   	}

   	/*
   	* 编辑商品规格
   	*/
   	public function specEdit(){
   		$GoodsService=new GoodsService();

		if(request()->isAjax()){
			$row=input('post.row/a');
			$value=input('post.value/a');
			//属性值去重
			if(count($value) != count(array_unique($value))){
				return (['code'=>-1,'msg'=>'类型属性重复','data'=>' ']);
			}
			$spec_id=input('post.spec_id');

			$res=$GoodsService->updateSpec($spec_id,$row,$value);

            //添加日志记录
            $this->write_log('编辑商品规格',$spec_id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			$map['spec_id']=input('get.ids');
			$row=$GoodsService->getSpecInfo($map);
			$this->assign('row',$row);
			return $this->fetch();
		}     		
   	}

   	/*
   	 * 规格名称检查
   	 */
   	public function specNameCheck(){
   		$spec_id = input('request.spec_id');
   		if(!$spec_id){
   			return ['code' => 0, 'msg' => '网络错误'];
   		}
   		$spec_name = input('request.spec_name');
   		if(!$spec_name){
   			return ['code' => 0, 'msg' => '规格名称不能为空'];
   		}
   		$result = Db::name('goods_spec')->where(['spec_name' => $spec_name, 'spec_id' => ['neq', $spec_id]])->field('spec_id')->find();
   		if($result){
   			return ['code' => -1];
   		}
   		else return ['code' => 1];
   	}

   	/*
   	* 删除商品规格
   	*/
   	public function specDelete(){
   		$ids=input('get.ids');
		$GoodsService=new GoodsService();
		$res=$GoodsService->deleteSpec($ids);

        //添加日志记录
        $this->write_log('删除商品规格',$ids);

		return AjaxReturn($res);    		
   	}

   	/*
   	* 删除规格属性
   	*/
   	public function specValueDelete(){
   		$id=input('get.id');
   		$GoodsService=new GoodsService();
		$res=$GoodsService->deleteSpecValue($id);

        //添加日志记录
        $this->write_log('删除规格属性',$id);

		return AjaxReturn($res);    
   	}

   	/*
   	* 编辑规格属性
   	*/
   	public function specValueEdit(){
   		$id=input('post.id');
   		$name=input('post.name');
   		$GoodsService=new GoodsService();
		$res=$GoodsService->editSpecValue($id,$name);

        //添加日志记录
        $this->write_log('编辑规格属性',$id);

		return AjaxReturn($res);    
   	}
    /*
    * 添加商品属性
    */
    public function subjectAdd(){
        if(request()->isAjax()){
            $row=input('post.row/a');
            $value=input('post.value/a');

            $GoodsService=new GoodsService();
            $res=$GoodsService->addSubject($row,$value);

            //添加日志记录
            $id=db('goods')->getLastInsID();
            $this->write_log('添加商品属性',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{

            return $this->fetch();
        }
    }

    /*
    * 编辑商品属性
    */
    public function subjectEdit(){
        $GoodsService=new GoodsService();

        if(request()->isAjax()){
            $row=input('post.row/a');
            $value=input('post.value/a');
            $subject_id=input('post.subject_id');
            $res=$GoodsService->updateSubject($subject_id,$row,$value);

            //添加日志记录
            $this->write_log('编辑商品属性',$subject_id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['subject_id']=input('get.ids');
            $row=$GoodsService->getsubjectInfo($map);
            $this->assign('row',$row);
            return $this->fetch();
        }
    }

    /*
    * 删除商品属性
    */
    public function subjectDelete(){
        $ids=input('get.ids');
        $GoodsService=new GoodsService();
        $res=$GoodsService->deleteSubject($ids);

        //添加日志记录
        $this->write_log('删除商品属性',$ids);

        return AjaxReturn($res);
    }

    /*
    * 删除规格属性
    */
    public function subjectValueDelete(){
        $id=input('get.id');
        $GoodsService=new GoodsService();
        $res=$GoodsService->deleteSubjectValue($id);

        //添加日志记录
        $this->write_log('删除规格属性',$id);

        return AjaxReturn($res);
    }

    /*
    * 编辑规格属性
    */
    public function subjectValueEdit(){
        $id=input('post.id');
        $name=input('post.name');
        $GoodsService=new GoodsService();
        $res=$GoodsService->editSubjectValue($id,$name);

        //添加日志记录
        $this->write_log('编辑规格属性',$id);

        return AjaxReturn($res);
    }

   	/*
   	* 商品类型
   	*/
   	public function attribute(){
    	$GoodsService=new GoodsService();
		$attr_name = trim(input('attr_name'));
        $this->assign('attr_name',$attr_name);
		if(request()->isAjax()){
			//排序
			$order=input('get.sort')." ".input('get.order');
			//limit
			$limit=input('get.offset').",".input('get.limit');
			if(input('attr_name')){
				$map['attr_name'] = ['like','%'.input('attr_name').'%'];
			}
			$data=$GoodsService->getAttributeList($map,'*',$order,$limit);
			return json($data);
		}else{
			return $this->fetch();
		}
   	}
    /*
    * 获取商品品牌
    */
    public function getCatebrand(){
        $GoodsBrand=new GoodscateBrand();
        $map['goryid']=input('goryid');
        $info=$GoodsBrand->select($map);
        if (!$info) {
            $info =array();
        }
        return $info;
    }
   	/*
   	* 添加商品类型
   	*/
   	public function attributeAdd(){
   		$GoodsService=new GoodsService();
		if(request()->isAjax()){
			$row=input('post.row/a');
			if($row){
				$map['attr_name'] = ['eq',trim($row['attr_name'])];
				$result = Db::name('attribute')->where($map)->find();
				if($result){
					return (['code'=>-1,'msg'=>'类型名称已经存在','data'=>' ']);
				}
			}
			$res=$GoodsService->addAttribute($row);

            //添加日志记录
            $id=db('goods')->getLastInsID();
            $this->write_log('添加商品类型',$id);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
            $subject=$GoodsService->getSubjectList([],'*',"weigh desc");
            $this->assign('subject',$subject['rows']);
			//获取规格列表
			$spec=$GoodsService->getSpecList([],'*',"weigh desc");
			$this->assign('spec',$spec['rows']);
			return $this->fetch();
		}   		
   	}

   	/*
   	* 编辑商品类型
   	*/
   	public function attributeEdit(){
   		$GoodsService=new GoodsService();

		if(request()->isAjax()){
			$row=input('post.row/a');

			$map['attr_id']=input('post.attr_id');
			$res=$GoodsService->updateAttribute($map,$row);

            //添加日志记录
            $this->write_log('编辑商品类型',$map['attr_id']);

			return AjaxReturn($res,getErrorInfo($res));
		}else{
			//商品类型详情
			$map['attr_id']=input('get.ids');
			$row=$GoodsService->getAttributeInfo($map);
			$row['spec_id_array']=explode(',', $row['spec_id_array']);
            $row['subject_id_array']=explode(',', $row['subject_id_array']);
			$this->assign('row',$row);
			//获取规格列表
			$spec=$GoodsService->getSpecList([],'*',"weigh desc");
			$this->assign('spec',$spec['rows']);
            //获取主体属性
            $subject=$GoodsService->getSubjectList([],'*',"weigh desc");
            $this->assign('subject',$subject['rows']);
            return $this->fetch();
		}     		
   	}

   	/*
   	* 获取商品类型详情
   	*/
   	public function attributeInfo(){
   		$GoodsService=new GoodsService();
   		$map['attr_id']=input('attr_id');
   		$info=$GoodsService->getAttributeInfo($map)->toArray();
   		//获取规格
   		$map=[
   			'spec_id'=>['in',$info['spec_id_array']]
   		];
   		$spec_list=$GoodsService->getSpecList($map);
   		$info['spec_list']=collection($spec_list['rows'])->toArray();
        //获取主体属性
        $map=[
            'subject_id'=>['in',$info['subject_id_array']]
        ];
        $subject_list=$GoodsService->getSubjectList($map);
        $info['subject_list']=collection($subject_list['rows'])->toArray();
   		return $info;
   	}

   	/*
   	* 删除商品类型
   	*/
   	public function attributeDelete(){
   		$ids=input('get.ids');
		$GoodsService=new GoodsService();
		$res=$GoodsService->deleteAttribute($ids);
        //添加日志记录
        $this->write_log('添加商品类型',$ids);
		return AjaxReturn($res);    		
   	}

   	/*
   	* 获取商品sku信息
   	*/
   	public function skuByCode(){
   		$code=input('get.code');
   		$GoodsService=new GoodsService();
   		$map['code']=$code;
   		$info=$GoodsService->getSkuInfo($map);
   		return AjaxReturn($info?1:0,$info);
   	}

   	/*
   	* 查询商品信息
   	*/
   	public function getGoodsInfo(){
   		$map['goods_name']=input('get.goods_name');
   		$GoodsService=new GoodsService();
   		$data=$GoodsService->where(array('stock'=>['>=',1]))->find($map);
   		return json($data);
   	}
	/*
   	* 查询供应商信息
   	*/
   	public function getGoodsSup(){
   		$map['a.id']=input('get.brandid');
   		$GoodsService=new GoodsService();
   		$data=$GoodsService->getSup($map);
   		return json($data);
   	}
    /*
    * 查询商品信息
    */
    public function getGoodsInfoId(){
        $map['goods_id']=input('get.goods_id');
        $GoodsService=new GoodsService();
        $data=$GoodsService->find($map);
        return json($data);
    }

    /*
    * 查询商品信息
    */
   	public function getGoodsList(){
   		$map['goods_name']=['like','%'.input('get.goods_name').'%'];
   		$GoodsService=new GoodsService();
   		$data=$GoodsService->select($map);
   		foreach ($data as $key => $value) {
   			foreach ($value->sku as $v) {
   				$sku=$v->toArray();
	   			$sku['goods']=$value;
	   			$list[]=$sku;
   			}
   		}
   		return json($list);
   	}

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
    /*
     * 根据商品分类获取二级分类
     * */
    public function getSecondName(){
        $category_id=input('categoryid');
        $GoodsCategory= new GoodsCategory();
        $map['pid']=$category_id;
        $rows=$GoodsCategory->select($map);
        return json(['rows'=>$rows]);
    }

    // 根据商品分类获取商品
    public function getGoodsName()
    {
        $goryid= input('goryid');
        $id = input('id');//砍价id

        $GoodsService=new GoodsService();
        //$sku_id = $GoodsService->getSkuId($id);
        //排序
        $order="weigh desc,goods_id desc";
        //limit
        $limit=input('get.offset').",".input('get.limit');

        if($goryid){
            $map['category_id']= $goryid;
        }
        // 排除 活动已选则的商品
        $map['prom_id'] = ['eq', 0];
        $map['prom_type'] = ['eq', 0];
        $map['is_recom_today'] = ['neq', 1];
//        $map['status'] = ['neq',3];
        $map['status'] = ['eq',0];
        $rows=$GoodsService->select($map,'goods_id,stock,goods_name,price,picture',$order,$limit);
        foreach ($rows as $k=>$v){
            $sku = Db::name('goods_sku')->where('goods_id',$v['goods_id'])->field('sku_id,sku_name,price')->select();
           $rows[$k]['goods_sku'] = $sku;
        }
        return json(['rows'=>$rows]);
    }
    // 素材根据商品分类获取商品
    public function getGoodsNames()
    {
        $goryid= input('goryid');
        $id = input('id');

        $GoodsService=new GoodsService();
        //$sku_id = $GoodsService->getSkuId($id);
        //排序
        $order="weigh desc,goods_id desc";
        //limit
        $limit=input('get.offset').",".input('get.limit');

        if($goryid){
            $map['category_id']= $goryid;
        }
        // 排除 活动已选则的商品
//        $map['prom_id'] = ['eq', 0];
//        $map['prom_type'] = ['eq', 0];
		$map['status'] =['neq',3];
        $rows=$GoodsService->select($map,'goods_id,stock,goods_name,price,picture',$order,$limit);
        return json(['rows'=>$rows]);
    }
	// 根据商品分类获取商品 属性
    public function getGoodsSku()
    {
        $goryid= input('goryid');
        $GoodsService=new GoodsService();
        //排序
        $order="weigh desc,goods_id desc";
        //limit
		
        $limit=input('get.offset').",".input('get.limit');

        if($goryid){
            $map['category_id']= $goryid;
        }
        // 排除 活动已选则的商品
        $map['prom_id'] = ['eq', 0];
        $map['prom_type'] = ['eq', 0];
//        $map['status'] = ['neq', 3];
        $map['status'] = ['eq', 0];
        $rows = $GoodsService->select($map,'goods_id,goods_name,price,stock,picture',$order,$limit);
		foreach( $rows as $key=>$val){
			$list = $GoodsService->getGui($val['goods_id']);
			$rows[$key]['list'] = $list;
		}
        return json(['rows'=>$rows]);
    }
	// 根据商品分类获取  属性
    public function getSku()
    {
		$GoodsService=new GoodsService();
        $goods_id = input('goods_id');
        $id = input('id');//砍价id
		$list = $GoodsService->huoquGui($goods_id);
		$sku_id = $GoodsService->getSkuId($id);
        return json(['rows'=>$list,'id'=>$id]);
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
    /**
     * 团购列表
     * team_activity
     */
    public function tuangou(){
        if(request()->isAjax()){
            //排序
            $order=input('get.sort')." ".input('get.order');

            if(input('get.search')){
                $where['goods_name']=['like','%'.input('get.search').'%'];
            }
            $teammodel = new TeamActivity();
            $total = $teammodel->count($where);
            $list = $teammodel->getList($where);
            if ($list) {
                $status_list = array(0=>'正常',1=>'结束');
                foreach ($list as &$val) {
                    $goods_info = $teammodel->getGoodsInfo($val['goods_id']);
                    $val['picture'] = $goods_info['picture'];
                    $val['price'] = $goods_info['price'];
                    $val['status'] = $status_list[$val['status']];
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
    public function tuangouAdd () {

        $teammodel = new TeamActivity();
        if(request()->isAjax()){
            $row=input('post.row/a');

            $res=$teammodel->add($row);

            //添加日志记录
            $id=db('team_activity')->getLastInsID();
            $this->write_log('团购商品添加',$id);

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
    public function tuangouEdit () {
        $teammodel = new TeamActivity();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $id = input('post.id');
            $res=$teammodel->save(array('id' => $id),$row);

            //添加日志记录
            $this->write_log('团购商品修改',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $info = $teammodel->find($map)->toArray();
            $goods_model = new GoodsService();
            $goodsinfo =$goods_model->find(array('goods_id' => $info['goods_id']));
            $info['goryid'] = $goodsinfo['category_id'];
            $info['goods_price'] = $goodsinfo['price'];
            $this->assign('row',$info);
            // 获取该分类下的所有商品
            $goods_list = $goods_model->select(array('category_id' => $info['goryid']));
            $this->assign('goods_list',$goods_list);
            //获取分类列表
            $GoodsCategory=new GoodsCategory();
            $rows=$GoodsCategory->select();
            //转为树形
            $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
            $this->assign('category',$rows);
            return $this->fetch();
        }
    }
    /**
     * 删除 团购商品
     */
    public function tuangouDel()
    {
        $ids=input('get.ids');
        $teammodel = new TeamActivity();
        $where = array();
        $where['id'] = array('in', $ids);
        $res=$teammodel->delete($where);

        //添加日志记录
        $this->write_log('删除团购商品',$ids);

        return AjaxReturn($res);
    }
    /**
     * 活动设置
     */
    public function activeSet()
    {
        if(request()->isAjax()){

            $Activemodel = new Active();
            $total = $Activemodel->count();
            $list = $Activemodel->select();
            if ($list) {
                $status_list = array(0=>'正常',1=>'结束');
                foreach ($list as &$val) {

                    $val['status'] = $status_list[$val['status']];
                }
            }
            return json(['total'=>$total,'rows'=>$list]);
        }else{
            return $this->fetch();
        }
    }
    /**
     * 活动修改
     */
    public function activeEdit() {

        $Activemodel = new Active();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $id = input('post.id');
            $res=$Activemodel->save(array('id' => $id),$row);

            //添加日志记录
            $this->write_log('活动修改',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $info = $Activemodel->find($map);
            $this->assign('row',$info);
            return $this->fetch();
        }
    }
    /**
     * 秒杀设置时段
     */
    public function miaoshaTimeSet()
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
//			if ($day1 != $day2) {
//                return (['code'=>0,'msg'=>'开始和结束时间必须为同一天']);
//            }
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
//            $day1 = date('d', $row['start_time']);
//            $day2 = date('d', $row['end_time']);
//            if ($day1 != $day2) {
//                return (['code'=>0,'msg'=>'开始和结束时间必须为同一天']);
//            }
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
                    $val['price'] = $goodsinfo['price'];
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
            if (!$row['sku_id']){
                return (['code'=>0,'msg'=>'请选择商品属性','data'=>'请选择商品属性']);
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
				$id=db('flash_goods')->getLastInsID();
				
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
			if(!$row['sku_id']){
                return (['code'=>0,'msg'=>'请选择商品属性','data'=>'请选择商品属性']);
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
     * 供应商管理
     */
    public function supplierIndex()
    {
		$supplier_title = input('supplier_title');
        $this->assign('supplier_title',$supplier_title);
        $supplierModel = new Supplier();

        if(request()->isAjax()){
            //排序
            $order="id desc";
            $limit=input('get.offset').",".input('get.limit');
            $map = [];
			if(input('get.search')){
				$map['supplier_title']=['like','%'.input('get.search').'%'];
			}
			if(input('supplier_title')){
				$map['supplier_title']=['like','%'.input('supplier_title').'%'];
			}			
			// $map['status'] = 3;
            $total=$supplierModel->count($map);
            $rows=$supplierModel->select($map,'*',$order, $limit);
            if (!empty($rows)) {
                $jiesuan_arr = [ 1 => '周结', 2 => '月结'];
                foreach ($rows as &$val) {
                    $val['jiesuan'] = $jiesuan_arr[$val['jiesuan']];
                }
            }
            return json(['total'=>$total,'rows'=>$rows]);
        }else{
            return $this->fetch();
        }
    }
    /**
     * 供应商添加
     */
    public function supplierAdd()
    {
        $supplierModel = new Supplier();
        if(request()->isAjax()){
            $row=input('post.row/a');
			if($row){
				$map['supplier_title'] = ['eq',trim($row['supplier_title'])];
				$res = $supplierModel->find($map);
				if($res){
					return (['code'=>-1,'msg'=>' 供应商名称已经存在','data'=>' ']);
				}
			}
            $res=$supplierModel->add($row);

            //添加日志记录
            $id=db('supplier')->getLastInsID();
            $this->write_log('供应商添加',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            return $this->fetch();
        }
    }
    /**
     * 供应商修改
     */
    public function supplierEdit()
    {
        $supplierModel = new Supplier();
        if(request()->isAjax()){
            $row=input('post.row/a');
            $id = input('post.id');
			if($row){
				$map['supplier_title'] = ['eq',trim($row['supplier_title'])];
				$res = $supplierModel->find($map);
				 
				if($res){
					if($res ['id'] != $id ){
						return (['code'=>-1,'msg'=>' 供应商名称已经存在','data'=>' ']);
					}	 
				}
			}
            $res=$supplierModel->save(array('id' => $id),$row);

            //添加日志记录
            $this->write_log('供应商修改',$id);

            return AjaxReturn($res,getErrorInfo($res));
        }else{
            $map['id']=input('get.ids');
            $info = $supplierModel->find($map);
            $this->assign('row',$info);
            return $this->fetch();
        }
    }
    /**
     * 供应商删除
     */
    public function supplierDel()
    {
        $supplierModel = new Supplier();
        $ids=input('get.ids');
        $map['id']=['in',$ids];

        $res=$supplierModel->delete($map);

        //添加日志记录
        $this->write_log('供应商添加',$ids);

        return AjaxReturn($res);
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
	/**
     * 上架商品
     */
    public function putway()
    {
		$ids=input('get.ids');
		$map['goods_id']=['in',$ids];
		$GoodsService=new GoodsService();
		$res=$GoodsService->delete($map);

        //添加日志记录
        $this->write_log('上架商品删除',$ids);

		return AjaxReturn($res);     	
    }

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
            $list = $Groupgoodsmodel->getList($where,'*',$orde,$limit);
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
                    $val['price'] = $goodsinfo['price'];
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
            if(!$row['sku_id']){
                return (['code'=>0,'msg'=>'请选择商品属性','data'=>'请选择商品属性']);
            }
            //获取属性价格
            $sku_price = Db::name('goods_sku')->where('sku_id',$row['sku_id'])->find();
			//0:减价 1：折扣
			if($row['price_type'] == 1){
				if($row['price_reduce'] == 0){
					//$row['team_price'] = $result['price'];
					$row['team_price'] = $sku_price['price'];
				}else{
                    //$row['team_price'] = ($row['price'] * $row['price_reduce']/100);
                    $row['team_price'] = ($sku_price['price'] * $row['price_reduce']/100);
                }

			}else{
				$row['team_price'] = ($sku_price['price'] - $row['price_reduce']);
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
            if(!$row['sku_id']){
                return (['code'=>0,'msg'=>'请选择商品属性','data'=>'请选择商品属性']);
            }
            //获取属性价格
            $sku_price = Db::name('goods_sku')->where('sku_id',$row['sku_id'])->find();
			//0:减价 1：折扣
			if($row['price_type'] == 1){
				if($row['price_reduce'] == 0){
					//$row['team_price'] = $row['price'];
					$row['team_price'] = $sku_price['price'];
				}else{
					//$row['team_price'] = ($row['price'] * $row['price_reduce']/100);
					$row['team_price'] = ($sku_price['price'] * $row['price_reduce']/100);
				}
				
			}else{
				//$row['team_price'] = abs($row['price'] - $row['price_reduce']);
				$row['team_price'] = abs($sku_price['price'] - $row['price_reduce']);
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
	/**
     * 商品评价列表
     */
    public function evaluation()
    {
		$user_name =  input('user_name');
		$goods_name =  input('goods_name');
        $start_time = input('start_time');
        $end_time = input('end_time');
        $status = input('status');
        $this->assign('user_name',$user_name);
        $this->assign('goods_name',$goods_name);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('status', $status);
        $GoodsService=new GoodsService();
		if(request()->isAjax()){
			//排序
			$order="or_id desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
			//回收站
		 
			if(input('user_name')){
				$where2 = [
				
				'user_name'=>['like','%'.input('user_name').'%']
				];
				$res = Db::name('users')->where($where2)->field('user_id')->find();
				if($res){
					 $map['or_uid'] = $res['user_id'];
					
				}else{
					return json(['total'=>'','rows'=>'']);
				}
			
			}
			if(input('goods_name')){
				$where3 = [
					'goods_name'=>['like','%'.input('goods_name').'%']
				];
				$res = Db::name('goods')->where($where3)->field('goods_id')->find();
				if($res){
					 $map['or_og_id'] = $res['goods_id'];
				}else{
					return json(['total'=>'','rows'=>'']);
				}
			}
			if(input('start_time')){
				 $start_time = str_replace('+',' ',input('start_time'));
			}
			//0:待审核 1:审核通过;2:审核不通过
			if(input('status')){
				 $status = input('status') == 3?0:input('status');
				 $map['status'] = array('eq',$status);
			}

		    if(input('end_time')){
				 $end_time = str_replace('+',' ',input('end_time'));
			}
            if ($start_time && $end_time) {
                $map['or_add_time'] = array('between',strtotime($start_time).','.(strtotime($end_time)));
            } elseif ($start_time) {
                $map['or_add_time'] = array('>=',strtotime($start_time));
            } elseif ($end_time) {
                $map['or_add_time'] = array('<=', (strtotime($end_time)));
            }
		
			$rows=$GoodsService->begEvales($order,$limit,$map);

			if ($rows) {
                $status_list = array(0=>'待审核',1=>'审核通过',2=>'审核不通过');
                foreach ($rows as &$val) {
					$user = Db::name('users')->where('user_id',$val['or_uid'])->field('user_name')->find();
					$goods = Db::name('goods')->where('goods_id',$val['or_goods_id'])->field('goods_name')->find();
					$val['goods_name'] = $goods['goods_name'];
					$val['user_name'] = $user['user_name'];
					$val['or_add_time'] = date('Y-m-d H:i:s',$val['or_add_time']);
                    $val['status'] = $status_list[$val['status']];
					
                }
            } 
			$total = count($GoodsService->begEvales('','',$map));
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}    	
    }
	/**
     * 商品评价审核 
     */
    public function editEvalu()
    {
       $GoodsService=new GoodsService();

		if(request()->isAjax()){
			$row = input('post.row/a');
            $map['or_id'] = input('post.or_id');
            $res = $GoodsService->updateEvalu($map,$row);

            //添加日志记录
            $this->write_log('商品评价审核修改',$map['or_id']);

			return AjaxReturn($res);
		}else{
 
			$map=[];
			$map['or_id']=input('get.ids');
			$row=$GoodsService->editEvalu($map);
			if($row){
				$row['or_add_time'] = date('Y-m-d H:i:s',$row['or_add_time']);
				$row['or_thumb'] = explode(',',rtrim($row['or_thumb'],';'));
				if(!$row['or_thumb'][0]){
					$row['or_thumb'] ='';
				}
			}
			 
			$this->assign('row',$row);
			return $this->fetch();
		}   		
    }
	
	/**
     * 商品评价删除
     */
    public function deleteEvalu()
    {
		$GoodsService=new GoodsService();
        $ids=input('get.ids');
        $where['or_id'] = array('in', $ids);
        $res=$GoodsService->deleteEvalu($where);

        //添加日志记录
        $this->write_log('商品评价删除',$ids);

        return AjaxReturn($res);
		
	}
	/**
     * 商品评价详情
     */
    public function evalushow()
    {		
		$GoodsService=new GoodsService();
		$map=[];
		$map['or_id']=input('get.ids');
		$row=$GoodsService->editEvalu($map);
	 
		if($row){
			$row['or_add_time'] = date('Y-m-d H:i:s',$row['or_add_time']);
			$row['or_thumb'] = explode(',',rtrim($row['or_thumb'],';'));
			if(!$row['or_thumb'][0]){
				$row['or_thumb'] ='';
			}
		}
		 
		$this->assign('row',$row);
		return $this->fetch();
	}
	/**
     * 商品库存预警
     */
    public function warning()
    {	 $goods_numbers = trim(input('goods_numbers'));
		 $goods_name =  input('goods_name');
		 $supplier_title =  trim(input('supplier_title'));
		 $category_name =  trim(input('category_name'));
		 $this->assign('goods_name',$goods_name);
		 $this->assign('category_name',$category_name);
		 $this->assign('supplier_title',$supplier_title);
		 $this->assign('goods_numbers',$goods_numbers);
		$GoodsService=new GoodsService();
		if(request()->isAjax()){
			//排序
			$order="stock asc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
			
			if(input('get.search')){
				$map['b.goods_name']=['like','%'.input('get.search').'%'];
			}		
			if(input('goods_name')){
				$map['b.goods_name']= ['like','%'.input('goods_name').'%'];
			}
			if(input('goods_numbers')){
				$map['b.goods_numbers']=  input('goods_numbers');
			}
			if(input('category_name')){
				$res = Db::name('goods_category')->where('category_name',input('category_name'))->find();
				if($res){
					$map['b.category_id'] = $res['category_id'];
				}else{
					return json(['total'=>'','rows'=>'']);
				}
			}
			if(input('supplier_title')){
				$res = Db::name('supplier')->where('supplier_title',input('supplier_title'))->find();
				 if($res){
					$map['b.supplier_id']=  $res['supplier_id'];
				}else{
					return json(['total'=>'','rows'=>'']);
				}
			}
			$rows = $GoodsService->stockWarn($map,$order,$limit);
			if ($rows) {
                $status_list = array(0=>'上架',1=>'下架',2=>'回收站');
                foreach ($rows as &$val) {
					$val['create_time'] = date('Y-m-d H:i:s',$val['create_time']);
                    $val['status'] = $status_list[$val['status']];
                }
            } 
			$num = $GoodsService->WarnNum($map,$order); 
			$total = count($num);
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}    	
	}
	/**
     * 商品库存预警修改
     */
    public function warningEdit()
    {		
		$GoodsService=new GoodsService();

		if(request()->isAjax()){
			$row = input('post.row/a');
            $map['sku_id'] = input('post.sku_id');
            $map['goods_id'] = input('post.goods_id');
            $res = $GoodsService->updateWarn($map,$row);

            //添加日志记录
            $this->write_log('商品库存预警修改',$map);

			if($res !== false){
				return AjaxReturn(true);
			}
			return AjaxReturn($res);
		}else{
 
			$map=[];
			$map['sku_id']=input('get.ids');
			$row=$GoodsService->getWarn($map);
			$this->assign('row',$row);
			return $this->fetch();
		}   		
	}
	/**
     * 商品库存预警操作
     */
    public function warnMulti()
    {		
		$action = input('action');
   		$ids = input('get.ids/a');
//		$array = implode(',',$ids);
		$GoodsService=new GoodsService();
		if(!$action){
			return AjaxReturn(UPDATA_FAIL);   
		}
		/*$arr = explode(',',$array);
		$info = [];
		foreach($arr as $key=>$val){
			$where = [
				'sku_id'=>$val
			];
			$result =  Db::name('goods_sku')->field('goods_id')->find($where);
			$info[$key] = $result['goods_id'];
		 }*/
        $array =  Db::name('goods_sku')->where(['sku_id'=>['in',$ids[0]]])->column('goods_id');
		if(!$array){
            return AjaxReturn(UPDATA_FAIL);
        }
//		$array = implode(',',$info);
		$val = input('params');
		//商品列表
		$map['goods_id']=['in',$array];
		$data['status'] = $val;
		$res= Db::name('goods')->where($map)->update($data);

        //添加日志记录
        $this->write_log('商品库存预警操作',$map['goods_id']);

		return AjaxReturn($res);
	}
	/**
     * 商品库存预警展示
     */
    public function warningShow()
    {		
		$GoodsService=new GoodsService();
		if(request()->isAjax()){
			//排序
			$order="stock desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
			$rows = $GoodsService->stockWarn($order,$limit);
			if ($rows) {
                $status_list = array(0=>'上架',1=>'下架',2=>'回收站');
                foreach ($rows as &$val) {
					$val['create_time'] = date('Y-m-d H:i:s',$val['create_time']);
                    $val['status'] = $status_list[$val['status']];
                }
            } 
			$total = count($rows);
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}    	
	}
	/**
     * 积分商品列表
     */
    public function integration()
    {		
		$GoodsService=new GoodsService();
		if(request()->isAjax()){
			//排序
			$order="exchange_integral desc";
			//limit
			$limit=input('get.offset').",".input('get.limit');
			$map = [
				'exchange_integral'=>['gt',0],
				'status'=>['neq',3]
			];
			$rows=$GoodsService->select($map,'*',$order,$limit);
			if ($rows) {
                $status_list = array(0=>'上架',1=>'下架',2=>'回收站');
                foreach ($rows as &$val) {
					$val['create_time'] = date('Y-m-d H:i:s',$val['create_time']);
                    $val['status'] = $status_list[$val['status']];
                }
            } 
			$total = count($rows);
			return json(['total'=>$total,'rows'=>$rows]);
		}else{
			return $this->fetch();
		}    	
	}
    /**
     * 活动设置标签
     */
    public function setlabel(){
        if (request()->isAjax()) {
            $active_label = input('request.active_label', '');
            $data['active_label'] = $active_label;
            $map = ['id' => ['gt', 0]];
            $res= Db::name('active_type')->where($map)->update($data);
            $map['id'] = implode(',',$map['id']);
            //添加日志记录
            $this->write_log('修改活动标签',$map['id']);

            return AjaxReturn($res);
        } else {
            return $this->fetch();
        }
    }
}
