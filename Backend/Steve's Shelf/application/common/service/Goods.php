<?php
namespace app\common\service;

use app\common\model\Goods as GoodsModel;
use app\common\model\GoodsSpec as GoodsSpecModel;
use app\common\model\FullGoods as FullGoodsModel;
use app\common\model\GoodsSku as GoodsSkuModel;
use app\common\model\GoodsCategory as GoodsCategoryModel;
use app\common\model\GroupGoods as GroupGoodsModel;
use app\common\model\GoodsSpecValue as GoodsSpecValueModel;
use app\common\model\GoodsSubject as GoodsSubjectModel;
use app\common\model\GoodsSubjectValue as GoodsSubjectValueModel;
use app\common\model\Attribute as AttributeModel;
use app\common\model\GoodsNewuser as GoodsForFresh;
use app\common\model\Bargain as BargainModel;
use app\common\model\GoodsBrand as GoodsBrand;
use app\common\service\Order as OrderService;
use app\common\model\Coupon as Coupon;
use app\common\service\User as UserService;
use getui\Pushs;
use QRcode\QRcode;

use think\Db;


class Goods extends Base{

	public function __construct(){
		parent::__construct();
		$GoodsModel = new GoodsModel();
		$this->model = $GoodsModel;
		$this->cart = new \app\common\service\Cart();
	}

	/*
	* 添加商品
	*/
	public function add($data,$sku){
		if($sku){
			//获取sku商品信息
			list($data,$sku)=$this->getSkuData($data,$sku);
		}
		// 启动事务g
		Db::startTrans();
		try{
			//写入主表数据
			$data['create_time']=time();
			$goods_id=Db::name('goods')->insertGetId($data);
			//写入sku表
			if($sku){
				$sku=array_map(function($v) use ($goods_id){
					$v['goods_id']=$goods_id;
					return $v;
				},$sku);
				Db::name('goods_sku')->insertAll($sku);
			}
		    // 提交事务
		    Db::commit();
		    return $goods_id;
		} catch (\Exception $e) {
            // 回滚事务
		    Db::rollback();
		    return $e->getMessage();
		}
	}

	/*
	* 编辑商品
	*/
	public function save($goods_id,$data,$sku){
		if($sku){
			//获取sku商品信息
			list($data,$sku)=$this->getSkuData($data,$sku);
		}
		// 启动事务
		Db::startTrans();
		try{
			//写入主表数据
			$data['update_time']=time();
			Db::name('goods')->where(['goods_id'=>$goods_id])->update($data);
			//写入sku表
			if($sku){
				//清空sku
				$sku_lisy = Db::name('goods_sku')->where(['goods_id'=>$goods_id])->select();
                Db::name('goods_sku')->where(['goods_id'=>$goods_id])->update(['status'=>0]);

				//重新保存
				$sku = array_map(function($v) use ($goods_id){
					$v['goods_id']=$goods_id;
					return $v;
				},$sku);
                $sku_code = [];
                if ($sku_lisy) {
                    //$sku_code = array_column($sku_lisy, 'code');
                    $sku_code = array_column($sku_lisy, 'sku_id');
                }
				foreach($sku as $key=>$val){
					//if(in_array($val['code'], $sku_code)){
                    $val['status']=1;
					if(in_array($val['sku_id'], $sku_code)){
                        Db::name('goods_sku')->where(['sku_id'=>$val['sku_id']])->update($val);
					} else {
                        Db::name('goods_sku')->insert($val);
                    }
				}

				//清除被删除的规格已加入购物车数据
                $sku_lisy = Db::name('goods_sku')->where(['goods_id'=>$goods_id,'status'=>0])->select();
				if($sku_lisy){
                    $sku_ids = $this->buildIds($sku_lisy,'sku_id');
                    Db::name('cart')->where(['goods_id'=>$goods_id])->whereIn('item_id',$sku_ids)->delete();
                }
			}
		    // 提交事务
		    Db::commit();
		    return $goods_id;			
		} catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
		    return $e->getMessage();
		}				
	}

    /**
     * 创建 IDs，可用于 IN 查询
     * @param array $array 查询结果 数组
     * @param string $key 待创建的 字段（int 型）
     * @return string 可直接用于 IN 查询的条件
     */
    public  function buildIds(array $array, $column, $expiry = ',')
    {
        if (empty($array)) {
            return "";
        }
        return join($expiry, array_filter(array_unique(array_column($array, $column))));
    }

	/*
	* 删除商品
	*/
	public function delete($map){
		Db::startTrans();
		try{
			Db::name('goods')->where($map)->delete();
			Db::name('goods_sku')->where($map)->delete();
		    // 提交事务
		    Db::commit();			
			return SUCCESS;
		} catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
		    return $e->getMessage();
		}		
	}

	/*
	* 获取sku商品信息
	*/
	public function getSkuData($data,$sku){
		$price=0;
		$stock=0;
		$new_sku=[];
		//商品规格信息
		$spec_format=$this->getSpecFormat();
		$spec_value_format=$this->getSpecValueFormat();
		$i = 0;
		foreach ($sku as $key => $value) {
			$sku_data=[];
			$_attr_value=[];
			$attr_value=[];
			$attr_value_array=[];
			//自动商品主图
			if(!$data['picture']&&$value['image']){
				$data['picture']=$value['image'];
			}		
			//价格处理
//			if($price==0||$value['price'] >= $price){
//				$price=$value['price'];
//			}
            $i++;
			//库存处理
			$stock+=$value['stock'];
			//规格排序
			foreach (explode(';',$key) as $k => $v) {
				$arr=explode(':',$v);
				$_attr_value[$arr[0]]=$arr;
			}
			sort($_attr_value);
			foreach ($_attr_value as $k => $v) {
				$attr_value[]=implode(":",$v);
				$attr_value_array[]=$v;
			}
			//sku 名称处理
			$sku_name=array_map(function($v) use ($spec_value_format){
				return $spec_value_format[$v[1]]['spec_value_name'];
			},$attr_value_array);
			$sku_name=implode("/",$sku_name);
			//sku 数据
			$sku_data=[
			    'sku_id'=>$value['sku_id'],
				'code'=>$value['code'],
				'sku_name'=>$sku_name,
				'attr_value'=>implode(";",$attr_value),
				'attr_value_array'=>json_encode($attr_value_array),
				'price'=>$value['price'],
                'show_price'=>$value['show_price'],
                'cost_price'=>$value['cost_price'],
				'stock'=>$value['stock'],
                'weight'=>$value['weight'],
				'image'=>$value['image'],
				'create_time'=>time(),
                'integral'=>$value['integral'],
                'all'=>$value['all'],
                'gift'=>$value['gift'],
			];
			$new_sku[]=$sku_data;
		}
		//商品主表信息
		$data['stock']=$stock;
		$spec=[];
		foreach ($data['spec'] as $k => $v) {
			$_spec=$spec_format[$k];
			$_spec['values']=array_map(function($v) use ($spec_value_format){
				return $spec_value_format[$v];
			},$v);
			$spec[]=$_spec;
		}
		$data['vip_price'] = ceil($data['price'] * (100 - $data['commission']))/100;
		$data['recommend']=json_encode($data['recommend']);
		$data['spec']=json_encode($data['spec']);
		$data['spec_array']=json_encode($spec);
		return [$data,$new_sku];
	}

	/*
	* 获取商品规格格式化信息
	*/
	public function getSpecFormat(){
		$GoodsSpecModel=new GoodsSpecModel();
		$this->model=$GoodsSpecModel;	
		$rows=parent::select();
		foreach ($rows as $key => $value) {
			$_data[$value->spec_id]=$value->toArray();
		}	
		return $_data;
	}

	/*
	* 获取商品规格属性值格式化信息
	*/
	public function getSpecValueFormat(){
		$GoodsSpecValueModel=new GoodsSpecValueModel();
		$this->model=$GoodsSpecValueModel;	
		$rows=parent::select();
		foreach ($rows as $key => $value) {
			$_data[$value->spec_value_id]=$value->toArray();
		}	
		return $_data;
	}	

	/*
	* 获取商品规格列表
	*/
	public function getSpecList($map=[],$field="*",$order="",$limit="",$join=""){
		$GoodsSpecModel=new GoodsSpecModel();
		$this->model=$GoodsSpecModel;
		$count=parent::count($map);
		$rows=parent::select($map,$field,$order,$limit,$join);
		foreach ($rows as &$value) {
			$value['values']=$value->values;
		}
		return ['total'=>$count,'rows'=>$rows];
	}

	/*
	* 添加商品规格
	*/
	public function addSpec($data,$value){
		$GoodsSpecModel=new GoodsSpecModel();
		$spec_id=$GoodsSpecModel->insertGetId($data);
		if(!$spec_id){
			return false;
		}
		$arr=[];
		foreach ($value as $v) {
			$arr[]=[
				'spec_id'=>$spec_id,
				'spec_value_name'=>$v,
				'create_time'=>time()
			];
		}
		$GoodsSpecValueModel=new GoodsSpecValueModel();
		$res=$GoodsSpecValueModel->insertAll($arr);
		return $res?1:0;
	}

	/*
	* 更新商品规格信息
 	*/
 	public function updateSpec($spec_id,$data,$value){
 		$map['spec_id']=$spec_id;
 		$GoodsSpecModel=new GoodsSpecModel();
		$this->model=$GoodsSpecModel;
		//获取原始信息
		$old_data=parent::find($map);
		//更新基础信息
		$res=parent::save($map,$data);	
		//添加新增属性
		$GoodsSpecValueModel=new GoodsSpecValueModel();
		$arr=[];
		foreach ($value as $k=>$v) {
			if(!$GoodsSpecValueModel->where(array('spec_id' => $spec_id,'spec_value_name' => $v))->find()){
				$arr[]=[
					'spec_id'=>$spec_id,
					'spec_value_name'=>$v,
					'create_time'=>time()
				];
			}
		}
		if($arr){
			$res1=$GoodsSpecValueModel->insertAll($arr);
		}
		//更新商品相关信息
		if($res&&$old_data['spec_name']!=$data['spec_name']){
			//处理新旧数据
			$str=str_replace("\\","\\\\",unicode_encode($old_data['spec_name']));
			$str1=str_replace("\\","\\\\",unicode_encode($data['spec_name']));
	 		//获取规格所属分类
	 		$attrList=Db::name('attribute')->whereOr('spec_id_array',$spec_id)->whereOr('spec_id_array','like','%,'.$spec_id.',%')->whereOr('spec_id_array','like','%,'.$spec_id)->whereOr('spec_id_array','like',$spec_id.',%')->field('attr_id')->fetchSql(false)->select();
	 		$attr_ids=array_map(function($v){
	 			return $v['attr_id'];
	 		},$attrList);
	 		//修改该分类关联规格信息
	 		$update_sql='update '.config('database.prefix').'goods set spec_array=REPLACE (spec_array,'."'".'"'.$str.'"'."'".','."'".'"'.$str1.'"'."'".') WHERE attr_id in ('.implode(",",$attr_ids).')';
	 		Db::execute($update_sql);
		}
		return ($res||$res1)?1:0;
 	}

	/*
	* 获取商品规格信息
	*/
	public function getSpecInfo($map){
		$GoodsSpecModel=new GoodsSpecModel();
		$this->model=$GoodsSpecModel;
		return parent::find($map);
	}
    /*
    * 获取商品参数列表
    */
    public function getSubjectList($map=[],$field="*",$order="",$limit="",$join=""){
        $GoodsSubjectModel=new GoodsSubjectModel();
        $this->model=$GoodsSubjectModel;
        $count=parent::count($map);
        $rows=parent::select($map,$field,$order,$limit,$join);
        foreach ($rows as &$value) {
            $value['values']=$value->values;
        }
        return ['total'=>$count,'rows'=>$rows];
    }
	/*
	* 删除商品规格
	*/
	public function deleteSpec($ids){
		$map['spec_id']=['in',$ids];
 		$GoodsSpecModel=new GoodsSpecModel();
		$this->model=$GoodsSpecModel;
		//删除基础信息
		$res=parent::delete($map);
		//删除属性信息
		$GoodsSpecValueModel=new GoodsSpecValueModel();
		$res1=$GoodsSpecValueModel->where($map)->delete();		
		return ($res||$res1)?1:0;	
	}

	/*
	* 删除规格属性
	*/
	public function deleteSpecValue($ids){
		$map['spec_value_id']=['in',$ids];
		$GoodsSpecValueModel=new GoodsSpecValueModel();
		$res=$GoodsSpecValueModel->where($map)->delete();		
		return ($res)?1:0;	
	}
    /*
    * 添加商品规格
    */
    public function addSubject($data,$value){
        $GoodsSpecModel=new GoodsSubjectModel();
        $spec_id=$GoodsSpecModel->insertGetId($data);
        if(!$spec_id){
            return false;
        }
        $arr=[];
        foreach ($value as $v) {
            $arr[]=[
                'subject_id'=>$spec_id,
                'subject_value_name'=>$v,
                'create_time'=>time()
            ];
        }
        $GoodsSpecValueModel=new GoodsSubjectValueModel();
        $res=$GoodsSpecValueModel->insertAll($arr);
        return $res?1:0;
    }
	/*
	* 编辑规格属性
	*/
	public function editSpecValue($spec_value_id,$spec_value_name){
		$map['spec_value_id']=$spec_value_id;
		$GoodsSpecValueModel=new GoodsSpecValueModel();
		$data['spec_value_name']=$spec_value_name;
		$data['update_time']=time();
		$res=$GoodsSpecValueModel->where($map)->update($data);		
		return ($res)?1:0;	
	}
    /*
    * 编辑商品主体属性
    */
    public function editSubjectValue($subject_value_id,$subject_value_name){
        $map['subject_value_id']=$subject_value_id;
        $GoodsSubjectValueModel=new GoodsSubjectValueModel();
        $data['subject_value_name']=$subject_value_name;
        $data['update_time']=time();
        $res=$GoodsSubjectValueModel->where($map)->update($data);
        return ($res)?1:0;
    }
    /*
    * 更新商品属性
     */
    public function updateSubject($subject_id,$data,$value){
        $map['subject_id']=$subject_id;
        $GoodsSubjectModel=new GoodsSubjectModel();
        $this->model=$GoodsSubjectModel;
        //获取原始信息
        $old_data=parent::find($map);
        //更新基础信息
        $res=parent::save($map,$data);
        //添加新增属性
        $GoodsSubjectValueModel=new GoodsSubjectValueModel();
        $arr=[];
        foreach ($value as $k=>$v) {
            if(!$GoodsSubjectValueModel->where(array('subject_id' => $subject_id,'subject_value_name' => $v))->find()){
                $arr[]=[
                    'subject_id'=>$subject_id,
                    'subject_value_name'=>$v,
                    'create_time'=>time()
                ];
            }
        }
        if($arr){
            $res1=$GoodsSubjectValueModel->insertAll($arr);
        }
        //更新商品相关信息
        if($res&&$old_data['title']!=$data['title']){
            //处理新旧数据
            $str=str_replace("\\","\\\\",unicode_encode($old_data['title']));
            $str1=str_replace("\\","\\\\",unicode_encode($data['title']));
            //获取规格所属分类
            $attrList=Db::name('attribute')->whereOr('subject_id_array',$subject_id)->whereOr('subject_id_array','like','%,'.$subject_id.',%')->whereOr('subject_id_array','like','%,'.$subject_id)->whereOr('subject_id_array','like',$subject_id.',%')->field('attr_id')->fetchSql(false)->select();
            $attr_ids=array_map(function($v){
                return $v['attr_id'];
            },$attrList);
            //修改该分类关联规格信息
            $update_sql='update '.config('database.prefix').'goods set spec_array=REPLACE (spec_array,'."'".'"'.$str.'"'."'".','."'".'"'.$str1.'"'."'".') WHERE attr_id in ('.implode(",",$attr_ids).')';
            Db::execute($update_sql);
        }
        return ($res||$res1)?1:0;
    }
    /*
* 获取商品属性信息
*/
    public function getSubjectInfo($map){
        $GoodsSpecModel=new GoodsSubjectModel();
        $this->model=$GoodsSpecModel;
        return parent::find($map);
    }
    /*
    * 删除商品主体属性
    */
    public function deleteSubject($ids){
        $map['subject_id']=['in',$ids];
        $GoodsSubjectModel=new GoodsSubjectModel();
        $this->model=$GoodsSubjectModel;
        //删除基础信息
        $res=parent::delete($map);
        //删除属性信息
        $GoodsSubjectValueModel=new GoodsSubjectValueModel();
        $res1=$GoodsSubjectValueModel->where($map)->delete();
        return ($res||$res1)?1:0;
    }
    /*
    * 删除商品属性
    */
    public function deleteSubjectValue($ids){
        $map['subject_value_id']=['in',$ids];
        $GoodsSubjectValueModel=new GoodsSubjectValueModel();
        $res=$GoodsSubjectValueModel->where($map)->delete();
        return ($res)?1:0;
    }
	/*
	* 获取商品类型
	*/
	public function getAttributeList($map=[],$field="*",$order="",$limit="",$join=""){
		$AttributeModel=new AttributeModel();
		$this->model=$AttributeModel;
		$count=parent::count($map);
		$rows=parent::select($map,$field,$order,$limit,$join);
		return ['total'=>$count,'rows'=>$rows];		
	}

	/*
	* 添加商品类型
	*/
	public function addAttribute($data){
		$data['spec_id_array']=implode($data['spec_id_array'], ',');
        $data['subject_id_array']=implode($data['subject_id_array'], ',');
		$AttributeModel=new AttributeModel();
		$this->model=$AttributeModel;
		return parent::add($data);		
	}

	/*
	* 更新商品类型
	*/
	public function updateAttribute($map,$data){
		$data['spec_id_array']=implode($data['spec_id_array'], ',');
        $data['subject_id_array']=implode($data['subject_id_array'], ',');
		$AttributeModel=new AttributeModel();
		$this->model=$AttributeModel;
		return parent::save($map,$data);				
	}

	/*
	* 获取商品类型详情
	*/
	public function getAttributeInfo($map){
		$AttributeModel=new AttributeModel();
		$this->model=$AttributeModel;
		$info=parent::find($map);		
		return $info;
	}
	/*
	* 删除商品类型
	*/
	  public function deleteAttribute($ids){
		$AttributeModel=new AttributeModel();
		$this->model=$AttributeModel;
		$info=$this->model->where('attr_id',$ids)->delete();		
		return $info;
	}  

	public function getGoodsListBySkuId($map){
		$GoodsSkuModel=new GoodsSkuModel();
		$this->model=$GoodsSkuModel;
		$list=parent::select($map);
		foreach ($list as &$value) {
			$value->goods->toArray();
		}
		return $list;
	}

	/*
	* 获取商品sku信息
	*/	
	public function getSkuInfo($map){
		$GoodsSkuModel=new GoodsSkuModel();
		$this->model=$GoodsSkuModel;
		$info=parent::find($map);
		if($info){
			$info['goods']=$info->goods;
		}
		return $info;
	}
    /**
     * 获取秒杀商品
     */
    public function getMiaoshalist()
    {
		$start_time = strtotime(date("Y-m-d",time()));
        $end_time = strtotime(date("Y-m-d 23:59:59",time()));
//        $res = Db::name('active_type')->where(['id' => 5, 'start_time' => ['<', $start_time], 'end_time' =>['>', $end_time], 'status' => 0] )->find();
//        if (!$res) {
//            return '';
//        }

        $hour = time();
        $where = [
            'start_time' => ['elt', $hour],
            'end_time' => ['gt', $hour],
            'status' => 0
        ];
 
        $res = Db::name('flash_active')->where($where)->order('id desc')->find();
        if(!$res) {
//            $res = Db::name('flash_active')->where(['start_time' => ['gt', $hour], 'status' => 0])->find();
            return [];
        }

        $goods_where = [
            'flash_id' => $res['id'],
            'is_end' => 0,
        ];
     //   $goods_list = Db::name('flash_goods')->where($goods_where)->limit(9)->select();
        $goods_list = Db::name('flash_goods')->where($goods_where)->select();
		//下单量大于库存 改变状态为结束
        if ($goods_list) {
            foreach ($goods_list as &$val) {
				if($val['order_number'] >= $val['goods_number']){
					Db::name('flash_goods')->where(['id' => ['eq',$val['id']]])->update(['is_end'=>1]);
				}
            }
        }
        //$goods_list = Db::name('flash_goods')->alias('a')->join('goods b','a.goods_id=b.goods_id')->where(['b.status'=>0,'a.flash_id' => $res['id'],'a.is_end' => 0,])->limit(9)->select();
        $goods_list = Db::name('flash_goods')->alias('a')->join('goods b','a.goods_id=b.goods_id')->where(['b.status'=>0,'a.flash_id' => $res['id'],'a.is_end' => 0,])->select();
        if ($goods_list) {
            foreach ($goods_list as &$val) {
                $goods_info = $this->getGoodsinfo($val['goods_id']);
                $val['goods_name'] = $goods_info['goods_name'];
                $val['price'] = $goods_info['price'];
                $val['vip_price'] = $goods_info['vip_price'];
                if ($val['sku_id']){
                    $val['show_price'] = Db::name('goods_sku')->where('sku_id',$val['sku_id'])->value('price');
                }else{
                    $val['show_price'] = $goods_info['show_price'];
                }
                $val['active_price'] = $val['limit_price'];//活动秒杀价格
                $val['commission'] = $goods_info['commission'];
                $val['picture'] = $goods_info['picture'];
				$active_price = $this->getActivePirce($goods_info['price'],$goods_info['prom_type'],$goods_info['prom_id']);
					$commission = $this->getCom();
					//开启 返利
					if($commission['shop_ctrl'] == 1){
						$f_p_rate = $commission['f_s_rate'];
					}else{
						$f_p_rate = 100; 
					}
					$val['dianzhu_price'] = floor($active_price * $goods_info['commission']/ 100 * $f_p_rate)/100;
				if($val['order_number'] >= $val['goods_number']){
					$val['is_end'] = 1;
				}
				$val['vip_price'] = sprintf('%0.2f', $val['vip_price']);
				$val['vip_price'] = floatval($val['vip_price']);
				$val['show_price'] = sprintf('%0.2f', $val['show_price']);
				$val['show_price'] = floatval($val['show_price']);
				$val['dianzhu_price'] = sprintf('%0.2f', $val['dianzhu_price']);
				$val['dianzhu_price'] = floatval($val['dianzhu_price']);
				if(empty($goods_info['commission'])){
					$val['dianzhu_price'] = 0.01;
				}
				$val['price'] = sprintf('%0.2f', $val['price']);
				$val['price'] = floatval($val['price']);
            }
        }
        $data = [
            'start_time' => date("Y-m-d H:i:s",$res['start_time']),
            'end_time' => date("Y-m-d H:i:s",$res['end_time']),
            'active_id' => 5,
			'active_type_name' => $this->ActiveTitle(5),
            'list' => $goods_list
        ];
        return $data;
    }
    /**
     * 获取商品信息
     */
    public function getGoodsinfo($goods_id, $field = '*') {
        $goods_info = Db::name('goods')->field($field)->where(array('goods_id' => $goods_id))->find();
        return $goods_info;
    }
	/*
	 * 根据活动获取商品列表
	 */
	public function getGoodsList($type, $map = [], $field = '*'){
		// $this->model = new Goods();
		$list = [];
		$time = time();
		//新人专享
		if($type == 'fresh'){
			$this->model = new GoodsForFresh();
			$list = $this->model->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->join('ht_goods_category c','a.item_id=c.category_id')->field('a.goods_price,a.buy_limit,b.*,c.*')->select();
		}
		//首页限时秒杀
		else if($type == 'limit'){
			$where = [
				'c.start_time' => ['lt', $time],
				'c.end_time' => ['gt', $time],
				'b.is_end' => 0
			];
			$list = $this->model->alias('a')->join('__FLASH_GOODS__ b', 'a.goods_id=b.goods_id', 'RIGHT')->join('__FLASH_ACTIVE__ c', 'b.flash_id=c.id','RIGHT')->field('a.picture,a.price,a.goods_name,b.goods_id,b.item_id,b.limit_price')->where($where)->limit(9)->select();
		}
		//全部限时秒杀
		else if($type == 'limitall'){
			$where = [
				'c.start_time' => ['egt', strtotime(date('Y-m-d 00:00:00', $time))],
				'c.end_time' => ['elt', strtotime(date('Y-m-d 23:59:59', $time))]
			];
			$list = $this->model->alias('a')->join('__FLASH_GOODS__ b', 'a.goods_id=b.goods_id', 'RIGHT')->join('__FLASH_ACTIVE__ c', 'b.flash_id=c.id','RIGHT')->field('a.*,b.buy_limit,b.goods_number,b.attr_goods_number,b.is_end,b.order_number,c.*')->where($where)->select();
		}
		//拼团
		else if($type == 'groupbuy'){
			$where = [
				'b.status' => 0
			];
			$list = $this->model->alias('a')->join('__TEAM_ACTIVITY__ b', 'a.goods_id=b.goods_id', 'RIGHT')->field('a.price,a.picture,b.*')->where($where)->order('b.sort desc')->select();
		}
		//砍价
		else if($type == 'bargain'){
			$where = [
				'b.status' => 0
			];
			$list = $this->model->alias('a')->join('__BARGAIN__ b', 'a.goods_id=b.goods_id', 'RIGHT')->field('a.goods_name,a.price,a.picture,b.*')->where($where)->select();
		}
		//热销爆品
		else if($type == 'hot'){
			$where = [
				'is_hot' => 1
			];
			$list = parent::select($where);
		}
		else{
			return ['code' => 0];
		}

		return ['data' => $list, 'code' => 200];
	}

	/*
	 * 根据分类获得商品列表
	 */
	public function getListByCate($cate_id, $uid = 0,  $p = 1, $where = [], $order = []){
		//是否是店主
		if($uid){
			$user_info = Db::name('users')->where('user_id', $uid)->field('is_seller')->find();
		}
		$ord = '';
		if($order['order_sv']){
			$ord .= 'volume desc,';
		}
		if($order['order_new']){
			$ord .= 'weigh desc,';
		}
		if(!is_null($order['order_price'])){
			$ord .= ($order['order_price'] == 1 ? 'weigh desc,' : 'price asc,');
		}
		if(strripos($ord, ',') == (strlen($ord) - 1)){
			$ord = substr($ord, 0, -1);
		}

		$map = [
			'status' => 0,
			'category_id' => $cate_id,
		];
		$cate_info = Db::name('goods_category')->where(['category_id' => $cate_id, 'status' =>'normal'])->field('pid,category_id,category_name')->find();
		$cate = [];		//分类信息
		if($cate_info['pid'] == 0){
			unset($cate_info['pid']);
			$cate['first_cate'] = $cate_info;
			$tmp = Db::name('goods_category')->where(['pid' => $cate_id, 'status' =>'normal'])->field('category_id,category_name')->order('weigh asc')->select();
			$cate['second_cate'] = $tmp;
			if($tmp){
				$str = '';
				foreach($tmp as $v){
					$str .= $v['category_id'].',';
				}
				if($str){
					$str = substr($str, 0, -1);
				}
				$map['category_id'] = ['in', $str];
			}
		}
		else{
			$cate['first_cate'] = Db::name('goods_category')->where('category_id', $cate_info['pid'])->field('category_id,category_name')->find();
			$cate['second_cate'] = Db::name('goods_category')->where('pid', $cate_info['pid'])->field('category_id,category_name')->order('weigh asc')->select();
		}
		if($where['goods_brand']){
			$map['brandid'] = ['in', explode(',', $where['goods_brand'])];
		}

		$map['is_gift'] = 0;
		$map['prom_type'] = 0;
		$num = 10;
		$s = ($p - 1) * $num;
		$goods_list = $this->model->where($map)->field('goods_id,goods_name,spec,spec_array,prom_id,prom_type,vip_price,price,show_price,picture,commission,stock,active_name')->order($ord)->limit($s, $num)->select();
		$total = $this->model->where($map)->count();
        if($goods_list){
            foreach ($goods_list as &$value) {
                $value['vip_price'] = ceil($value['price'] * (100 - $value['commission'])) / 100;
					$active_price = $this->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
					$commission = $this->getCom();
					//开启 返利
					if($commission['shop_ctrl'] == 1){
						$f_p_rate = $commission['f_s_rate'];
					}else{
						$f_p_rate = 100; 
					}
					$value['dianzhu_price'] = floor($active_price * $value['commission']/ 100 * $f_p_rate)/100;
					if($value['prom_type'] == 5 && empty($value['commission'])){
						$value['dianzhu_price'] = 0.01;
					}
					$value['dianzhu_price'] = floatval($value['dianzhu_price']);
					$value['price'] = sprintf('%0.2f', $value['price']);
					$value['price'] = floatval($value['price']);	
					$value['show_price'] = sprintf('%0.2f', $value['show_price']);
					$value['show_price'] = floatval($value['show_price']);	
					$value['vip_price'] = sprintf('%0.2f', $value['vip_price']);
					$value['vip_price'] = floatval($value['vip_price']);
				$label_title = $this->getActive_label($value['prom_type']);
				if($label_title){
					$value['active_name'] = $label_title;
				}
            }
        }

		$goods_list = $this-> ActiveInfo($goods_list);
		return ['cate' => $cate, 'goods' => $goods_list,'total'=>$total];
	}

	/*
	 * 分类商品筛选
	 */
	public function brandSelect($cate_id){
		$goods_brand = new GoodsBrand();
		$where1 =[
			'goryid' => $cate_id,
			'status' => 0,
		];
		$list = $goods_brand->where($where1)->field('id,title,brand_img')->order('weigh desc')->select();

        if(empty($list)){
            //一级分类
            $ca = new GoodsCategoryModel();
            $gory_id = $ca->where(['pid'=>$cate_id])->column('category_id');
            if(!empty( $gory_id)){
                $gory_id = implode(',',$gory_id);
                $where2 = [
                    'goryid'=>['in',$gory_id],
                    'status' => 0
                ];
                $list = $goods_brand->where($where2)->field('id,title,brand_img')->order('weigh desc')->select();
            }
        }
        $data = [];
        if(!empty($list)){
            //去重
            $temp = [];
            $list = collection($list)->toArray();
            $goryids = array_column($list,'title');
            $goryids = array_unique($goryids);
            foreach ($goryids as $i){
                foreach ($list as $v){
                    if($v['title']==$i){
                        $temp[$i] = $v;
                    }
                }
            }
            foreach ($temp as $val){
                $data[] = $val;
            }
        }
		return $data;
	}

	/*
	 * 分类商品筛选
	 */
	public function brandTui($limit = 5, $p = 1){
		$p = $p?$p:1;
        $limit = $limit?$limit:5;
        $fir_limit = ($p-1)* $limit;
		$this->model = new GoodsBrand();
		$where =[
			'status' => 0,
		];

		$list = $this->model->field('id,title,brand_img,goryid')->order('weigh desc')->where('status',0)->limit($fir_limit, $limit)->select();
		

		return $list;
	}

	/*
	 * 分类商品筛选
	 */
	public function brandGoods($brandid){
		$where = [
			'brandid'=>$brandid,
			'status'=>0,
		];
		$Brandmodel = new GoodsBrand();
		$Band = $Brandmodel->where(['id'=>$brandid])->field('id,title,brand_img,goryid')->find();
		if(!$Band ){
			return 0;
		}
		$brandid_arr = $Brandmodel->where(['title'=>['eq', $Band['title']]])->column('id');
		$brandid_str =  implode(',',$brandid_arr);
		$where = [
			'brandid'=>['in',$brandid_str],
			'status'=>0,
			'is_gift'=>0,
		];
		$data = $this->model->where($where)->field('goods_id,goods_name,prom_id,prom_type,vip_price,price,show_price,stock,picture,volume,vip_price, category_id,commission,active_name')->select();
		 //销量大于库存 前台不展示 下架商品
		 foreach ($data as &$val) {
		 	
			if($val['volume'] >= $val['stock']){
				Db::name('goods')->where(['goods_id' => ['eq',$val['goods_id']]])->update(['status'=>1]);
			}
		}
		$data = $this->model->where($where)->field('goods_id,goods_name,stock,price,show_price,stock,picture,volume,vip_price,category_id,commission,prom_type,prom_id,active_name')->select();
				
		if(!$data){
			return 0;
		}
		//商品为活动时
		$data = $this->ActiveInfo($data);
		foreach ($data as $key => $val) {
			$val['rate'] = sprintf('%0.2f', $val['stock'] / ($val['volume'] + $val['stock']) * 100);
					$val['rate'] = floatval($val['rate']);
					if($val['rate']<=0){
		 				$val['rate'] = 0;
		 			}		
					
				}
		$Band ['list'] = $data;
		return $Band;
	}


	/*
	 * 商品详细信息
	 */
	public function getInfoById($goods_id, $field = '*'){
		return $this->model->where('goods_id', $goods_id)->field($field)->find();
	}

	/*
	 * 商品信息--商品
	 */
	public function goodsDetail($goods_id, $user_id = 0, $active_id = 0, $store_id = 0){
		$goods_info = $this->model->where('goods_id', $goods_id)->field('goods_id,goods_name,price,freight,stock,picture,images,exchange_integral,introduction,description,volume,status,vip_price,show_price,keywords,category_id,prom_type,prom_id,commission,supplier_id,active_name,is_gift')->find();
		//商品已下架或不存在
		if($goods_info['status'] == 1 || !$goods_info){
			return -1;
		}
		else{
			if($active_id == 4){
				$active_price = $this->getActivePirce($goods_info['price'],$goods_info['prom_type'],$goods_info['prom_id']);
			}else{
				$active_price = $this->ActivePrice($goods_info['prom_type'],$goods_info['price'],$goods_info['prom_id']);
			}
			if($active_price){
				$goods_info['active_price'] = $active_price;
                $goods_info['dianzhu_price'] = floor($active_price * $goods_info['commission'])/ 100;
			}else{
//				$val['dianzhu_price'] = floor($goods_info['price'] * $goods_info['commission'])/ 100;
                $goods_info['dianzhu_price'] = floor($goods_info['price'] * $goods_info['commission'])/ 100;
			}

			$spec_info = Db::name('goods_sku')->where('goods_id', $goods_id)->field('code,sku_name,price,stock,image,prom_id,prom_type')->select();
			// 优惠券
			$coupon_where = 'coupon_type=1 and coupon_type_id='.$goods_id.' or coupon_type=3 and coupon_stat=0';
			$coupon_info = Db::name('coupon')->where($coupon_where)->field('coupon_id,coupon_title,coupon_thumb,coupon_price,coupon_use_limit')->order('coupon_s_time asc')->find();
			$comment_num = Db::name('order_remark')->where(['or_goods_id' => $goods_id, 'status' => 1, 'or_isdel' => 0])->count();
			$goods_comment = Db::name('order_remark')->alias('a')->join('__USERS__ b', 'a.or_uid=b.user_id','INNER')->where(['a.or_goods_id' => $goods_id, 'a.status' => 1, 'a.or_isdel' => 0])->field('a.or_id,a.or_scores,a.or_cont,a.or_thumb,a.or_add_time,b.user_name,b.user_avat')->order('a.or_add_time desc')->limit(2)->select();
			if($goods_comment){
				foreach($goods_comment as &$v) {
					$v['or_thumb'] = explode(',', $v['or_thumb']);
					$v['or_thumb'] = array_filter($v['or_thumb']);
					if($v['or_add_time']){
						$v['or_add_time'] = date('Y-m-d', $v['or_add_time']);
					}
				}
			}

//			$goods_info['dianzhu_price'] = floor($goods_info['price'] * $goods_info['commission'])/ 100;
			$goods_info['price'] = floatval($goods_info['price']);
			$goods_info['dianzhu_price'] = floatval($goods_info['dianzhu_price']);
			$goods_info['price'] = sprintf('%0.2f', $goods_info['price']);
			$goods_info['price'] = floatval($goods_info['price']);	
			$goods_info['show_price'] = sprintf('%0.2f', $goods_info['show_price']);
			$goods_info['show_price'] = floatval($goods_info['show_price']);	
			$goods_info['vip_price'] = sprintf('%0.2f', $goods_info['vip_price']);
			$goods_info['vip_price'] = floatval($goods_info['vip_price']);	
		/* 	$goods_info['active_price'] = sprintf('%0.2f', $goods_info['active_price']);
			$goods_info['active_price'] = floatval($goods_info['active_price']);	 */
			//检查商品是否参加活动
			//`prom_type` tinyint(1) DEFAULT '0' COMMENT '2:预售',
			//`prom_id` tinyint(1) DEFAULT '0' COMMENT '促销活动id值',
			//$goods_info = $this->checkActiv($goods_info);
			if ($active_id > 8) {
                // 判断自定义活动
                $act_info = $this->getCustomtActive($active_id);
                if ($act_info['active_type'] == 1) {
                    $goods_info['active_price'] = $goods_info['price'] - $act_info['active_type_val'];
                } elseif ($act_info['active_type'] == 2) {
                    $goods_info['active_price'] = $goods_info['price'] * $act_info['active_type_val'] / 100;
                } elseif($act_info['active_type'] == 5) {

                    $goods_info['active_price'] = $goods_info['price'];
                    $active_goods=Db::name('active_goods')->where('goods_id', $goods_id)->field('goods_num')->find();
					
			 		$goods_info['stock']=$active_goods['goods_num'];
                }

//                $goods_info['dianzhu_price'] = $goods_info['active_price'] * $goods_info['commission'] / 100;
            } elseif ($active_id == 1) {
                //判断团购
                $info = Db::name('group_goods')->where(['goods_id' => $goods_info['goods_id'], 'is_end' => 0])->field('id,goods_id,group_price,start_time,end_time,goods_number')->find();
                if($info){
                	//$goods_info['buy_limit']=$info['buy_limit'];
                    $goods_info['active_price'] = $info['group_price'];
                    $goods_info['stock'] = $info['goods_number'];
                    $goods_info['end_time'] = '00:00:00';
                    $goods_info['start_time'] = '00:00:00';
                    if($info['end_time'] >= time()){
                        $goods_info['start_time'] = date('Y-m-d H:i:s', $info['start_time']);
                        $goods_info['end_time'] = date('Y-m-d H:i:s', $info['end_time']);
                    }
//                    $goods_info['dianzhu_price'] = $goods_info['active_price'] * $goods_info['commission'] / 100;
                }
            } elseif ($active_id == 5) {
                // 判断秒杀
                $info = Db::name('flash_goods')->alias('a')->join('__FLASH_ACTIVE__ b', 'a.flash_id=b.id')->where(['a.goods_id' => $goods_info['goods_id'], 'a.is_end' => 0, 'b.status' => 0])->field('a.flash_id,a.buy_limit,a.goods_number,a.order_number,a.limit_price,b.start_time,b.end_time,a.sku_id')->find();
                if($info){
                    // $goods_info['buy_limit'] = $info['buy_limit'];
                    $goods_info['stock'] = $info['goods_number'];
                    $goods_info['limit_price'] = $info['limit_price'];
                    if ($info['sku_id']){
                        $goods_info['show_price'] = Db::name('goods_sku')->where('sku_id',$info['sku_id'])->value('price');
                    }
                    $goods_info['end_time'] = '00:00:00';
                    if($info['end_time'] >= time()){
                        $goods_info['end_time'] = date('H:i:s', $info['end_time'] - time());
                    }
//                    $goods_info['dianzhu_price'] = $goods_info['limit_price'] * $goods_info['commission'] / 100;
                    // 判断 是否 可以购买状态
                    if ($info['start_time'] > time()) {
                        $goods_info['miaosha_is_pay'] = 0;
                    } else {
                        $goods_info['miaosha_is_pay'] = 1;
                    }
                    $goods  = Db::name('goods')->where('goods_id',$goods_info['goods_id'])->field('commission')->find();
	                if(empty($goods['commission'])){
	                	$goods_info['dianzhu_price'] = 0.01;
	                }
                }
            }
            // 拼团活动
            else if($active_id == 3){
            	$acti_info = Db::name('team_activity')->where([ 'goods_id' => $goods_id])->find();

            	$goods_info['stock']=$acti_info['goods_number'];
            	if ($acti_info['sku_id']){
                    $goods_info['show_price'] = Db::name('goods_sku')->where('sku_id',$acti_info['sku_id'])->value('price');
                }
                    $goods_info['price'] = $acti_info['team_price'];
            }
            //满2件打九折 满99三件 满199减100
            else if(5<$active_id && $active_id<9){
            	$active_goods=Db::name('full_goods')->where(['goods_id'=>$goods_id,'act_type'=>$active_id])->field('goods_number')->find();
			
			$goods_info['stock']=$active_goods['goods_number'];
		
            }//预售
            else if($active_id==2){
            	$goods_activity=Db::name('goods_activity')->where(['goods_id'=>$goods_id,'act_type'=>$active_id])->field('total_goods')->find();
            	$goods_info['stock']=$goods_activity['total_goods'];
		
            }elseif($active_id==4){
            	$goods_bargain=Db::name('bargain')->where(['goods_id'=>$goods_id,])->field('goods_number')->find();
            	$goods_info['stock']=$goods_bargain['goods_number'];
            }
            
			//相关推荐
			$recomm_where = [
				'category_id' => $goods_info['category_id'],
				'goods_id' => ['neq', $goods_id],
				'status' => 0,
				// 'goods_name' => ['neq', '开店大礼包'],
				'is_gift' => 0,
			];
			if($store_id){
				$recomm_where = [
					'a.status' => 0,
					// 'a.goods_name' => ['neq', '开店大礼包'],
					'a.is_gift' => 0,
					'b.s_g_storeid' => $store_id,
				];
				$recomm_goods = $this->model->alias('a')->join('__STORE_GOODS__ b', 'a.goods_id=b.s_g_goodsid')->field('a.goods_id,a.goods_name,a.picture,a.price,a.vip_price,a.prom_id,a.prom_type, a.show_price,a.commission,a.active_name')->where($recomm_where)->order('a.create_time desc')->limit(10)->select();
				if(!$recomm_goods){
					$pid = Db::name('goods_category')->where('category_id',$goods_info['category_id'])->value('pid');
					$cat_id = Db::name('goods_category')->where('pid',$pid)->column('category_id');
					$recomm_where3 = [
						'goods_id' => ['neq', $goods_id],
						'status' => 0,
						'is_gift' => 0,
					];
					if($cat_id){
						$cat_id = implode(',',$cat_id);
						$recomm_where3['category_id'] = ['in',$cat_id];
					}
					
					$recomm_goods = $this->model->where($recomm_where2)->field('goods_id,goods_name,picture,prom_id,prom_type,vip_price,price,show_price,commission,active_name')->order('create_time desc')->limit(10)->select();
				}
			}
			else{
				$recomm_goods = $this->model->where($recomm_where)->field('goods_id,goods_name,picture,prom_id,prom_type,vip_price,price,show_price,commission,active_name')->order('create_time desc')->limit(10)->select();
				if(!$recomm_goods){
					$pid = Db::name('goods_category')->where('category_id',$goods_info['category_id'])->value('pid');
					$cat_id = Db::name('goods_category')->where('pid',$pid)->column('category_id');
					$recomm_where2 = [
						'goods_id' => ['neq', $goods_id],
						'status' => 0,
						'is_gift' => 0,
					];
					if($cat_id){
						$cat_id = implode(',',$cat_id);
						$recomm_where2['category_id'] = ['in',$cat_id];
					}
					$recomm_goods = $this->model->where($recomm_where2)->field('goods_id,goods_name,picture,prom_id,prom_type,vip_price,price,show_price,commission,active_name')->order('create_time desc')->limit(10)->select();
				}
			}

	        if ($recomm_goods) {
	            foreach($recomm_goods as &$val) {
					$activeInfo = Db::name('active_type')->where('id',$val['prom_type'])->field('id,label_title')->find();
					$val['active_id'] = '';
					$val['active_type_name'] = '';
					if($activeInfo){
						$val['active_id'] = $activeInfo['id'];
						$val['active_type_name'] = $activeInfo['label_title'];
						$val['active_name'] = $activeInfo['label_title'];
					}
					$active_price = $this->ActivePrice($val['prom_type'],$val['price'],$val['prom_id']);
					
					$val['dianzhu_price'] = floor($val['price'] * $val['commission'])/ 100;
	                if($store_id){
	                	$val['store_id'] = $store_id;
	                }
					$val['price'] = floatval($val['price']);
					$val['dianzhu_price'] = floatval($val['dianzhu_price']);
					$active_price = sprintf('%0.2f', $active_price);
					$val['active_price'] = floatval($active_price);	
					$val['price'] = sprintf('%0.2f', $val['price']);
					$val['price'] = floatval($val['price']);	
					$val['show_price'] = sprintf('%0.2f', $val['show_price']);
					$val['show_price'] = floatval($val['show_price']);	
					$val['vip_price'] = sprintf('%0.2f', $val['vip_price']);
					$val['vip_price'] = floatval($val['vip_price']);	
                }
            }
			$recomm_goods = $this->ActiveInfo($recomm_goods);
            if($store_id){
            	$goods['store_id'] = $store_id;
            }
			$active_name = $this->ActiveName($goods_info['prom_type']);

			if($active_name){
                $goods_info['active_name'] = $active_name;
			}

			$goods_info['coupon'] = $coupon_info ? $coupon_info : [];
			$goods_info['comment_total'] = $comment_num;
			$goods_info['comment_list'] = $goods_comment;
			$goods_info['recomm'] = $recomm_goods;
			$goods_info['description'] = explode(',', $goods_info['description']);
			
			$abstract_content =  Db::name('active_type')->where('id',$active_id)->find();
			
			if($abstract_content){
				if($active_id == 1){
					$goods_info['abstract_content']= '限制购买数量'.$abstract_content['limit_num'].'件';
				}
				
			}else{
				$goods_info['abstract_content'] = '';
			}
			
 
			$goods_info['is_favor'] = 0;
//			$goods_info['notes'] = "";
			// 我的足迹
			if($user_id){
				$track = Db::name('users_track')->where(['track_goods_id'=> $goods_id, 't_uid' => $user_id])->field('track_id')->find();
				if($track){
					Db::name('users_track')->where('track_id', $track['track_id'])->update(['track_visible' => 1, 'track_add_time' => time()]);
				}
				else{
					$insert = [
						't_uid' => $user_id,
						'track_goods_id' => $goods_id,
						'track_add_time' => time(),
						'track_visible' => 1
					];
					Db::name('users_track')->insert($insert);
				}

				// 是否收藏
				$is_favor = Db::name('favorite')->where(['f_uid' => $user_id, 'favor_type' => 1, 'f_goods_id' => $goods_id, 'f_is_del' => 0])->find();
				$goods_info['is_favor'] = $is_favor ? 1 : 0;
                // 店主是否上架
                $is_favor = Db::name('store_goods')->where(['s_g_userid' => $user_id, 's_g_isdel' => 0, 's_g_goodsid' => $goods_id])->find();
                $goods_info['is_shangjia'] = $is_favor ? 1 : 0;
			}

			return $goods_info;
		}
	}
	/*
	 * 领取优惠券
	 */
	public function getCoupon($goods_id){
		$coupon_model = new Coupon();
		$where = 'coupon_stat=0 and coupon_total>0 and (coupon_type in(2,3) or coupon_type=1 and coupon_type_id='.$goods_id. ')';
		// $where = [
		// 	'coupon_stat' => 0,
		// 	'coupon_total' => ['gt', 0]
		// ];
		$field = 'coupon_id,coupon_title,coupon_type_id,coupon_price,coupon_use_limit,coupon_buy_price,coupon_s_time,coupon_aval_time';
		$avai = $coupon_model->where($where)->field($field)->order('coupon_s_time asc,coupon_aval_time asc,coupon_type asc')->select();
		foreach($avai as &$v){
			if($v['coupon_aval_time'] < time()){
				Db::name('coupon')->where('coupon_id', $v['coupon_id'])->update(['coupon_stat' => 1]);
				unset($v);
				continue;
			}
			else{
				$v['coupon_s_time'] = date('Y.m.d', $v['coupon_s_time']);
				$v['coupon_aval_time'] = date('Y.m.d', $v['coupon_aval_time']);
			}
		}
		return $avai;
	}

	/*
	 * 商品信息--详情
	 */
	public function goodsInfo($goods_id, $sku_id = ''){
		// if($sku_id){
		// 	$where = ['a.sku_id' => $sku_id];
		// }
		//商品介绍、规格参数
		// $intro = $this->model->where('goods_id', $goods_id)->field('description,subject_values,')->find();
		//规格参数
		$intro = $this->model->alias('a')->join('__GOODS_SKU__ b', 'a.goods_id=b.goods_id')->where('a.goods_id', $goods_id)->field('a.description,a.subject_values,a.unit,b.code,b.all,b.gift')->find();
		//购买须知
		 $content = Db::name('content')->where('title', '购买需知')->field('content')->find(); 
		// $content = Db::name('content_category')->alias('a')->join('content b','a.category_id = b.category_id')->where('category_name', '购买须知')->field('b.content')->find();
        //拼团规则
        $pt_active = Db::name('active_type')->where('id',3)->value('rules_content');
        //满赠优惠
        $full_give = Db::name('config') ->value('full_gift');
        $full_give = json_decode($full_give,true);
        foreach($full_give['field'] as $key=>$v){

            $full_give_arr[] = '满'.$v.'赠'.$full_give['value'][$key];
        }

		$info['intro'] = explode(',', $intro['description']);
		$info['spec']['code'] = $intro['code'];
		$info['spec']['subject'] = json_decode($intro['subject_values'], true);
		$info['unit'] = $intro['unit'];
		if($content){
			$info['content'] = $content;
		}else{
			$info['content'] = '';
		}
		$info['pt_avtive'] = $pt_active;
		$info['full_give'] = $full_give_arr;
		return $info;
	}

	/*
	 * 商品详情--素材
	 */
	public function goodsMaterial($goods_id, $uid){
		$list = Db::name('users_material')->alias('a')->join('__USERS__ b', 'a.m_uid=b.user_id', 'LEFT')->where('a.m_goods_id', $goods_id)->field('a.m_id,a.mate_content,a.mate_thumb,a.mate_add_time,b.user_avat,b.user_name,b.user_mobile,a.mate_video')->select();
		if($list){
			foreach($list as &$v){
				if($v['mate_thumb']){
                    $v['mate_thumb'] = trim($v['mate_thumb'], ',');
					$v['mate_thumb'] = explode(',', $v['mate_thumb']);
				}
				else{
					$v['mate_thumb'] = [];
				}
				$v['mate_add_time'] = date('Y-m-d H:i:s', $v['mate_add_time']);
				$v['is_favorite'] = 0;
				//素材是否收藏
				if($uid){
					$res = Db::name('favorite')->where(['f_uid' => $uid, 'favor_type' => 2, 'f_goods_id' => $v['m_id']])->find();
					if($res){
						$v['is_favorite'] = 1;
					}
                    // 店主是否上架
                    $is_favor = Db::name('store_goods')->where(['s_g_userid' => $uid, 's_g_isdel' => 0, 's_g_goodsid' => $goods_id])->find();
                    $v['is_shangjia'] = $is_favor ? 1 : 0;
				}

				// 过滤
                $v['mate_content'] = $this->word_filter($v['mate_content']);
			}
		}
		return $list;
	}

	/*
	 * 收藏商品
	 */
	public function goodsFavor($uid, $goods_id){
		$favor_info = Db::name('favorite')->where(['f_uid' => $uid, 'favor_type' => 1, 'f_goods_id' => $goods_id])->field('favor_id')->find();
		//取消收藏
		if($favor_info){
			$result = Db::name('favorite')->where(['favor_id' => $favor_info['favor_id']])->delete();
		}
		//加入收藏
		else{
			$insert = [
				'f_uid' => $uid,
				'favor_type' => 1,
				'f_goods_id' => $goods_id,
				'f_add_time' => time()
			];
			$result = Db::name('favorite')->insert($insert);
		}
		return $result;
	}

	/*
	 * 收藏素材
	 */
	public function mateFavor($uid, $mid,$type){
		$type = $type == 1?3:$type;
		$favor_info = Db::name('favorite')->where(['f_uid' => $uid, 'favor_type' => $type, 'f_goods_id' => $mid])->field('favor_id')->find();
		//取消收藏
		if($favor_info){
			$result = Db::name('favorite')->where(['favor_id' => $favor_info['favor_id']])->delete();
		}
		//加入收藏
		else{
			$insert = [
				'f_uid' => $uid,
				'favor_type' => $type,
				'f_goods_id' => $mid,
				'f_add_time' => time()
			];
			$result = Db::name('favorite')->insert($insert);
		}
		return $result;
	}

	/*
	 * 立即购买
	 */
	public function buyNow($uid, $goods_id, $sku_id,$num, $prom_id, $store_id = 0){

	    $num = (int)$num?(int)$num:1;

		$coupon_model = new Coupon();
		$addr_info = Db::name('addr')->where(['a_uid' => $uid, 'is_del' => 0])->field('addr_id,addr_province,addr_city,addr_area,addr_cont,addr_receiver,addr_phone,post_no')->order('is_default desc,addr_add_time desc')->find();

		if ($addr_info) {
            if($addr_info['addr_province']){
                $addr_info['provicne_id'] = $addr_info['addr_province'];
                $addr_info['addr_province'] = $this->getRegion(['region_id' => $addr_info['addr_province']]);
            }
            if($addr_info['addr_city']){
                $addr_info['addr_city'] = $this->getRegion(['region_id' => $addr_info['addr_city']]);
            }
            if($addr_info['addr_area']){
                $addr_info['addr_area'] = $this->getRegion(['region_id' => $addr_info['addr_area']]);
            }
            $addr_info['addr_area'] = $addr_info['addr_province'].' '.$addr_info['addr_city'].' '.$addr_info['addr_area'];
        }
		$goods_info = $this->model->where('goods_id', $goods_id)->field('goods_id,goods_name,price,freight,picture,vip_price,commission,prom_id')->find();


		if($sku_id){
			$sku_info = Db::name('goods_sku')->where(['sku_id' => $sku_id, 'goods_id' => $goods_id])->field('sku_name,price,image')->find();
			if($sku_info){
				$goods_info['sku'] = $sku_info['sku_name'];
				$goods_info['price'] = $sku_info['price'];
				$goods_info['vip_price'] = $sku_info['price'] * (1 - $goods_info['commission'] / 100);
                $goods_info['picture'] = $sku_info['image'];
			}
			// $goods_info['stock'] = $sku_info['stock'];
		}

		$youhui = 0;
        $total_price = 0;
        // 获取活动信息
        if ($prom_id) {
            $price = 0;
            // 抢购/秒杀
            if ($prom_id == 5) {
                $goods_where = [
                    'goods_id' => $goods_id,
                    'goods_number' => ['gt', 0]
                ];
                // 秒杀商品 未到时间
                $flash_goods_info = Db::name('flash_goods')->where($goods_where)->find();
                if (!$flash_goods_info) {
                    return -1;
                }
                $where = [
                    'id' => $flash_goods_info['flash_id'],
                    'status' => 0
                ];
                $res = Db::name('flash_active')->where($where)->find();
                if ($res['start_time'] > time()) {
                    return -2;
                }

                $price = $flash_goods_info['limit_price'];
                if ($flash_goods_info['buy_limit'] >= $num) {
                    // $youhui = ($sku_info['price'] - $flash_goods_info['limit_price']) * $num;
                    if($flash_goods_info['price_type'] == 0){
                    	$total_price = $flash_goods_info['limit_price'] * $num;
                    }
                    else{
                    	$total_price = $sku_info['price'] * ($flash_goods_info['price_reduce'] / 100) * $num;
                    }
                } else {
                	if($flash_goods_info['price_type'] == 0){
                		$total_price = $flash_goods_info['limit_price'] * $flash_goods_info['buy_limit'] + $sku_info['price'] * ($num - $flash_goods_info['buy_limit']);
                	}
                	else{
                		$total_price = $sku_info['price'] * ($flash_goods_info['price_reduce'] / 100) * $flash_goods_info['buy_limit'] + $sku_info['price'] * ($num - $flash_goods_info['buy_limit']);
                	}
                }
                // 活动优惠
                $youhui = $sku_info['price'] * $num - $total_price;
            }
            // 积分商品或新人购买
            elseif($prom_id > 8){
            	//活动商品立即购买库存限制
            $acti_info = Db::name('active_goods')->where(['active_type_id' => $prom_id, 'goods_id' => $goods_id, 'status' => 0])->field('goods_num,goods_price,order_number')->find();

            if($acti_info){
            	$shen_num=$acti_info['goods_num'];
            	
            	if($num > $shen_num){
            		return -4;
            	}
	            	}
               $act_info = $this->getCustomtActive($prom_id);
                $act_price = 0;
                // 减价
                if ($act_info['active_type'] == 1) {
                    $price = $sku_info['price'] - $act_info['active_type_val'];
                    $act_price = $act_info['active_type_val'];
                }
                // 打折
                elseif ($act_info['active_type'] == 2) {
                    $price = $sku_info['price'] * $act_info['active_type_val'] / 100;
                    $act_price = $sku_info['price'] * (100 - $act_info['active_type_val']) / 100;
                }
                // 免邮或积分
                else {
                    $price = $sku_info['price'];
                }
                if ($act_info['limit_num'] >= $num) {
                    $total_price =$price * $num;
                    $youhui = $act_price * $num;
                } else {
                    $total_price = $price * $act_info['limit_num'] + $sku_info['price'] * ($num - $act_info['limit_num']);
                    $youhui = $act_price * $act_info['limit_num'];
                }
				if($prom_id == 12){
					$res = Db::name('order')->where(['order_uid' => $uid, 'order_status' => ['neq', 5]])->find();
					if($res){
						return -3;
					}
				}
            }// 拼团
            elseif($prom_id == 3) {
				  $act_info = Db::name('team_activity')->where('goods_id',$goods_id)->field('goods_id,price_type,price_reduce')->find();
                 if($act_info){
					if($act_info['price_type'] == 1){
						// $price = $sku_info['price'] * $act_info['active_type_val'] / 100;
						$act_prices = $sku_info['price'] * (100 - $act_info['price_reduce']) / 100;	
						$youhui_price =  $sku_info['price'] *$act_info['price_reduce'] /100;
						$youhui = $youhui_price * $num;
					}else if($act_info['price_type'] == 0){
						$act_prices = $sku_info['price'] - $act_info['price_reduce'];
						
						$youhui = $act_info['price_reduce'] * $num;
 						if($act_prices<0){
							$act_prices = 0;
						}
					}
					 
				 }
				 $goods_info['active_price'] =$act_prices;
				 $total_price = $act_prices;
			}
            // 团购
            elseif($prom_id == 1) {
                $active_where = [
                    'goods_id' => $goods_id,
                ];
                $active_goods_info = Db::name('group_goods')->where($active_where)->find();
                if (!$active_goods_info) {
                    // 该活动没有找到商品
                    return -1;
                }
                if($num>$active_goods_info['goods_number']){
                	return -4;
                }
                if($active_goods_info['goods_number']<=0){
                	return -4;
                }
                if ($active_goods_info['start_time'] > time() || $active_goods_info['end_time'] < time()) {
                    return -2;// 该商品未到活动时间
                }
                if($active_goods_info['buy_limit'] >= $num){
                	// 减价
                	if($active_goods_info['price_type'] == 0){
                		$total_price = $active_goods_info['group_price'] * $num;
                	}
                	// 折扣
                	else{
                		$total_price = $sku_info['price'] * ($active_goods_info['price_reduce'] / 100) * $num;
                	}
                }
                else{
                	// 减价
                	if($active_goods_info['price_type'] == 0){
                		$total_price = $active_goods_info['group_price'] * $active_goods_info['buy_limit'] + $sku_info['price'] * ($num - $active_goods_info['buy_limit']);
                	}
                	// 折扣
                	else{
                		$total_price = $sku_info['price'] * ($active_goods_info['price_reduce'] / 100) * $active_goods_info['buy_limit'] + $sku_info['price'] * ($num - $active_goods_info['buy_limit']);
                	}
                }
                // $price = $active_goods_info['group_price'];
                // $total_price = $price * $num;
                $youhui = $sku_info['price'] * $num - $total_price;
            }
            // 满199减100
            elseif ($prom_id == 6){
                $price = $sku_info['price'];
                $total_price = $price * $num;
                if ($total_price >= 199 ) {
                    $total_price = $total_price- 100;
                    $youhui = 100;
                }
            }
            // 99元3件
            elseif ($prom_id == 7){
                $price = $sku_info['price'];
                if ($num < 3) {
                    $total_price = $price * $num;
                }
                // else if($num  == 3){
                //     $youhui = $price * $num - 99;
                //     $total_price = 99;
                // }
                else {
                    $total_price = $price * ($num - 3) + 99;
                    $youhui = $price * 3 - 99;
                }
            }
            // 满2件打9折
            elseif ($prom_id == 8){
                $price = $sku_info['price'];
                if ($num < 2) {
                    $total_price = $price * $num;
                } else {
                    $total_price = ceil($price * $num * 9 * 10) /100;
                    $youhui =ceil($price * $num * 10) /100;
                }
            }
            //特卖商品
            
          	// 自定义活动
            else {

            	if($prom_id == 12){

					//新人专享
					$res = Db::name('order')->where(['order_uid' => $uid, 'order_status' => ['neq', 5]])->find();

					if($res){
						return -3;
					}
				}	

            	$acti_info = Db::name('active_goods')->where(['active_type_id' => $prom_id, 'goods_id' => $goods_id, 'status' => 0])->field('goods_num,goods_price,order_number')->find();



            	if($num <= $acti_info['goods_num'] - $acti_info['order_number']){
            		$total_price = $acti_info['goods_price'] * $num;

            	}
            	else{
            	    $youxiao = $acti_info['goods_num'] - $acti_info['order_number'];
            		$total_price = $acti_info['goods_price'] * $youxiao + $sku_info['price'] * ( $num - $youxiao);
            	}

            	$youhui = $sku_info['price'] * $num - $total_price;

                // $price = $sku_info['price'];
                // $total_price = $price * $num;
            }





            // }else{
            // 	return -1;
            // }

            // }

			$team_price = Db::name('team_activity')->where('id',$goods_info['prom_id'])->field('team_price')->find();
			if($team_price){
				 $goods_info['active_price'] = $team_price['team_price'];
			 }
            $goods_info['yuanjia'] =$sku_info['price'];
            $goods_info['price'] = $price;
        } else {
            //普通商品
            $total_price = $goods_info['price'] * $num;
        }

		// $coupon_where = 'coupon_type=1 and coupon_type_id='.$goods_id.' or coupon_type=3';
		$coupon_info = Db::name('coupon_users')->where(['c_uid' => $uid, 'coupon_stat' => 1])->count();
		//积分可抵扣
		$user_info = Db::name('users')->where('user_id', $uid)->field('user_points')->find();
		$point_config = Db::name('config')->where(1)->value('setjifen');
		$point_config = json_decode($point_config, true);
		if(!$point_config['status']){
			$points_avai = sprintf('%0.2f', $user_info['user_points'] / $point_config['number']);
		}
		$info['active_info']= $this->getCustomtActive($prom_id);
		//积分信息
		$info['points'] = $user_info['user_points'];
		$info['points_avai'] = $points_avai;
		//发票信息
		$info['addr'] = $addr_info;
		$yunfei = $this->getYunfei($goods_info['goods_id'],$sku_id, $addr_info['provicne_id'], $num);
		$goods_info['freight'] = $yunfei;

		$info['goods'] = $goods_info;
		$info['coupon'] = $coupon_info ? 1 : 0;
		// 充值卡
		$recharge = Db::name('user_rc')->where(['card_uid' => $uid, 'card_stat' => 1])->count();
		// 元宝
		$yz = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_stat' => 2])->count();
		$info['rc'] = $recharge ? 1 : 0;
		$info['yz'] = $yz ? 1 : 0;
		if($store_id){
			$info['s_id'] = $store_id;
		}
		$info['total_price'] = $total_price;
		$info['youhui'] = $youhui;


		return $info;
	}
	/*
	 * 获取运费
	 */
	public function getYunfei($goods_id, $sku_id, $province_id = 0, $num = 1)
    {
        $info = Db::name('goods')->field('is_free_shipping,province,basic_freight,other_freight')->where(['goods_id' => $goods_id])->find();
        $yunfei = 0;
//        if ($info['is_free_shipping'] == 1) {
//            $sku_info = Db::name('goods_sku')->where(['sku_id' => $sku_id])->find();
//            $basic_freight = json_decode($info['basic_freight'], true);
//            if ($sku_info['weight']* $num <= $basic_freight[$province_id]['moren']['weight']) {
//                // 基础运费
//                $yunfei += $basic_freight[$province_id]['moren']['money'];
//            } else {
//                // 基础费用
//                $yunfei += $basic_freight[$province_id]['moren']['money'];
//                // 超出部分 收费
//                $yunfei += ceil(($sku_info['weight'] * $num - $basic_freight[$province_id]['moren']['weight']) / $basic_freight[$province_id]['zuijia']['weight']) * $basic_freight[$province_id]['zuijia']['money'];
//            }
//        }
        // 偏远地区 另外收费，
//        $province_list = $info['province'] ? explode(',', $info['province']) : '';
//        if ($province_list && $province_id) {
//            if (in_array($province_id, $province_list)) {
//                $yunfei += $info['other_freight'];
//            }
//        }
        if ($yunfei < 0) {
            return 0;
        }
        return $yunfei;
    }
    /**
     * 验证活动商品的购买数量
     */
    public function checkGoodsNum($uid,$goods_id,$num, $prom_id)
    {
        if ($prom_id) {
            if ($prom_id == 5) {
                $hour = time();
                $where = [
                    'start_time' => ['elt', $hour],
                    'end_time' => ['gt', $hour],
                    'status' => 0
                ];
                $res = Db::name('flash_active')->where($where)->find();
                if (!$res) {
                    return false;
                }
                $goods_where = [
                    'flash_id' => $res['id'],
                    'goods_id' => $goods_id,
                    'goods_number' => ['gt', 0]
                ];
                $goods_info = Db::name('flash_goods')->where($goods_where)->find();
                if (!$goods_info) {
                    return false;
                }
                if ($goods_info['buy_limit'] < $num) {
                    return false;
                }

            } elseif($prom_id > 8){
                $act_info = $this->getCustomtActive($prom_id);
                if ($act_info['limit_num'] < $num) {
                    return false;
                }
            }
        }
        return true;
    }
	/**
     * 根据活动id 获取商品
     */
	public function getActiveGoods($active_type_id, $limit = 20, $p = 1, $time = null,$goods_id=0)
    {
        $p = $p?$p:1;
        $limit = $limit?$limit:20;
        $fir_limit = ($p-1)* $limit;
		 
		//团购
        if ($active_type_id == 1) {
			$goods_where = [
                'a.end_time' => ['gt', time()],
                'b.status'=>0,
                'a.is_end' => 0,
                'a.goods_id' => ['neq',$goods_id],
            ];          
			 $goods_list = Db::name('group_goods')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->where($goods_where)->limit($fir_limit, $limit)->select();
			  if ($goods_list) {
				foreach ($goods_list as &$val) {
					$goods  = Db::name('goods')->where('goods_id',$val['goods_id'])->find();
					//商品信息
					$goods_info = $this->getGoodsinfo($val['goods_id']);
					$val['goods_name'] = $goods_info['goods_name'];
					$val['price'] = $goods_info['price'];
					$val['picture'] = $goods_info['picture'];
					$val['vip_price'] = $goods['vip_price'];
					$val['show_price'] = $goods['show_price'];
					$val['active_price'] = $val['group_price'];
				 	$val['profit'] = $val['active_price'] * $goods['commission'] / 100;
				}
			}
			 return $goods_list;
        } elseif($active_type_id == 5) {
            // 秒杀

			// 00:00,23:00
			/*$hour =$time?$time:time();
			$hour =  explode(',',$time);
			$now_time = date('Y-m-d',time());
			$start_time = strtotime($now_time.' '.$hour[0]);
            $end_time = strtotime($now_time.' '.$hour[1]);*/

			if($time == 'Yesterday'){				
				$start_time = strtotime(date("Y-m-d",strtotime("-1 day")));
				//$start_time =  0;
				$end_time = strtotime(date("Y-m-d 23:59:59",strtotime("-1 day")));
				 $where = [
					'start_time' => ['egt', $start_time],
					'end_time' => ['elt', $end_time],    
					'status' => 0
				];
				$id_arr = Db::name('flash_active')->where($where)->column('id');
				$id_str = implode(',',$id_arr);
				$goods_where = [
					'a.flash_id' => ['in',$id_str],
					'a.is_end' => 0,
					'b.status'=>0,
					'a.goods_number' => ['exp', ">order_number"],
					'a.goods_id' => ['neq',$goods_id],
				];
				/* 	$goods_list = Db::name('flash_goods')->where($goods_where)->limit($fir_limit, $limit)->select(); */
				$goods_list = Db::name('flash_goods')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->where($goods_where)->limit($fir_limit, $limit)->select();
				if ($goods_list) {
					foreach ($goods_list as &$val) {
						$goods_info = $this->getGoodsinfo($val['goods_id']);
						$goods  = Db::name('goods')->where('goods_id',$val['goods_id'])->find(); 
						$val['rate'] = sprintf('%0.2f', $val['goods_number'] / ($val['order_number'] + $val['goods_number']) * 100);
						$val['rate'] = floatval($val['rate']);
						if($val['rate']<=0){
		 				$val['rate'] = 0;
		 				}
						$val['goods_name'] = $goods_info['goods_name'];
						$val['picture'] = $goods_info['picture'];
						$val['price'] = $goods['price'];
						$val['vip_price'] = $goods['vip_price'];
						$val['show_price'] = $goods['show_price'];
						$val['active_price'] = $val['limit_price'];
						$val['profit'] = $val['active_price'] * $goods['commission'] / 100;
						if(empty($goods['commission'])){
							$val['profit'] = 0.01;
						}
					}
				}
				return $goods_list;
			
			}elseif($time == 'Tomorrow'){
				
				$start_time = strtotime(date("Y-m-d",strtotime("+1 day")));
				$end_time = strtotime(date("Y-m-d 23:59:59",strtotime("+1 day")));
				$where = [
					'start_time' => ['egt', $start_time],
		            'end_time' => ['elt', $end_time],    
		            'status' => 0
		        ];
		        $res = Db::name('flash_active')->where($where)->find();	 
		        if(!$res) return [];
		        $fa_id = $res['id'];
			}elseif(!$time){
				$now = time();
				$whereTemp = [
		            'end_time' => ['>=', $now],
		            'status' => 0
		        ];

				/*if($hour<12){
					$start_time = strtotime(date("Y-m-d",time()));
					$end_time = strtotime(date("Y-m-d 12:00",time()));
				}else if($hour>=12&&$hour<18){
					$start_time = strtotime(date("Y-m-d 12:00",time()));
					$end_time = strtotime(date("Y-m-d 18:00",time()));
				}else if($hour>=18&&$hour<=21){
					$start_time = strtotime(date("Y-m-d 18:00",time()));
					$end_time = strtotime(date("Y-m-d 21:00",time()));
				}else{
					$start_time = strtotime(date("Y-m-d 21:00",time()));
            	}*/
            	//$flash_active = Db::name('flash_active')->where($whereTemp)->order('start_time asc')->find();
            	$flash_active = Db::name('flash_active')->where($whereTemp)->order('id desc')->find();
            	//if(empty($flash_active) || $flash_active['end_time']>strtotime(date("Y-m-d 23:59:59",$now))){
            	if(empty($flash_active)){
            		//可能今天的当前时间没有秒杀商品了，找上个时间段
            		$flash_active = Db::name('flash_active')->where(['end_time'=>['<',$now],'status' => 0])->order('end_time desc')->find();
            		if(empty($flash_active) || $flash_active['start_time']<=strtotime(date("Y-m-d",$now))) return [];
            	}
	            $fa_id = $flash_active['id'];
            	
            }else{
            	$hour =  explode(',',$time);
				$now_time = date('Y-m-d',time());
				$start_time = strtotime($now_time.' '.$hour[0]);
	            $end_time = strtotime($now_time.' '.$hour[1]);
	            $where = [
					'start_time' => ['egt', $start_time],
		            'end_time' => ['elt', $end_time],    
		            'status' => 0
		        ];
		        $res = Db::name('flash_active')->where($where)->order('id desc')->find();
		        if(!$res) return [];
		        $fa_id = $res['id'];
            }
			
            $goods_where = [
                'a.flash_id' => $fa_id,
                'a.is_end' => 0,
                'b.status'=>0,
				'a.goods_id' => ['neq',$goods_id],
            ];
            //$goods_list = Db::name('flash_goods')->where($goods_where)->limit($fir_limit, $limit)->select();
            $goods_list = Db::name('flash_goods')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->where($goods_where)->limit($fir_limit, $limit)->select();
            if ($goods_list) {
                foreach ($goods_list as &$val) {
                    $goods_info = $this->getGoodsinfo($val['goods_id']);
					$goods  = Db::name('goods')->where('goods_id',$val['goods_id'])->find();
					$val['rate'] = sprintf('%0.2f', $val['goods_number'] / ($val['order_number'] + $val['goods_number']) * 100);
					$val['rate'] = floatval($val['rate']);
					if($val['rate']<=0){
		 				$val['rate'] = 0;
		 			}
                    $val['goods_name'] = $goods_info['goods_name'];
                    $val['picture'] = $goods_info['picture'];
					$val['price'] = $goods['price'];
					$val['vip_price'] = $goods['vip_price'];
					if ($val['sku_id']){
                        $val['show_price'] = Db::name('goods_sku')->where('sku_id',$val['sku_id'])->value('price');
                    }else{
                        $val['show_price'] = $goods['show_price'];
                    }

					$val['active_price'] = $val['limit_price'];
					$val['profit'] = $val['active_price'] * $goods['commission'] / 100;
					if(empty($goods['commission'])){
							$val['profit'] = 0.01;
					}
                }
            }
            return $goods_list;
		//预售商品信息
        }else if($active_type_id == 2){
			  $goods_where = [
                'a.act_type' =>  2,
                'b.status'=>0,
				'a.goods_id' => ['neq',$goods_id],
            ];
			 //$goods_list = Db::name('goods_activity')->where($goods_where)->limit($fir_limit, $limit)->select();
			 $goods_list = Db::name('goods_activity')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->where($goods_where)->limit($fir_limit, $limit)->select();
			  if ($goods_list) {
                foreach ($goods_list as &$val) {
					$goods  = Db::name('goods')->where('goods_id',$val['goods_id'])->find();

					$val['profit'] = $val['price'] * $goods['commission'] / 100;
					//商品信息
                    $goods_info = $this->getGoodsinfo($val['goods_id']);
                    $val['goods_name'] = $goods_info['goods_name'];
                    $val['picture'] = $goods_info['picture'];

					$val['price'] = $goods['price'];
					$val['vip_price'] = $goods['vip_price'];
					$val['show_price'] = $goods['show_price'];
					$val['active_price'] = $val['deposit'];
                }
            }
			 return $goods_list;
		//拼团商品信息
		}else if($active_type_id == 3){
			  $goods_where = [
                'a.status' => 0,
                'b.status'=>0,
				'a.goods_id' => ['neq',$goods_id],
            ];
			// $goods_list = Db::name('team_activity')->where($goods_where)->limit($fir_limit, $limit)->select();
			  $goods_list = Db::name('team_activity')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->where($goods_where)->limit($fir_limit, $limit)->select();
			  if ($goods_list) {
                foreach ($goods_list as $key=>&$val) {
                //
					//商品信息
					 $goods  = Db::name('goods')->where('goods_id',$val['goods_id'])->find();
					 $val['rate'] = sprintf('%0.2f', $val['goods_number'] / ($val['order_number'] + $val['goods_number']) * 100);
					$val['rate'] = floatval($val['rate']);
					if($val['rate']<=0){
		 				$val['rate'] = 0;
		 			}
		 			if ($val['sku_id']){
                        //sku信息
                        $goods_sku = Db::name('goods_sku')->where('sku_id',$val['sku_id'])->field('price,show_price')->find();
                    }else{
					    $goods_sku='';
                    }

                    $goods_info = $this->getGoodsinfo($val['goods_id']);
                    $val['goods_name'] = $goods_info['goods_name'];
                     $val['picture'] = $goods_info['picture'];
                    $val['total_goods'] = $goods_info['stock'];
					$val['vip_price'] = $goods['vip_price'];
                   // $val['price'] = $goods['price'];
                    $val['price'] = $val['team_price'];
                    if ($goods_sku){
                        $val['show_price'] = $goods_sku['price'];
                    }else{
                        $val['show_price'] = $goods['show_price'];
                    }
					//$val['show_price'] = $goods['show_price'];
					$val['active_price'] = $val['team_price'];
					$val['profit'] = $val['active_price'] * $goods['commission'] / 100;
                }
            }
			 return $goods_list;
		//砍价商品信息
		}else if($active_type_id == 4){
			  $goods_where = [
                'a.status' =>  0,
                'b.status'=>0,
				'a.goods_id' => ['neq',$goods_id],
            ];
			 //$goods_list = Db::name('bargain')->where($goods_where)->limit($fir_limit, $limit)->select();
			 $goods_list = Db::name('bargain')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->where($goods_where)->limit($fir_limit, $limit)->select();
			  if ($goods_list) {
                foreach ($goods_list as &$val) {
					//商品信息
					$goods  = Db::name('goods')->where('goods_id',$val['goods_id'])->find();

                    $goods_info = $this->getGoodsinfo($val['goods_id']);
                    $val['goods_name'] = $goods_info['goods_name'];

                    $val['picture'] = $goods_info['picture'];

					$val['price'] = $goods['price'];
					$val['vip_price'] = $goods['vip_price'];
					$val['show_price'] = $goods['show_price'];
					$val['active_price'] = $goods['price'];//商品活动价为原价
					$val['profit'] = $val['end_price'] * $goods['commission'] / 100;
                }
            }
			 return $goods_list;
			 //	6:满199减100; 7:99元3件; 8:满2件打九折;商品品信息
		}else if(($active_type_id == 6)||($active_type_id == 7)||($active_type_id == 8) ){
			  $goods_where = [
                'a.act_type' =>  $active_type_id,
                'b.status'=>0,
                'a.is_end' =>  0,
				'a.goods_id' => ['neq',$goods_id],
            ];
			 //$goods_list = Db::name('full_goods')->where($goods_where)->limit($fir_limit, $limit)->select();
			 $goods_list = Db::name('full_goods')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->where($goods_where)->limit($fir_limit, $limit)->select();
			  if ($goods_list) {
                foreach ($goods_list as &$val) {
					//商品信息

					$goods  = Db::name('goods')->where('goods_id',$val['goods_id'])->find();

                    $goods_info = $this->getGoodsinfo($val['goods_id']);
                    $val['goods_name'] = $goods_info['goods_name'];
                    $val['picture'] = $goods_info['picture'];

					$val['price'] = $goods['price'];
					$val['vip_price'] = $goods['vip_price'];
					$val['show_price'] = $goods['show_price'];
					$val['active_price'] = $goods['price'];//商品活动价为原价
					$val['profit'] = $val['price'] * $goods['commission'] / 100;
                }
            }
			 return $goods_list;
		}
		// 自定义活动
		else {
            //$active_info = Db::name('active_goods')->where(array('active_type_id' => $active_type_id, 'status' => 0,'goods_id' => ['neq',$goods_id]))->limit($fir_limit, $limit)->order('sort desc')->select();
            $active_info = Db::name('active_goods')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->where(['a.active_type_id' => $active_type_id, 'a.status' => 0,'b.status'=>0,'a.goods_id' => ['neq',$goods_id]])->limit($fir_limit, $limit)->select();
            if($active_info){
                foreach($active_info as $key=>$val){
                	// if($val['status']==0){


                    $act_info = $this->getCustomtActive($val['active_type_id']);
				 
                    $active_info[$key]['goods_number'] = $val['goods_num'];

					$goods  = Db::name('goods')->where('goods_id',$val['goods_id'])->find();

					//商品上架的展示
					if($goods['status']==0){
						$active_info[$key]['vip_price'] = $goods['vip_price'];
					$active_info[$key]['show_price'] = $goods['show_price'];
					 
					$active_info[$key]['rate'] = sprintf('%0.2f', $val['goods_num'] / ($val['order_number'] + $val['goods_num']) * 100);
					$active_info[$key]['rate'] = floatval($active_info[$key]['rate']);
					if($active_info[$key]['rate']<=0){
						$active_info[$key]['rate'] = 0;
					}
					// 减价
                    if ($act_info['active_type'] == 1) {
                        $active_info[$key]['price'] = $goods['price'];
                        $active_info[$key]['active_price'] = $goods['price'] - $act_info['active_type_val'];
						if($active_info[$key]['active_price']<0){
							$active_info[$key]['active_price'] = 0;
						}

                    }
                    // 打折
                    elseif ($act_info['active_type'] == 2) {
                        $active_info[$key]['price'] = $goods['price'];
                        $active_info[$key]['active_price'] = $goods['price'] * $act_info['active_type_val'] / 100;

                    }
                    // 免邮或积分
                    else {
                        $active_info[$key]['price'] = $goods['price'];
                        $active_info[$key]['active_price'] = $goods['price'];
                    }
                    // 赚取利润
                    if(isset($active_info[$key]['active_price'])){
                    	$active_info[$key]['profit'] = $active_info[$key]['active_price'] * $goods['commission'] / 100;
                    }
                    else{
                    	$active_info[$key]['profit'] = $active_info[$key]['price'] * $goods['commission'] / 100;
                    }
					}
				//}	
                }
            }
            return $active_info;
        }

    }
    /**
     * 获取自定义活动信息
     */
    public function getCustomtActive($act_id)
    {
        return Db::name('active_type')->where(['id' => $act_id])->find();
    }
    /**
     * 根据活动id 获取活动信息
     */
    public function getActiveInfo($active_id, $goods_id = null)
    {
        if ($active_id == 5) {

            $goods_where = [
                'is_end' => 0,
                'goods_id' => $goods_id
            ];
            $goods_info = Db::name('flash_goods')->where($goods_where)->find();
            $where = [
                'status' => 0,
                'id' => $goods_info['flash_id']
            ];
            $res = Db::name('flash_active')->where($where)->find();
            $data = [
                'start_time' => date('H:i',$res['start_time']),
                'end_time' =>date('H:i',$res['end_time']),
                'limit_price' => $goods_info['limit_price'],
                'goods_number' => $goods_info['goods_number']
            ];
            return $data;
        } else if($active_id == 1) {
            // 团购
            $goods_info = Db::name('team_activity')->where(['goods_id' => $goods_id])->find();
            $data = [
                'active_price' => $goods_info['team_price'],
                'goods_number' => $goods_info['goods_number'] - $goods_info['order_number']
            ];
            return $data;
         }
         //else if($active_id ==8){



        // }
        

//        $active_info = Db::name('active_type')->where(array('id' => $active_id, 'status' => 0))->find();
//        return $active_info;
    }
    /*
     * 商品搜索结果
     */
    public function goodsSearch($user_id, $key,$p){
    	//搜索历史
    	$p = $p?$p:1;
        $limit = 10;
        $fir_limit = ($p-1)* $limit;

    	if($user_id && $key){
    		$info = Db::name('search')->where(['history_key' => $key, 'history_uid' => $user_id])->field('history_id')->find();
    		if($info){
    			Db::name('search')->where('history_id', $info['history_id'])->update(['history_add_time' => time()]);
    		}
    		else{
    			$insert = [
    				'history_uid' => $user_id,
    				'history_key' => $key,
    				'history_add_time' => time()
    			];
    			Db::name('search')->insert($insert);
    		}

    	}
    	if($key){
    		$key = preg_split('/\s+/', $key);
    		$key_str = implode('%', $key);
    		$where[0] = ['like', "%$key_str%"];
//    		foreach($key as $v){
//    			$where[] = ['like', '%'.$v.'%'];
//    		}
    		$list = $this->model->where('goods_name|keywords|introduction', $where, 'or')->where(['is_gift' => 0, 'status' => 0,'prom_type'=>0,'prom_id'=>0])->limit($fir_limit, $limit)->order('is_hot desc,is_recom desc,volume desc,create_time desc')->field('goods_id,goods_name,picture,prom_id,prom_type,vip_price,price,show_price,stock,commission,active_name')->select();
    		// return $this->model->getlastsql();
    		$total = $this->model->where('goods_name|keywords|introduction', $where, 'or')->where(['is_gift' => 0, 'status' => 0,'prom_type'=>0,'prom_id'=>0])->count();
            if (!empty($list)) {
                foreach ($list as &$val) {
                    if ($val['prom_type'] == 1) {
                        $val['active_name'] = $this->ActiveName($val['prom_type']);
                        $val['price'] = Db::name('group_goods')->where(['goods_id' => $val['goods_id']])->value('group_price');
                        $val['vip_price'] = $val['price'] * (100 - $val['commission'])/100;
                    } elseif ($val['prom_type'] == 2) {
                        $val['active_name'] = $this->ActiveName($val['prom_type']);
                        $val['vip_price'] = $val['price'] * (100 - $val['commission'])/100;

                    } elseif ($val['prom_type'] == 3) {
                        $val['active_name'] = $this->ActiveName($val['prom_type']);
                        $val['price'] = Db::name('team_activity')->where(['goods_id' => $val['goods_id']])->value('team_price');
                        $val['vip_price'] = $val['price'] * (100 - $val['commission'])/100;

                    } elseif ($val['prom_type'] == 4) {
                        $val['active_name'] = $this->ActiveName($val['prom_type']);

                        $val['vip_price'] = $val['price'] * (100 - $val['commission'])/100;

                    } elseif ($val['prom_type'] == 5) {
                        $val['active_name'] = $this->ActiveName($val['prom_type']);
                        $val['price'] = Db::name('flash_goods')->where(['goods_id' => $val['goods_id']])->value('limit_price');
                        $val['vip_price'] = $val['price'] * (100 - $val['commission'])/100;

                    } elseif ($val['prom_type'] == 6 || $val['prom_type'] == 7 || $val['prom_type'] == 8) {
                        $val['active_name'] = $this->ActiveName($val['prom_type']);

                    } elseif ($val['prom_type'] > 8) {
                        $val['active_name'] = $this->ActiveName($val['prom_type']);
                        $active_info = Db::name('active_type')->where(['id' => $val['prom_type']])->find();
                        if ($active_info['active_type'] == 1) {
                            $val['price'] = $val['price'] - $active_info['active_type_val'];
                        } elseif ($active_info['active_type'] == 2) {
                            $val['price'] = $val['price'] * $active_info['active_type_val'];
                        }
                        $val['vip_price'] = $val['price'] * (100 - $val['commission'])/100;
                    }
                    $val['price'] = floatval($val['price']);
                }
            }
            
     		return ['list'=>$list,'total'=>$total];
    	}
    }
	/*
     * 精选聚惠
     */
    public function goodsPicked(){
    	$rows = $this->model->field('goods_id,goods_name,picture,price,show_price,prom_type,prom_id,commission,stock,volume')->order('is_hot desc,is_recom desc,volume desc,create_time desc')->limit(9)->select();

		//销量大于库存 前台不展示 下架商品
		 foreach ($rows as &$val) {
			if($val['volume'] >= $val['stock']){
				Db::name('goods')->where(['goods_id' => ['eq',$val['goods_id']]])->update(['status'=>1]);
			}
		}
		$rows = $this->model->field('goods_id,goods_name,picture,price,show_price,prom_type,prom_id,commission,stock,volume')->where('status',0)->order('is_hot desc,is_recom desc,volume desc,create_time desc')->limit(9)->select();
		return $rows;
    }
	/*
     * 今日特卖
     */
    public function goodsOffer(){
    	$rosws = $this->model->field('goods_id,goods_name,picture,price,vip_price,prom_type,prom_id,show_price,stock,volume,commission,stock,volume,active_name,active_state')->order('is_hot desc,is_recom desc,volume desc,create_time desc')->limit(3)->select();
		//销量大于库存 前台不展示 下架商品
		 foreach ($rosws as &$val) {
			if($val['volume'] >= $val['stock']){
				Db::name('goods')->where(['goods_id' => ['eq',$val['goods_id']]])->update(['status'=>1]);
			}
		}
		$rosws = $this->model->field('goods_id,goods_name,picture,price,vip_price,prom_type,prom_id,show_price,stock,volume,commission,active_name,active_state')->order('is_hot desc,is_recom desc,volume desc,create_time desc')->limit(3)->select();
		//获取活动信息
		$rosws = $this->ActiveInfo($rosws);
		return $rosws;
    }
	/*
     * 此商品 是否加入店铺
     */
    public function getstore($uid,$goodsid){
		$res = Db::name('store')->where('s_uid',$uid)->find();
		if(!$res){
			return -1;
		}
		$where = [
			 's_g_storeid' => $res['s_id'],
			 's_g_goodsid' => $goodsid,
		];
		$res = Db::name('store_goods')->where($where)->find();
		if(!$res){
			return 0;
		}
		return 1;
    }
    /**
     * 判断商品id 是否有活动
     */
    public function checkGoods($goods_id)
    {
        return $this->model->where(['goods_id' => $goods_id])->value('prom_type');
    }
    /**
     * 修改商品 活动状态s
     */
    public function updateGoods($goods_id, $prom_type= 0, $prom_id = 0)
    {
        return $this->model->where(['goods_id' => $goods_id])->update(['prom_type' => $prom_type, 'prom_id' => $prom_id]);
    }
    /**
     * 判断该活动 有没有该商品
     */
    public function judgeActive($goods_id, $active_id)
    {
        $where = [
            'status' => 0,
            'start_time' => ['lt', time()],
            'end_time' => ['gt', time()],
            'id' => $active_id
        ];

        $actvie_info = Db::name('active_type')->where($where)->find();
        if (!$actvie_info) {
            // 该活动不存在
            return -1;
        }
        if ($active_id == 1) {
            // 团购
            $GroupGoodsModel = new GroupGoods();
            $res = $GroupGoodsModel->find(['goods_id' => $goods_id, 'is_end' => 0]);
            if ($res) {
                $num = $res['goods_number'] - $res['order_number'];
                if ($num < 1) {
                    // 该活动商品库存不足
                    return -2;
                } else {
                    return 1;
                }
            } else {
                // 该活动没有查到该商品
                return -3;
            }
        }elseif ($active_id == 4){
			//砍价
			$BargainMode = new Bargain();
            $res = $BargainMode->find(['goods_id' => $goods_id]);
			 if ($res) {
				$stock  = $this->getStock($res['sku_id']);
                $num = $stock - $res['order_number'];
                if ($num < 1) {
                    // 该活动商品库存不足
                    return -2;
                } else {
                    return 1;
                }
            } else {
                // 该活动没有查到该商品
                return -3;
            }
			
		} elseif ($active_id == 5) {
            // 秒杀
            $FlashActivemodel = new FlashActive();
            $time =  time();
            $flash_where = [
                'start_time' => ['elt', $time],
                'end_time' => ['gt', $time],
                'status' => 0
            ];
            $FlashActiveres =$FlashActivemodel->find($flash_where);
            if (!$FlashActiveres) {
                // 该时段没有秒杀活动
                return -4;
            }
            $Flashmodel = new FlashGoods();
            $Flash_where = ['goods_id' => $goods_id, 'is_end' => 0, 'flash_id' => $FlashActiveres['id']];
            $res = $Flashmodel->find($Flash_where);
            if ($res) {
                $num = $res['goods_number'] - $res['order_number'];
                if ($num < 1) {
                    return -2;
                } else {
                    return 1;
                }
            } else {
                return -3;
            }
        }else if($active_id == 6||$active_id == 7||$active_id == 8 ){
			 // 6:满199减100;7:满99元3件;8:满2件9折
			$FullGoodsModel = new FullGoodsModel();
			$where = [
				'goods_id' => $goods_id,
				'is_end' =>0,
				'act_type' =>$active_id,

			];
            $res = $FullGoodsModel->where($where)->find();
			if ($res) {
                $num = $res['goods_number'] - $res['order_number'];
                if ($num < 1) {
                    return -2;
                } else {
                    return 1;
                }
            } else {
                return -3;
            }
		}
        $goods_where['active_type_id']=$actvie_info['id'];
        $goods_where['status'] = 0;
        $goods_where['goods_id'] = $goods_id;
        $info = Db::name('active_goods')->where($goods_where)->find();
        if ($info) {
            $num = $info['goods_num'] - $info['order_number'];

            if ($num < 1) {
                return -2;
            } else {
                return 1;
            }
        } else {
            return -3;
        }
        return 1;
    }
    /*今日推荐*/
    function todayRecomment($page,$uid){

		//$goodsList=Db::name("Goods")->where(['is_recom_today'=>1,'prom_type'=>0,'status'=>0])
        //    ->order("weigh desc,goods_id desc")->field("goods_id,goods_name,picture,prom_id,prom_type,vip_price,price,show_price,commission,volume,stock,sum_sales")
        //    ->page($page,10)
        //   ->select();
		//销量大于库存 前台不展示 下架商品
		//  foreach ($goodsList as &$val) {
		//	if($val['volume'] >= $val['stock']){
		//		Db::name('goods')->where(['goods_id' => ['eq',$val['goods_id']]])->update(['status'=>1]);
		//	}
		//}

		$goodsList=Db::name("Goods")->where(['is_recom_today'=>1,'prom_type'=>0,'status'=>0])
            ->order("weigh desc,goods_id desc")->field("goods_id,goods_name,picture,prom_id,prom_type,vip_price,price,show_price,commission,volume,stock,sum_sales")
            ->page($page,10)
            ->select();
          //foreach ($goodsList as $key=>$value){
			  // $res = $this->getstore($uid, $value['goods_id']);
					// $goodsService = new Goods();
					// $active_price = $goodsService->getActivePirce($value['price'],$value['prom_type'],$value['prom_id']);
					// $commission = $this->getCom();
					// //开启 返利
					// if($commission['shop_ctrl'] == 1){
					// 	$f_p_rate = $commission['f_s_rate'];
					// }else{
					// 	$f_p_rate = 100;
					// }
					// $goodsList[$key] = floor($active_price * $value['commission'])/ 100 * $f_p_rate/100;
				

			 
           //}
        $commission = $this->getCom();
        //开启 返利
        if($commission['f_s_rate'] == 1){
            $f_p_rate = $commission['f_s_rate'];
        }else{
            $f_p_rate = 100;
        }
        foreach ($goodsList as &$val) {
        	
        		$store_goods=Db::name('store_goods')->where(['s_g_userid' => $uid,'s_g_goodsid'=>$val['goods_id']])->find();
				if($store_goods){
					 $val['is_put'] = 1;
				}else{
					$val['is_put'] = 0;
				}

            $val['dianzhu_price'] = floor($val['price'] * $val['commission']/ 100 * $f_p_rate)/100;
			$val['vip_price'] = sprintf('%0.2f', $val['vip_price']);
			$val['vip_price'] = floatval($val['vip_price']);
			$val['show_price'] = sprintf('%0.2f', $val['show_price']);
			$val['show_price'] = floatval($val['show_price']);	
			$val['dianzhu_price'] = sprintf('%0.2f', $val['dianzhu_price']);
			$val['dianzhu_price'] = floatval($val['dianzhu_price']);	
			$val['price'] = sprintf('%0.2f', $val['price']);
			$val['price'] = floatval($val['price']);	
        }
		  $goodsList = $this->ActiveInfo($goodsList);
        return $goodsList;
    }

    /*
	 * 获得地区名称
	 */
	public function getRegion($where){
        $regionName=Db::name("region")->where($where)->value('region_name');
        return $regionName;
    }

    /*
     * 是否为活动商品
     */
    public function checkActiv(&$goods_info){
        if ($goods_info['prom_type'] == 5) {
            //判断秒杀
            $info = Db::name('flash_goods')->alias('a')->join('__FLASH_ACTIVE__ b', 'a.flash_id=b.id')->where(['a.goods_id' => $goods_info['goods_id'], 'a.is_end' => 0, 'b.status' => 0])->field('a.flash_id,a.buy_limit,a.goods_number,a.order_number,a.limit_price,b.start_time,b.end_time')->find();
            if($info){
                // $goods_info['buy_limit'] = $info['buy_limit'];
                $goods_info['stock'] = $info['goods_number'];
                $goods_info['limit_price'] = $info['limit_price'];
                $goods_info['end_time'] = '00:00:00';
                if($info['end_time'] >= time()){
                    $goods_info['end_time'] = date('H:i:s', $info['end_time'] - time());
                }
            }
        }
    	return $goods_info;
    }

    /*
     * 商品分享图片的生成
     */
    public function goodsShareImg($is_seller, $goods_id, $acti_id){
    	$QRCode = new QRcode();
    	$ds = DIRECTORY_SEPARATOR;
    	$root = dirname(dirname(dirname(__DIR__)));

    	//商品信息
    	$goods_info = $this->model->where(['goods_id' => $goods_id, 'status' => 0])->field('picture,goods_name,price,commission,vip_price,prom_type')->find();
    	if(!$goods_info){
    		return ['code' => '0', 'msg' => '商品不存在或已下架'];
    	}

//    	$goods_info['picture'] = $root.$ds.'public'.$goods_info['picture'];
    	if(!$goods_info['picture']){
    		return ['code' => '0', 'msg' => '商品图片不存在'];
    	}
    	// $goods_info['vip_price'] = sprintf('%0.2f', $goods_info['price'] * (1 - $goods_info['commission'] / 100));
    	$goods_info['vip_price'] = sprintf('%0.2f', $goods_info['vip_price']);
    	$goods_info['profit'] = sprintf('%0.2f', $goods_info['price'] - $goods_info['vip_price']);

    	//二维码图片路径
    	$img_dir = $root.$ds.'public'.$ds.'goodsShare'.$ds;
    	if(!is_dir($img_dir)){
    		mkdir($img_dir, 0777, true);
    	}
    	$qr_name = $this->createImgName('qrcode', $goods_id);
    	// while(file_exists($img_dir.$qr_name)){
    	// 	$qr_name = $this->createImgName('qrcode', time());
    	// }

    	//二维码内容
    	$qr_goods_url = url('api/fx/goodsDetail',['goodsid'=>$goods_id,'acti_id'=>$acti_id], '', true);
    	//二维码容错率
    	$qr_error_level = 'M';
    	//二维码大小
    	$qr_size = 4;
    	//二维码边距
    	$qr_margin = 3;
    	//生成二维码
    	$QRCode::png($qr_goods_url, $img_dir.$qr_name, $qr_error_level, $qr_size, $qr_margin);
    	//二维码图片信息
    	$qr_base_info = getimagesize($img_dir.$qr_name);

    	$qr_w = $qr_base_info[0];	//宽
    	$qr_h = $qr_base_info[1];	//高
    	//二维码图片画布
    	$qr_obj = imagecreatefrompng($img_dir.$qr_name);

    	//商品图片信息
    	$goods_base_info = getimagesize($goods_info['picture']);
    	$goods_ext_info = pathinfo($goods_info['picture']);
    	$goods_w = $goods_base_info[0];
    	$goods_h = $goods_base_info[1];
    	//创建商品原图片画布
    	switch($goods_base_info[2]){
    		case 1 : $goods_obj = imagecreatefromgif($goods_info['picture']); break;
    		case 2 : $goods_obj = imagecreatefromjpeg($goods_info['picture']); break;
    		case 3 : $goods_obj = imagecreatefrompng($goods_info['picture']); break;
    	}
    	//分享图片路径
//    	$share_name = $this->createImgName('share', $goods_ext_info['extension']);
    	$share_name = $this->createImgName('share', 'png');
    	while(file_exists($img_dir.$share_name)){
//    		$share_name = $this->createImgName('share', $goods_ext_info['extension']);
    		$share_name = $this->createImgName('share', 'png');
    	}
    	//创建画布
    	$img_w = 375;	//新画布宽度
    	$img_h = 600;	//新画布高度
    	$new_goods_w = 375; 	//新画布中商品图片宽度
    	$new_goods_h = 400;		//新画布中商品图片高度
    	$new_name_w = 200; 		//新画布中商品名称宽度
    	$new_qr_w = 125;	//新画布中二维码图片宽度
    	$new_qr_h = 125;	//新画布中二维码图片高度

    	$img_obj = imagecreatetruecolor($img_w, $img_h);
    	//填充背景
    	$gray = imagecolorallocate($img_obj, 245, 245, 245);
    	imagefill($img_obj, 0, 0, $gray);
    	//放入商品图片
    	imagecopyresampled($img_obj, $goods_obj, 0, 0, 0, 0, $new_goods_w, $new_goods_h, $goods_w, $goods_h);
    	//放入二维码图片
    	imagecopyresampled($img_obj, $qr_obj, 240, 420, 0, 0, $new_qr_w, $new_qr_h, $qr_w, $qr_h);
    	//加入商品名称
    	$name_size = 20;
    	$price_size = 15;
    	$font = $root.$ds.'extend'.$ds.'QRcode'.$ds.'simfang.ttf';

    	
    	$black = imagecolorallocate($img_obj, 0, 0, 0);

    	$red = imagecolorallocate($img_obj, 217, 16, 20);
    	$sub_name_1 = $goods_info['goods_name'];
    	$sub_name_2 = '';
    	if(strlen($goods_info['goods_name']) > 20){
    		$sub_name_1 = mb_strimwidth($goods_info['goods_name'], 0, 18, '','utf-8');
    		$sub_name_2 = mb_strimwidth($goods_info['goods_name'], iconv_strlen($sub_name_1, 'utf-8'), 18, '...','utf-8');
    		$name_size = 18;
    	}

    	imagettftext($img_obj, $name_size, 0, 20, 440, $black, $font, $sub_name_1);
    	imagettftext($img_obj, $name_size, 0, 20, 470, $black, $font, $sub_name_2);
    	imagettftext($img_obj, $price_size, 0, 20, 530, $black, $font, '￥'.$goods_info['price']);
    	if($is_seller){
    	    if($goods_info['prom_type']==5 && empty($goods_info['commission'])){
                $goods_info['profit'] = 0.01;
            }
            if($goods_info['profit']!=0.00){
                imagettftext($img_obj, $price_size, 0, 120, 530, $red, $font, '赚￥'.$goods_info['profit']);
            }
    	}
    	else{
    		$vip_price = '会员价￥'.$goods_info['vip_price'];
    		if(strlen($vip_price) > 18){
    			imagettftext($img_obj, $price_size, 0, 25, 550, $red, $font, $vip_price);
    		}
    		else{
    			imagettftext($img_obj, $price_size, 0, 100, 530, $red, $font, $vip_price);
    		}

    	}
    	$ext = 'jpg';
//    	switch($goods_base_info[2]){
//    		case 1 : $ext = 'gif'; imagegif($img_obj, $img_dir.$share_name); break;
//    		case 2 : $ext = 'jpg'; imagejpeg($img_obj, $img_dir.$share_name); break;
//    		case 3 : $ext = 'png'; imagepng($img_obj, $img_dir.$share_name); break;
//    	}
        imagepng($img_obj, $img_dir.$share_name);
    	//转base64
    	$img_base64 = 'data:image/'.$ext.';base64,';
    	if($fp = fopen($img_dir.$share_name, 'rb', 0)){
    		$fp_info = fread($fp, filesize($img_dir.$share_name));
    		fclose($fp);
	    	$img_base64 .= chunk_split(base64_encode($fp_info));
    	}

    	imagedestroy($qr_obj);
    	imagedestroy($goods_obj);
    	imagedestroy($img_obj);

    	return ['code' => '1', 'data' => ['img' => request()->domain().'/goodsShare/'.$share_name, 'img_64' => $img_base64]];

    }
    /*
     * 生成图片名
     */
    public function createImgName($type, $ext = ''){
    	//二维码图片
    	if($type == 'qrcode'){
    		$name = 'QR_'.$ext.'.png';
    	}
    	//商品分享
    	else if($type == 'share'){
    		$name = 'FX_'.time().'.'.$ext;
    		// $name = 'FX_'.time().'.png';
    	}
    	return $name;
    }
    /*
     * 定时任务
     */
    public function timedTask()
    {
        // 处理活动
         $this->setHuoDong();
        // 处理拼团
         $this->setPintuan();
        // 处理 预售as_list
        // $this->setYushou();
        // 处理 未支付订单
        //$this->chuliOrder();
        // 处理 定时消息
        $this->setMessage();
    }
    /*
     * 处理 定时消息
     */
    public function setMessage()
    {
        // 查询 时间到了 未发送的信息
        $where = [
            'mp_send_status' => 0,
            'mp_status' => 0,
            'mp_send_time' => ['<', time()],
        ];
        $list = Db::name('message_push')->where($where)->select();
        if (!empty($list)) {
            foreach ($list as $val) {
                $type = $val['mp_name'];
                $where = [];
                // vip
                if ($type == 1) {
                    $where['is_seller'] = 0;
                    $where['user_mobile'] = ['neq', ''];
                    $user_list = db('users')->field('user_id,user_name,user_mobile,client_id,app_system')->where($where)->select();
                } elseif ($type == 2) { // 普通店主
                    $user_list = db('store')->alias('a')->join('__USERS__ b','a.s_uid=b.user_id')->field('b.user_id,b.user_name,b.user_mobile,b.client_id,b.app_system')->where(['a.s_grade' => 1])->select();
                } elseif ($type == 3) { // 高级店主
                    $user_list = db('store')->alias('a')->join('__USERS__ b','a.s_uid=b.user_id')->field('b.user_id,b.user_name,b.user_mobile,b.client_id,b.app_system')->where(['a.s_grade' => 2])->select();
                } elseif ($type == 4) { // 旗舰店主
                    $user_list = db('store')->alias('a')->join('__USERS__ b','a.s_uid=b.user_id')->field('b.user_id,b.user_name,b.user_mobile,b.client_id,b.app_system')->where(['a.s_grade' => 3])->select();
                } elseif ($type == 5) { // 店主
                    $where['is_seller'] = 1;
                    $user_list = db('users')->field('user_id,user_name,user_mobile,client_id,app_system')->where($where)->select();
                } else {    // 所有用户
                    $user_list = db('users')->field('user_id,user_name,user_mobile,client_id,app_system')->where(['user_mobile' => ['neq', '']])->select();
                }
                if (!empty($user_list)) {
                    // 手机通知栏
                    $tixing = false;
                    if ($val['mp_type'] == 1) { // 文字推送
                        $mp_addr = explode(',', $val['mp_address']);
                        // 手机通知栏提醒
                        if (in_array(1, $mp_addr)) {
                            $tixing = true;
                        }
                    }
                    $mobile_arr = [];// 手机号
                    $and_client_arr = []; // 手机型号
                    $ios_client_arr = []; // 手机型号
                    foreach ($user_list as $value) {
                        $mobile_arr[] = $value['user_mobile'];
                        if ($value['app_system'] == 1) {
                            $ios_client_arr[]['client_id'] = $value['client_id'];
                        } else {
                            $and_client_arr[]['client_id'] = $value['client_id'];
                        }
                    }
                    if ($tixing) {
                        if ($ios_client_arr) {
                            $ios_client_arr['system'] = 1;
                            $this->tuisong($ios_client_arr, $val['mp_title'], $val['mp_content']);
                        }
                        if ($and_client_arr) {
                            $and_client_arr['system'] = 2;
                            $this->tuisong($and_client_arr, $val['mp_title'], $val['mp_content']);
                        }
                    }
                }
                Db::name('message_push')->where(['mp_id' => $val['mp_id']])->update(['mp_send_status' => 1]);
            }
        }
    }
    /*
     * 推送消息
     * $clientids 数组
     */
    public function tuisong($clientids, $title, $content)
    {
        $msg = [
            'content'=>$content,//透传内容
            'title'=>$title,//通知栏标题
            'text'=>$content,//通知栏内容
        ];
        $Pushs = new Pushs();
        $Pushs->getTypes($msg,$clientids);
    }
	 /*
	  * 处理活动
	  */
	 public function setHuoDong()
     {
         // 处理活动 判断 活动是否在正常状态
         $active_list = Db::name('active_type')->select();
         $goods_model = $this->model;
         $new_time = time();
         foreach ($active_list as $value) {
             if ($value['id'] == 1) {
                 // 团购 活动 如果在活动时间内 则在商品表添加活动表示， 否则 取消标识
                 $list = Db::name('group_goods')->field('id,goods_id')->where(['is_end' => 0])->select();
                 if ($value['start_time'] <= $new_time && $value['end_time'] > $new_time && $value['status'] == 0) {

                     if (!empty($list)) {
                         foreach ($list as $key => $val) {
                             $where = [];
                             $data = [];
                             $where['goods_id'] = $val['goods_id'];
                             $data['prom_type'] = 1;
                             $data['prom_id'] = $val['id'];
                             $goods_model->where($where)->update($data);
                         }
                     }
                 } else {
                     if (!empty($list)) {
                         $ids_arr = array_column($list, 'goods_id');
                         $ids = implode(',', $ids_arr);
                         $goods_model->where(['goods_id'=> ['in', $ids]])->update(['prom_type' => 0]);
                     }
                 }

             } elseif ($value['id'] == 2) {
                 // 预售 活动 如果在活动时间内 则在商品表添加活动表示， 否则 取消标识
                 $list = Db::name('goods_activity')->field('act_id,goods_id')->where(['is_end' => 0])->select();
                 if ($value['start_time'] <= $new_time && $value['end_time'] > $new_time && $value['status'] == 0) {
                     if (!empty($list)) {
                         foreach ($list as $key => $val) {
                             $where = [];
                             $data = [];
                             $where['goods_id'] = $val['goods_id'];
                             $data['prom_type'] = 2;
                             $data['prom_id'] = $val['act_id'];
                             $goods_model->where($where)->update($data);
                         }
                     }
                 } else {
                     if (!empty($list)) {
                         $ids_arr = array_column($list, 'goods_id');
                         $ids = implode(',', $ids_arr);
                         $goods_model->where(['goods_id'=> ['in', $ids]])->update(['prom_type' => 0]);
                     }
                 }
             } elseif ($value['id'] == 3) {
                 // 拼团 活动 如果在活动时间内 则在商品表添加活动表示， 否则 取消标识
                 $list = Db::name('team_activity')->field('id,goods_id')->where(['status' => 0])->select();
                 if ($value['start_time'] <= $new_time && $value['end_time'] > $new_time && $value['status'] == 0) {
                     if (!empty($list)) {
                         foreach ($list as $key => $val) {
                             $where = [];
                             $data = [];
                             $where['goods_id'] = $val['goods_id'];
                             $data['prom_type'] = 3;
                             $data['prom_id'] = $val['id'];
                             $goods_model->where($where)->update($data);
                         }
                     }
                 } else {
                     if (!empty($list)) {
                         $ids_arr = array_column($list, 'goods_id');
                         $ids = implode(',', $ids_arr);
                         $goods_model->where(['goods_id'=> ['in', $ids]])->update(['prom_type' => 0]);
                     }
                 }
             } elseif ($value['id'] == 4) {
                 // 砍价 活动 如果在活动时间内 则在商品表添加活动表示， 否则 取消标识
                 $list = Db::name('bargain')->field('id,goods_id')->where(['status' => 0])->select();
                 if ($value['start_time'] <= $new_time && $value['end_time'] > $new_time && $value['status'] == 0) {
                     if (!empty($list)) {
                         foreach ($list as $key => $val) {
                             $where = [];
                             $data = [];
                             $where['goods_id'] = $val['goods_id'];
                             $data['prom_type'] = 4;
                             $data['prom_id'] = $val['id'];
                             $goods_model->where($where)->update($data);
                         }
                     }
                 } else {
                     if (!empty($list)) {
                         $ids_arr = array_column($list, 'goods_id');
                         $ids = implode(',', $ids_arr);
                         $goods_model->where(['goods_id'=> ['in', $ids]])->update(['prom_type' => 0]);
                     }
                 }
             } elseif ($value['id'] == 5) {
                 // 秒杀 活动 如果在活动时间内 则在商品表添加活动表示， 否则 取消标识
//                $hour = date('H', time());
//                $flash_where = [
//                    'start_time' => ['elt', $hour],
//                    'end_time' => ['gt', $hour],
//                    'status' => 0
//                ];
//                $flash_id = Db::name('flash_active')->where($flash_where)->value('id');
                 $flash_goods_where = ['is_end' => 0];
                 $list = Db::name('flash_goods')->field('id,goods_id,flash_id')->where($flash_goods_where)->select();
                 if ($value['start_time'] <= $new_time && $value['end_time'] > $new_time && $value['status'] == 0 ) {
                     if (!empty($list)) {
                         foreach ($list as $key => $val) {
//                            if ($val['flash_id'] == $flash_id) {
                             $where = [];
                             $data = [];
                             $where['goods_id'] = $val['goods_id'];
                             $data['prom_type'] = 5;
                             $data['prom_id'] = $val['id'];
                             $goods_model->where($where)->update($data);
//                            } else {
//                                $goods_model->where(['goods_id'=> $val['goods_id']])->update(['prom_type' => 0]);
//                            }

                         }
                     }
                 } else {
                     if (!empty($list)) {
                         $ids_arr = array_column($list, 'goods_id');
                         $ids = implode(',', $ids_arr);
                         $goods_model->where(['goods_id'=> ['in', $ids]])->update(['prom_type' => 0]);
                     }
                 }
             } elseif ($value['id'] == 6) {
                 // 满199减100 活动 如果在活动时间内 则在商品表添加活动表示， 否则 取消标识
                 $list = Db::name('full_goods')->field('id,goods_id')->where(['act_type' => 6, 'is_end' => 0])->select();
                 if ($value['start_time'] <= $new_time && $value['end_time'] > $new_time && $value['status'] == 0) {
                     if (!empty($list)) {
                         foreach ($list as $key => $val) {
                             $where = [];
                             $data = [];
                             $where['goods_id'] = $val['goods_id'];
                             $data['prom_type'] = 6;
                             $data['prom_id'] = $val['id'];
                             $goods_model->where($where)->update($data);
                         }
                     }
                 } else {
                     if (!empty($list)) {
                         $ids_arr = array_column($list, 'goods_id');
                         $ids = implode(',', $ids_arr);
                         $goods_model->where(['goods_id'=> ['in', $ids]])->update(['prom_type' => 0]);
                     }
                 }
             } elseif ($value['id'] == 7) {
                 // 满99元3件 活动 如果在活动时间内 则在商品表添加活动表示， 否则 取消标识
                 $list = Db::name('full_goods')->field('id,goods_id')->where(['act_type' => 7, 'is_end' => 0])->select();
                 if ($value['start_time'] <= $new_time && $value['end_time'] > $new_time && $value['status'] == 0) {
                     if (!empty($list)) {
                         foreach ($list as $key => $val) {
                             $where = [];
                             $data = [];
                             $where['goods_id'] = $val['goods_id'];
                             $data['prom_type'] = 7;
                             $data['prom_id'] = $val['id'];
                             $goods_model->where($where)->update($data);
                         }
                     }
                 } else {
                     if (!empty($list)) {
                         $ids_arr = array_column($list, 'goods_id');
                         $ids = implode(',', $ids_arr);
                         $goods_model->where(['goods_id'=> ['in', $ids]])->update(['prom_type' => 0]);
                     }
                 }
             } elseif ($value['id'] == 8) {
                 // 满2件9折 活动 如果在活动时间内 则在商品表添加活动表示， 否则 取消标识
                 $list = Db::name('full_goods')->field('id,goods_id')->where(['act_type' => 8, 'is_end' => 0])->select();
                 if ($value['start_time'] <= $new_time && $value['end_time'] > $new_time && $value['status'] == 0) {
                     if (!empty($list)) {
                         foreach ($list as $key => $val) {
                             $where = [];
                             $data = [];
                             $where['goods_id'] = $val['goods_id'];
                             $data['prom_type'] = 8;
                             $data['prom_id'] = $val['id'];
                             $goods_model->where($where)->update($data);
                         }
                     }
                 } else {
                     if (!empty($list)) {
                         $ids_arr = array_column($list, 'goods_id');
                         $ids = implode(',', $ids_arr);
                         $goods_model->where(['goods_id'=> ['in', $ids]])->update(['prom_type' => 0]);
                     }
                 }
             } else {
                 // 自定义 活动 如果在活动时间内 则在商品表添加活动表示， 否则 取消标识
                 $list = Db::name('active_goods')->field('id,goods_id')->where(['active_type_id' =>$value['id'], 'status' => 0])->select();
                 if ($value['start_time'] <= $new_time && $value['end_time'] > $new_time && $value['status'] == 0) {
                     if (!empty($list)) {
                         foreach ($list as $key => $val) {
                             $where = [];
                             $data = [];
                             $where['goods_id'] = $val['goods_id'];
                             $data['prom_type'] = $value['id'];
                             $data['prom_id'] = $val['id'];
                             $goods_model->where($where)->update($data);
                         }
                     }
                 } else {
                     if (!empty($list)) {
                         $ids_arr = array_column($list, 'goods_id');
                         $ids = implode(',', $ids_arr);
                         $goods_model->where(['goods_id'=> ['in', $ids]])->update(['prom_type' => 0]);
                     }
                 }
             }
         }
     }


    /**
     * 处理拼团
     */
    public function setPintuan()
    {
        // 查出所有 超时的拼团
        $list = Db::name('team_found')->field('id')->where(['end_time' =>['<', time()], 'status' => 1])->select();
        if (!empty($list)) {
            foreach ($list as $val) {
                // 查出该拼团的所有人
                $team_follow_list = Db::name('team_follow')->field('id,order_id')->where(['found_id' =>$val['id']])->select();
                if (!empty($team_follow_list)) {
                    foreach ($team_follow_list as $value) {
                        // 查出订单  返回金额
                        $this->orderTuikuan($value['order_id']);
                    }
                }
                Db::name('team_found') ->where(['id' => $val['id']])->update(['status' => 3]);
            }
        }
    }
    /*
     * 订单退款
     * 优惠券、积分、充值卡余额、元宝、（使用支付宝、微信、银联 金额退还到余额）
     */
    public function orderTuikuan($order_id)
    {
        if (!$order_id) {
            return false;
        }
        $order_info = Db::name('order')->where(['order_id' => $order_id])->find();
        var_dump($order_info, 3038);
        if (!empty($order_info)) {
            // 优惠券 不再退回20190106
//            if (!empty($order_info['order_coupon_id'])) {
//                Db::name('coupon_users')->where(['c_id' => $order_info['order_coupon_id']])->update(['coupon_stat' => 1]);
//            }
            // 积分
            if (!empty($order_info['order_pay_points'])) {
                Db::name('users')->where(['user_id' => $order_info['order_uid']])->setInc('user_points', $order_info['order_pay_points']);
            }
            // 元宝
            if ($order_info['yz_id']) {
                $this->addYinzi($order_info['order_uid'], $order_info['yz_id']);
            }
            // 充值卡
            if ($order_info['rc_id'] && $order_info['rc_amount']) {
                $rc_info = Db::name('user_rc')->where('card_id', $order_info['rc_id'])->field('card_uid,card_no,card_balance,card_stat')->find();
                if ($rc_info['card_stat'] == 2) {
                    Db::name('user_rc')->where('card_id', $order_info['rc_id'])->update(['card_stat' => 1, 'card_balance' => $order_info['rc_amount']]);
                    //充值卡记录
                    $OrderService = new OrderService();
                    $OrderService->add_rc_log($rc_info['card_uid'],$order_info['rc_id'],$order_info['rc_amount'],1);
                } else {
                    Db::name('user_rc')->where('card_id', $order_info['rc_id'])->setInc('card_balance', $order_info['rc_amount']);
                    //充值卡记录
                    $OrderService = new OrderService();
                    $OrderService->add_rc_log($rc_info['card_uid'],$order_info['rc_id'],$order_info['rc_amount'],1);
                }
            }

            // 实际支付金额
            Db::name('users')->where(['user_id' => $order_info['order_uid']])->setInc('user_account', $order_info['order_pay_price']);
            // 写入 余额日志
            $log_info = [
                'a_uid' => $order_info['order_uid'],
                'acco_num' => $order_info['order_pay_price'],
                'acco_type' => 12, // 退款
                'acco_desc' => '订单退款',
                'acco_time' => time(),
                'order_id' => $order_info['order_id']
            ];
            Db::name('account_log')->insert($log_info);
        }
        Db::name('order')->where(['order_id' => $order_id])->update(['order_status' => 5]);
    }
    /*
     * 处理预售
     */
    public function setYushou()
    {
        $yushouActive_info = Db::name('active_type')->where(['id' => 2, 'status' => 0, 'start_time' => ['<', time()]])->find();
        if (!empty($yushouActive_info)) {
            // 还没有结束 预售
            if ($yushouActive_info['pay_end_time'] > time()) {
                return false;
            }
        } else {
            return false;
        }
        // 预售已结束，修改没有支付尾款的 订单
        $list = Db::name('order')->field('order_id')->where(['order_prom_type' => 2, 'pay_status'=>0, 'order_status' =>0])->select();
        if (!empty($list)) {
            $data = [
                'pay_status' => 2,
                'order_pay_time' => time(),
                'order_finish_time' => time()
            ];
            $order_ids = array_column($list, 'order_id');
            Db::name('order')->where(['order_id' => ['in', implode(',', $order_ids)]])->update($data);
            foreach ($list as $value) {
                $order_goods = Db::name('order_goods')->where(['og_order_id' => $value['order_id']])->find();
                $this->addGoodsStock(1, $order_goods['og_goods_id'], $order_goods['og_goods_spec_id'], 2);
            }
        }
    }
    /*
     * 待支付订单 超时处理
     */
    public function chuliOrder()
    {
        // 20分钟前
        $new_time = time()- 60*20;
        // 排除 预售商品
        $list = Db::name('order')
            ->alias('a')
            ->field('a.*')
            ->join('__ORDER_GOODS__ b', 'b.og_order_id = a.order_id')
            ->where(['a.order_status' => 0, 'a.order_create_time' => ['<', $new_time], 'b.og_acti_id' => ['neq', 2]])
            ->select();
        if (!empty($list)) {
            foreach ($list as $val) {
                $order_goods_info = Db::name('order_goods')->where(['og_order_id' =>$val['order_id'] ])->select();
                foreach ($order_goods_info as $value) {
                    $this->addGoodsStock($value['og_goods_num'], $value['og_goods_id'], $value['og_goods_spec_id'], $value['og_acti_id']);
               }
                // 元宝
                if ($val['yz_id']) {
                    $this->addYinzi($val['order_uid'], $val['yz_id']);
                }
                // 优惠券 不再退 20190106
//                if($val['order_coupon_id']){
//                    Db::name('coupon_users')->where(['c_uid' => $val['order_uid'], 'c_id' => $val['order_coupon_id']])->update(['coupon_stat' => 1]);
//                }
                // 充值卡
                if ($val['rc_id']) {
                    $rc_info = Db::name('user_rc')->where('card_id', $val['rc_id'])->field('card_uid,card_no,card_balance,card_stat')->find();
                    if ($rc_info['card_stat'] == 2) {
                        Db::name('user_rc')->where('card_id', $val['rc_id'])->update(['card_stat' => 1, 'card_balance' => $val['rc_amount']]);
                        //充值卡记录
                        $OrderService = new OrderService();
                    	$OrderService->add_rc_log($rc_info['card_uid'],$val['rc_id'],$val['rc_amount'],1);
                    } else {
                        Db::name('user_rc')->where('card_id', $val['rc_id'])->setInc('card_balance', $val['rc_amount']);
                        //充值卡记录
                        $OrderService = new OrderService();
                    	$OrderService->add_rc_log($rc_info['card_uid'],$val['rc_id'],$val['rc_amount'],1);
                    }
                }
                $user_info = Db::name('users')->where('user_id', $val['order_uid'])->field('user_name')->find();
                // 日志
                $log = [
                    'o_log_orderid' => $val['order_id'],
                    'o_log_role' => $user_info['user_name'],
                    'o_log_desc' => '超时自动取消订单',
                    'o_log_addtime' => time()
                ];
                Db::name('order_log')->insert($log);
            }
            $ids = array_column($list, 'order_id');
            Db::name('order')->where(['order_id' => ['in', implode(',', $ids)]])->update(['order_status' =>5 ]);
        }
    }
    /*
     * 退元宝
     */
    public function addYinzi($uid, $yinzi_id)
    {
        $result = Db::name('yinzi')->where(['yin_uid' => $uid, 'yin_id' => $yinzi_id])->update(['yin_stat' => 2]);
        if($result){
            //元宝日志
            $yinzi_log=[
                'y_log_yid'=>$yinzi_id,
                'y_log_uid'=> $uid,
                'y_log_desc'=>'取消订单退回元宝',
                'y_log_addtime'=>time(),
            ];
            Db::name('yinzi_log')->insert($yinzi_log);
        }

    }
    /*
     * 增加库存
     */
    public function addGoodsStock($num = 1, $goods_id, $sku_id = 0, $prom_type = 0)
    {
        Db::name('goods')->where(['goods_id' => $goods_id])->setInc('stock', $num);
        if ($sku_id) {
            Db::name('goods_sku')->where(['sku_id' => $sku_id])->setInc('stock', $num);
        }
        if ($prom_type > 0) {
            // 增加库存 减少销量
            if ($prom_type == 1) {
                Db::name('group_goods')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
                Db::name('group_goods')->where(['goods_id' => $goods_id])->setInc('goods_number', $num);
            } elseif($prom_type == 2) {
                Db::name('goods_activity')->where(['goods_id' => $goods_id])->setInc('total_goods', $num);
                Db::name('goods_activity')->where(['goods_id' => $goods_id])->setDec('act_count', $num);
            } elseif ($prom_type == 3) {
                Db::name('team_activity')->where(['goods_id' => $goods_id])->setInc('goods_number', $num);
                Db::name('team_activity')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
            } elseif ($prom_type == 4) {
                Db::name('bargain')->where(['goods_id' => $goods_id])->setInc('goods_number', $num);
                Db::name('bargain')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
            } elseif ($prom_type == 5) {
                Db::name('flash_goods')->where(['goods_id' => $goods_id])->setInc('goods_number', $num);
                Db::name('flash_goods')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
            } elseif ($prom_type == 6 || $prom_type == 7 || $prom_type ==8) {
                Db::name('full_goods')->where(['goods_id' => $goods_id])->setInc('goods_number', $num);
                Db::name('full_goods')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
            } else {
                Db::name('active_goods')->where(['goods_id' => $goods_id])->setInc('goods_num', $num);
                Db::name('active_goods')->where(['goods_id' => $goods_id])->setDec('order_number', $num);
            }

        }
    }
	/*
     * 获取商品评价信息
     */
    public function begEvales($order='',$limit='',$map='')
    {
			$list = Db::name('order_remark')->where($map)->order($order)->limit($limit)->select();
		return $list;
    }
	/*
     * 商品评价
     */
    public function getEvales($order='',$limit='',$map='')
    {
			$list = Db::name('order_remark')->alias('a')->join('ht_users b','a.or_uid=b.user_id')->join('ht_goods c','a.or_goods_id=c.goods_id')->field('a.or_cont,a.or_id,a.or_add_time,a.or_goods_id,a.or_scores,a.status,a.or_thumb,b.user_name,c.goods_name')->where($map)->order($order)->limit($limit)->select();
		return $list;
    }
	/*
     * 商品评价
     */
    public function editEvalu($where)
    {
		$res = Db::name('order_remark')->alias('a')->join('ht_users b','a.or_uid=b.user_id')->join('ht_goods c','a.or_goods_id=c.goods_id')->field('a.or_cont,a.or_id,a.or_add_time,a.or_goods_id,a.or_scores,a.status,a.or_thumb,b.user_name,c.goods_name')->where($where)->find();
		return $res;
    }
	/*
     * 商品审核
     */
    public function updateEvalu($map,$row)
    {
		$res = Db::name('order_remark')->where($map)->update($row);
		return $res;
    }
	/*
     * 商品审核
     */
    public function deleteEvalu($where)
    {
		$res = Db::name('order_remark')->delete($where);
		return $res;
    }
	/*
     * 商品审核
     */
    public function stockWarn($map='',$order='',$limit='')
    {	//库存预警
		$res = Db::name('config')->value('warn_stock');
		if(!$res){
			$res = 200;
		}
		 $where = [
            'a.stock' => ['elt', $res],
        ];
		$list = Db::name('goods_sku')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->field('a.sku_id,a.code,a.sku_name,a.goods_id,a.stock,a.attr_value,b.status,b.goods_name,b.goods_numbers,b.category_id')->where($where)->where($map)->order($order)->limit($limit)->select();
		
		return $list;
    }	
	/*
     * 商品审核数据列表
     */
    public function WarnNum($map='',$order='')
    {	//库存预警
		$res = Db::name('config')->value('warn_stock');
		if(!$res){
			$res = 200;
		}
		 $where = [
            'a.stock' => ['elt', $res],
			'b.status' =>['neq',3]
        ];
		$list = Db::name('goods_sku')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->field('a.sku_id,a.code,a.sku_name,a.goods_id,a.stock,a.attr_value,b.status,b.goods_name,b.category_id')->where($where)->where($map)->order($order)->select();
		
		return $list;
    }/*
     * 商品审核信息获取
     */
    public function getWarn($where)
    {	 
		$list = Db::name('goods_sku')->alias('a')->join('ht_goods b','a.goods_id=b.goods_id')->field('a.sku_id,a.code,a.sku_name,a.goods_id,a.stock,a.attr_value,b.status,b.goods_name,b.category_id,b.goods_numbers,b.volume,b.picture,b.images,b.description')->where($where)->find();
		
		return $list;
    }
	/*
     * 商品审核
     */
    public function updateWarn($map='',$row='')
    {
		if($row){
			$goods['status'] = $row['status']; 
			$sku['stock'] = $row['stock']; 
		}
		$res = Db::name('goods_sku')->where('sku_id',$map['sku_id'])->update($sku);
		if($res !== false){
			$res = Db::name('goods')->where('goods_id',$map['goods_id'])->update($goods);
		}
		return $res;
    }
	/*
     * 获取商品规格
     */
    public function getGui($goods_id=''){
    
		$list = Db::name('goods_sku')->where('goods_id',$goods_id)->select();
		 
		return $list;
    }
	/*
     * 获取商品规格
     */
    public function huoquGui($goods_id=''){
    
		$list = Db::name('goods_sku')->where('goods_id',$goods_id)->select();
		return $list;
    }
	/*
     * 获取砍价商品 sku_id
     */
    public function getSkuId($id=''){
   
		$res = Db::name('bargain')->where('id',$id)->field('sku_id')->find();
		if($res){
			return $res['sku_id'];
		} 
		return $res;
    }/*
     * 活动标签
     */
    public function ActiveName($prom_type=''){
   
		$res = Db::name('active_type')->where('id',$prom_type)->field('active_type_name')->find();
		return $res['active_type_name'];
    }
    /*
     * 获取 礼包商品
     */
    public function getLibao()
    {
        $list = Db::name('goods')->where(['is_gift' => 1,'status'=>0])->field('goods_id,goods_name,price,picture,stock,weigh')->order('weigh desc')->select();
		if($list){
			foreach($list as $key=>$val){
				$list[$key]['price'] = sprintf('%0.2f', $val['price']);
				$list[$key]['price'] = floatval($val['price']);
			}	
		}
        return $list;
    }
    /*
     * 获取 礼包商品详情
     */
    public function getLibaoInfo($goods_id)
    {
        $info = Db::name('goods')->alias('a')->where(['a.is_gift' => 1, 'a.goods_id' => $goods_id])->join('__GOODS_SKU__ b', "b.goods_id = a.goods_id")->field('a.goods_id,a.goods_name,a.price,a.picture,a.description,a.stock,b.sku_id')->find();
        $info['description'] = explode(',', $info['description']);
		$info['price'] = sprintf('%0.2f', $info['price']);
		$info['price'] = floatval($info['price']);
        return $info;
    }
    /*
     * 获取省列表
     */
    public function getProvince()
    {
        return Db::name('region')->where(['parent_id' => 0])->select();
    } 
	/*
     * 获取省列表
     */
    public function getSup($map)
    {
         $list = Db::name('goods_brand')->alias('a')->join('__SUPPLIER__ c', "c.id = a.supplier_id")->where($map)->field('c.*')->select();
         return $list;
    } 
	
	/*
     * 获取属性库存
	 $sku_id
     */
    public function getStock($sku_id)
    {
		$stock = Db::name('goods_sku')->where('sku_id',$sku_id)->value('stock');
		if($stock ){
			return $stock ;
		}else{
			return  0;
		}
	
	}
	// 获取活动标签
    public function getActive_label($id)
    {
		$id ==''?0:$id;
        $active_info = Db::name('active_type')->where(array('id' => $id))->find();
        return $active_info['label_title'];
    }
	// 获取活动价格
    public function getActivePirce($price,$prom_type='',$prom_id='')
    {
		 
		$active =$price;
		if($prom_type){
			if($prom_type<=8){
				//团购
				if($prom_type == 1){
					$group_goods = Db::name('group_goods')->where('id',$prom_id)->field('price_type,price_reduce')->find();
					//折扣
					if($group_goods['price_type'] == 1){
						$active = $price * (100 - $group_goods['price_reduce']) / 100;
					}else if($group_goods['price_type'] == 0){
						$active = $price - $group_goods['price_reduce'];
					}
				//预售
				}else if($prom_type == 2){
					$active_goods = Db::name('goods_activity')->where('act_id',$prom_id)->field('deposit_use')->find();
					$active = $price - $active_goods['deposit_use'];
				//拼团
				}else if($prom_type == 3){
					$order = 'sort desc';
					$active_goods = Db::name('team_activity')->where('id',$prom_id)->field('price_type,price_reduce')->order($order)->find();
					if($active_goods['price_type'] == 1){
						$active = $price * (100 - $active_goods['price_reduce']) / 100;
					}else if($active_goods['price_type'] == 0){
						$active = $price - $active_goods['price_reduce'];
					}
				//砍价
				}else if($prom_type == 4){
					$active_goods = Db::name('bargain')->where('id',$prom_id)->field('end_price')->find();
					$active = $active_goods['end_price'];
				//抢购/秒杀
				}else if($prom_type == 5){
					$active_goods = Db::name('flash_goods')->where('id',$prom_id)->field('price_type,price_reduce')->find();
					if($active_goods['price_type'] == 1){
						$active = $price * (100 - $active_goods['price_reduce']) / 100;
					}else if($active_goods['price_type'] == 0){
						$active = $price - $active_goods['price_reduce'];
					}
				//满减
				}else if($prom_type == 6){
					$active = $price;
				//99元3件
				}else if($prom_type == 7){
					$active = $price;
				//满两件九折	
				}else if($prom_type == 8){
					$active = $price;
				}
			}else{
				$order = 'sort desc';
				$active_goods = Db::name('active_goods')->alias('a')->join('active_type b','b.id=a.active_type_id')->join('goods c','a.goods_id = c.goods_id')->field('b.active_type,b.active_type_val')->order($order)->where(['a.id'=>$prom_id])->find();
				//1减价2打折3免邮4积分
				if($active_goods['active_type'] == 2){
						$active = $price * (100 - $active_goods['active_type_val']) / 100;
					}else if($active_goods['active_type'] == 1){
						$active = $price - $active_goods['active_type_val'];
					} 
			}
		}	
		return $active;
	}

    /**
     * 每天12点计算培训费、佣金
     */
    public function midnightTask()
    {
        $res1 = $this->calculateCommi();//可结算佣金
        $res3 = $this->trainingFee();//培训费
        $res2 = $this->upGrade();//店铺升级     
    }

    /**
     * 可结算佣金
     */
    public function calculateCommi()
    {
        //计算佣金
        //确认收货超过7天的未发生售后的，或者发生售后，但是没有退款的
        $time=7*24*3600;
        $where_c = [
            'a.is_settle'=>0,
            'a.commi_add_time'=>['<',time()-$time],
            'b.after_status'=>['in',[0,2]]
        ];
        $commis = Db::name('commission')->alias('a')
            ->join('__ORDER__ b','a.commi_order_id=b.order_id','LEFT')
            ->where($where_c)
            ->field('a.*')
            ->select();

        if(empty($commis)) return false;
        $user_service = new UserService();
        $commis_ids = [];
        foreach ($commis as $s){
            if(empty($s['commi_uid'])) continue;
            if($s['uid_role']==1){
                //vip购物，上级得佣金
                $user_service->changeAccount($s['commi_p_uid'], 4, $s['commi_p_price'],$s['commi_order_id']);
            }else{
                //店主
                $user_service->changeAccount($s['commi_uid'], 4, $s['commi_price'],$s['commi_order_id']);
            }
            $commis_ids[] = $s['commi_id'];
        }
        if(!empty($commis_ids)){
            Db::name('commission')->where(['commi_id'=>['in',$commis_ids]])->update(['is_settle'=>1]);
        }
        return true;
    }

    /**
     * 店铺升级
     */
    public function upGrade()
    {
        //统计大礼包，判断是否升级
        //高级店，直接销售5000大礼包
        $where_u = [
            'a.is_seller'=>1,
            'b.s_grade'=>1
        ];
        $times = time();
        $uids = Db::name('users')->alias('a')->join('__STORE__ b ','a.user_id= b.s_uid')->where($where_u)->field('a.user_id')->select();
        if(empty($uids)) return false;
        $gj = [];
        foreach ($uids as $u){
            $sum_money = Db::name('gift_log')->where('log_p_uid',$u['user_id'])->sum('log_order_price');
            if(!empty($sum_money) && $sum_money>5000){
                $gj[] = $u['user_id'];
            }
        }
        if(!empty($gj)){
            //更新为高级店
            Db::name('store')->where(['s_uid'=>['in',$gj]])->update(['s_grade'=>2,'s_better_time'=>$times]);
        }
        //旗舰店，大礼包销售额200000
        $s_uids = Db::name('store')->where(['s_grade'=>2])->field('s_uid')->select();
        if(empty($s_uids)) return false;
        $best = [];
        foreach ($s_uids as $v){
            $my_child = $this->getAllChild($v['s_uid']);
            if(empty($my_child)) continue;
            $my_child[] = $v['s_uid'];
//            $my_child = implode(',', $my_child);
            $gift_total = Db::name('gift_log')->where(['log_uid' => ['in', $my_child]])->sum('log_order_price');
            if(!empty($gift_total) && $gift_total>200000){
                $best[] = $v['s_uid'];
            }
        }

        if(empty($best)) return false;
//        $best = implode(',',$best);
        //更新为高级店
        $res = Db::name('store')->where(['s_uid'=>['in',$best]])->update(['s_grade'=>3,'s_best_time'=>$times]);
        return $res;
    }

    /**
     * 培训费
     */
    public function trainingFee()
    {
        $end = time();
        $start = $end - 1*24*3600;
        // $start = 1548749496;
        $map['log_add_time'] = array('between',$start.','.$end);
        $gift_logs = Db::name('gift_log')->where($map)->column('log_uid');
        if(empty($gift_logs)) return false;
        //查这些人的上级有没有高级店主或者旗舰店主
        foreach ($gift_logs as $v){
            $st = Db::name('users_tree')->alias('a')->join('__STORE__ b ','b.s_uid= a.t_p_uid')->where('a.t_uid',$v)->field('a.t_p_uid,s_grade')->find();
            if(empty($st)) continue;
            $this->dealTrain($st,50,2);
            //旗舰店销售大礼包
            if($st['s_grade']==3){
                //看这个旗舰店主上面是否还有旗舰店主
                $qj_store_p = Db::name('users_tree')->alias('a')->join('__STORE__ b ','b.s_uid= a.t_p_uid')->where(['a.t_uid'=>$st['t_p_uid'],'b.s_grade'=>3])->value('a.t_p_uid');
                if(empty($qj_store_p)) continue;
                //找上级旗舰店主下面有多少个直接旗舰店主
                $where_qj = [
                    'a.t_p_uid'=>$qj_store_p,
                    'b.s_grade'=>3
                ];
                $qj_sum_infos = Db::name('users_tree')->alias('a')->join('__STORE__ b ','b.s_uid= a.t_uid')->where($where_qj)->order('b.s_best_time,a.t_addtime')->column('a.t_uid');
                //该旗舰店排上级旗舰店发展的旗舰店的第几位
                //A旗舰店发展第一位直属旗舰店B，B直接销售一套大礼包，A得20培训费，A每直接增加1位旗舰店，该旗舰店售出一套礼包，培训费增加5元
                $key  =  array_search ( $st['t_p_uid'] ,  $qj_sum_infos);
                $mon = $key * 5 + 20;
                $this->insertBonus($qj_store_p,$mon,2);
            }
        }
    }

    /**
     *
     */
    public function dealTrain($st,$num,$grade)
    {
        $flag = true;
        while($flag){
            if($st['s_grade']==3){
                $this->insertBonus($st['t_p_uid'],80,2);
                break;
            }
            if($st['s_grade']==2){
                $this->insertBonus($st['t_p_uid'],50,2);
                $this->dealTrain($st,80,3);//高级店
                break;
            }
            $st = Db::name('users_tree')->alias('a')->join('__STORE__ b ','b.s_uid= a.t_p_uid')->where('a.t_uid',$st['t_p_uid'])->field('a.t_p_uid,b.s_grade')->find();
            if(empty($st)){
                $flag = false;
            }
        }
    }

    /**
     *
     */
    public function insertBonus($uid,$num,$type)
    {
        $bonus_data = [
            'user_id'=>$uid,
            'price'=>$num,//高级店培训费50
            'type'=>$type,
            'add_time'=>time()
        ];
        Db::name('bonus')->insert($bonus_data);
    }

    /**
     * 月结
     */
    public function monthTask()
    {
        $res = $this->groupSale();//社群销售
        $res = $this->serviceFee();//社群服务费
        $res = $this->marketExp();//市场拓展
        
    }

    /**
     *社群服务费
     */
    public function serviceFee()
    {
        //社群服务费
        $now = time();
        $start = date('Y-m-25 23:59:59',strtotime("-1 month"));
        $start = strtotime($start);
        $s_uid = Db::name('store')->where(['s_grade'=>3])->field('s_uid')->select();
        if(empty($s_uid)) return false;
        $all = [];
        foreach ($s_uid as $v){
            $childs = Db::name('users_tree')->alias('a')->join('__USERS__ b' ,'a.t_uid=b.user_id')->where(['a.t_p_uid|a.t_g_uid'=>$v['s_uid']])->field('b.user_id,b.is_seller')->select();
            //
            if(empty($childs)) continue;
            $childs = array_unique($childs);
            $where_temp = [
            	's_grade'=>3,
            	's_uid'=>['in',$childs]
            ];
            $del_s_uid = Db::name('store')->where($where_temp)->column('s_uid');
            if($del_s_uid){
            	//包含旗舰店
            	$conclude_t_uids = Db::name('users_tree')->where(['t_p_uid|t_g_uid'=>['in',$del_s_uid]])->column('t_uid');
            	$conclude_t_uids = array_merge($conclude_t_uids,$del_s_uid);
            	$childs = array_diff($childs,$conclude_t_uids);
            }
            $all[$v['s_uid']] = $childs;
        }

        if(empty($all)) return false;
        $seller_ids = [];
        foreach ($all as $k=>$a){
            foreach ($a as $one){
            	$seller_ids[$k][] = $k;
                if($one['is_seller']==1){
                    $seller_ids[$k][] = $one['user_id'];
                }
            }
        }
        $o_where = [
            'is_settle' => ['neq', 2],
            'commi_add_time' => [['>',$start],['<=',$now]]
        ];
        $moneyData = [];
        //查询旗舰店下所有的社群店主佣金利润
        foreach ($all as $key => $val) {

            $user_ids = array_column($val,'user_id');
            //vip
            $where_vip = [
                'commi_uid'=>['in',$user_ids],
                'uid_role'=>1
            ];
            $where_vip = array_merge($o_where,$where_vip);

            $commi_price_vip = Db::name('commission')->where($where_vip)->sum('commi_p_price');
            $commi_price_vip = $commi_price_vip?sprintf('%0.2f',$commi_price_vip):0;
            //店主
            if(isset($seller_ids[$key])){
                $where_seller = [
                    'commi_uid'=>['in',$seller_ids[$key]],
                    'uid_role'=>['>',1]
                ];
                $where_seller = array_merge($o_where,$where_seller);
                $commi_price_seller = Db::name('commission')->where($where_seller)->sum('commi_price');
                $commi_price_seller = $commi_price_seller?sprintf('%0.2f',$commi_price_seller):0;
                $commi_price_vip += $commi_price_seller;
            }

            if(empty($commi_price_vip)) continue;
            $moneyData[$key] = $commi_price_vip;

        }
        if(empty($moneyData)) return false;
        
        //计算旗舰店下所有利润额
        foreach ($moneyData as $k => $v) {
            $this->insertBonus($k,sprintf('%0.2f', $v*15/100),4);
        }
        return true;
    }
    /**
     * 月度核算社群销售
     */
    public function groupSale()
    {
        //社群销售
        $now = time();
        $start = date('Y-m-25 23:59:59',strtotime("-1 month"));
        $start = strtotime($start);
        $s_uid = Db::name('store')->where(['s_grade'=>['>',1]])->field('s_uid')->select();
        if(empty($s_uid)) return false;
        $all = [];
        foreach ($s_uid as $v){
            $temp_s_uid = Db::name('users_tree')->where(['t_p_uid|t_g_uid'=>$v['s_uid']])->column('t_uid');
            if(empty($temp_s_uid)) continue;
            $temp_s_uid = array_unique($temp_s_uid);
            $where_temp = [
            	's_grade'=>['>',1],
            	's_uid'=>['in',$temp_s_uid]
            ];
            $del_s_uid = Db::name('store')->where($where_temp)->column('s_uid');
            if($del_s_uid){
            	//包含高级店或者旗舰店
            	$conclude_t_uids = Db::name('users_tree')->where(['t_p_uid|t_g_uid'=>['in',$del_s_uid]])->column('t_uid');
            	$conclude_t_uids = array_merge($conclude_t_uids,$del_s_uid);
            	$temp_s_uid = array_diff($temp_s_uid,$conclude_t_uids);
            }
            $all[$v['s_uid']] = $temp_s_uid;
        }
        if(empty($all)) return false;
        $o_where = [
            'is_settle' => ['neq', 2],
            'commi_add_time' => [['>',$start],['<=',$now]]
        ];
        $orderdata = [];
        //查找社群中所有人
        foreach ($all as $key => $val) {
            $gj_child_uids = $val;
            $gj_child_uids[] = $key;
            $o_where = array_merge($o_where,['commi_uid'=>['in',$gj_child_uids]]);
            $commi_order_prices = Db::name('commission')->where($o_where)->sum('commi_order_price');
            if(empty($commi_order_prices)) continue;
            $orderdata[$key] = sprintf('%0.2f',$commi_order_prices);
        }
        if(empty($orderdata)) return false;
        //判断社群销售额
        foreach ($orderdata as $k => $v) {
            if($v >= 200000){
                $price = 5500;
            }elseif($v >= 100000){
                $price = 2800;
            }elseif($v >= 50000){
                $price = 1200;
            }elseif($v >= 30000){
                $price = 650;
            }elseif($v >= 10000){
                $price = 220;
            }elseif($v >= 5000){
                $price = 100;
            }else{
                $price = 0;
            }

            if($price>0){
                $this->insertBonus($k,$price,3);
            }
        }
        return true;
    }
    /**
     *市场拓展奖，月度
     */
    public function marketExp()
    {
        $uids = Db::name('store')->where('s_grade',3)->column('s_uid');
        if(empty($uids)) return false;
        //查找旗舰店下面有多少个直属旗舰店
        $arr = [];
        foreach ($uids as $v){
            $where_qj = [
                'a.t_p_uid'=>$v,
                'b.s_grade'=>3
            ];
            $qj_sum = Db::name('users_tree')->alias('a')->join('__STORE__ b ','b.s_uid= a.t_uid')->where($where_qj)->column('a.t_uid');
            if(empty($qj_sum)) continue;
            if(count($qj_sum)>=5){
                $arr[$v] = $qj_sum;
            }
        }
       /* echo "<pre>";
        var_dump($arr);die;*/
        //根据销售额发钱
        if(!empty($arr)){
            $now = time();
            $start = date('Y-m-25 23:59:59',strtotime("-1 month"));
            $start = strtotime($start);
            // print_r($arr);die;
            foreach ($arr as $k=>$val){
                foreach ($val as $a) {
                    $allchild = $this->getAllChild($a);
                    if (empty($allchild)) continue;
                    $allchild[] = $a;


                    /*$o_where = [
                        'order_uid' => ['in', $allchild],
                        'after_status' => ['in', [0,2]],
                    ];
                    $order_ids = Db::name('order')->where($o_where)->where('order_finish_time', ['>=', $start], ['<=', $now], 'and')->column('order_id');

                    if (empty($order_ids)) continue;
                    $og_where = [
                        'b.is_self' => 1,
                        'b.is_gift' => 0,
                        'a.og_order_id' => ['in', $order_ids]
                    ];

                    //统计这个旗舰店这个月的销售额
                    $order_commi_prices = Db::name('order_goods')->alias('a')
                        ->join('__GOODS__ b', 'a.og_goods_id=b.goods_id', 'LEFT')
                        ->where($og_where)
                        ->sum('a.order_commi_price');*/
                    $s_where = [
                    	's_uid' => ['in',$allchild],
                        'sg_addtime'=>[['>',$start],['<=',$now]],
                    	'status' => 0
                    ];
                    $order_commi_prices = Db::name('sg_sale')->where($s_where)->sum('price');
                    if(empty($order_commi_prices)) continue;
                    $order_commi_prices = sprintf('%0.2f', $order_commi_prices);

                    if($order_commi_prices>=2 * 10000 * 10000){
                    	//2亿以上
                    	$this->insertBonus($a,sprintf('%0.2f', $order_commi_prices/10) , 5);
                    }elseif($order_commi_prices >= 5000 * 10000){
                    	//5000万-2亿 7%
                    	$this->insertBonus($a,sprintf('%0.2f', $order_commi_prices*7/100) , 5);
                        $this->insertBonus($k,sprintf('%0.2f', $order_commi_prices*3/100) , 5);
                    }elseif($order_commi_prices >= 3000 * 10000){
                    	//3000-5000万 5%
                    	$order_commi_prices = sprintf('%0.2f', $order_commi_prices*5/100);
                        $this->insertBonus($a,$order_commi_prices, 5);
                        $this->insertBonus($k,$order_commi_prices, 5);
                    }elseif($order_commi_prices >= 1000 * 10000){
                    	//1000-3000万 4%
                        $this->insertBonus($a,sprintf('%0.2f', $order_commi_prices*4/100) , 5);
                        $this->insertBonus($k,sprintf('%0.2f', $order_commi_prices*6/100) , 5);
                    }elseif($order_commi_prices >= 500 * 10000){
                    	//500-1000万  3%
                        $this->insertBonus($a,sprintf('%0.2f', $order_commi_prices*3/100) , 5);
                        $this->insertBonus($k,sprintf('%0.2f', $order_commi_prices*7/100) , 5);
                    }elseif($order_commi_prices >= 100 * 10000){
                    	//100-500万  2%
                        $this->insertBonus($a,sprintf('%0.2f', $order_commi_prices*2/100) , 5);
                        $this->insertBonus($k,sprintf('%0.2f', $order_commi_prices*8/100) , 5);
                    }else{
                    	//0-100万 //当前旗舰店赚1%
                        $this->insertBonus($a,sprintf('%0.2f', $order_commi_prices/100) , 5);
                        $this->insertBonus($k,sprintf('%0.2f', $order_commi_prices*9/100) , 5);
                    }
                    // if ($order_commi_prices > 0 && $order_commi_prices <= 100 * 10000) {
                    //     //0-100万 //当前旗舰店赚1%
                    //     $this->insertBonus($a,sprintf('%0.2f', $order_commi_prices/100) , 5);
                    //     $this->insertBonus($k,sprintf('%0.2f', $order_commi_prices*9/100) , 5);
                    // } elseif ($order_commi_prices > 100 * 10000 && $order_commi_prices <= 500 * 10000) {
                    //     //100-500万  2%
                    //     $this->insertBonus($a,sprintf('%0.2f', $order_commi_prices*2/100) , 5);
                    //     $this->insertBonus($k,sprintf('%0.2f', $order_commi_prices*8/100) , 5);
                    // } elseif ($order_commi_prices > 500 * 10000 && $order_commi_prices <= 1000 * 10000) {
                    //     //500-1000万  3%
                    //     $this->insertBonus($a,sprintf('%0.2f', $order_commi_prices*3/100) , 5);
                    //     $this->insertBonus($k,sprintf('%0.2f', $order_commi_prices*7/100) , 5);
                    // } elseif ($order_commi_prices > 1000 * 10000 && $order_commi_prices <= 3000 * 10000) {
                    //     //1000-3000万 4%
                    //     $this->insertBonus($a,sprintf('%0.2f', $order_commi_prices*4/100) , 5);
                    //     $this->insertBonus($k,sprintf('%0.2f', $order_commi_prices*6/100) , 5);
                    // } elseif ($order_commi_prices > 3000 * 10000 && $order_commi_prices <= 5000 * 10000) {
                    //     //3000-5000万 5%
                    //     $order_commi_prices = sprintf('%0.2f', $order_commi_prices*5/100);
                    //     $this->insertBonus($a,$order_commi_prices, 5);
                    //     $this->insertBonus($k,$order_commi_prices, 5);
                    // } elseif ($order_commi_prices > 5000 * 10000 && $order_commi_prices <= 2 * 10000 * 10000) {
                    //     //5000万-2亿 7%
                    //     $this->insertBonus($a,sprintf('%0.2f', $order_commi_prices*7/100) , 5);
                    //     $this->insertBonus($k,sprintf('%0.2f', $order_commi_prices*3/100) , 5);
                    // } elseif ($order_commi_prices > 2 * 10000 * 10000) {
                    //     //2亿以上
                    //     $this->insertBonus($a,sprintf('%0.2f', $order_commi_prices/10) , 5);
                    // }
                }
            }
        }
    }
    /**
     * 查找我的所有下级
     */
    public function getAllChild($uid)
    {
        $my_child = Db::name('users_tree')->where(['t_p_uid' => $uid])->field('t_uid')->select();
        $uids = [];
        if($my_child){
            foreach ($my_child as $v){
                $uids[] = $v['t_uid'];
                $uid = $this->getAllChild($v['t_uid']);
                if(!empty($uid)){
                   $uids = array_merge($uids,$uid);
                }
            }
            $uids = array_unique($uids);
        }
        return $uids;
    }
}