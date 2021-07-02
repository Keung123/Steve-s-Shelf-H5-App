<?php
namespace app\common\service;
use app\common\service\Config;
use think\Db;

class Base{

	public function __construct(){
		
	}

	public $with="";

	/*
	* 添加数据
	*/
	public function add($data){
		$res=$this->model->save($data);
		if($res){
			return SUCCESS;
		}else{
			return ADD_FAIL;
		}
	}

	public function addGetId($data){
		$res=$this->model->insertGetId($data);
		if($res){
			return $res;
		}else{
			return ADD_FAIL;
		}
	}	


	/*
	* 分页查询数据
	*/
	public function paginate($map=[],$field="*",$order="",$pageSize=10,$join=""){
		$res=$this->model->where($map)->with($this->with)->join($join)->field($field)->order($order)->paginate($pageSize);
		return $res;
	}

	/*
	* 查询列表数据
	*/
	public function select($map = [], $field = "*", $order = "", $limit = "", $join = ""){
		$res = $this->model->where($map)->with($this->with)->join($join)->field($field)->order($order)->limit($limit)->select();
		return $res;
	}

	/*
	* 查询总数
	*/
	public function count($map=[]){
		$res=$this->model->where($map)->count();
		return $res;
	}

	/*
	* 求和
	*/
	public function sum($map=[],$field){
		$res=$this->model->where($map)->sum($field);
		return $res?$res:0;
	}	

	/*
	* 查询单条数据
	*/
	public function find($map, $field = '*'){
		$res=$this->model->where($map)->field($field)->find();
		return $res;
	}

	/*
	* 查询某列数据
	*/
	public function value($map,$value){
		return $this->model->where($map)->value($value);
	}

	/*
	* 更新数据
	*/
	public function save($map=[],$data){
		$res=$this->model->save($data,$map);
		if($res !== false){
			return SUCCESS;
		}else{
			return UPDATA_FAIL;
		}		
	}

	/*
	* 删除数据
	*/
	public function delete($map){
		$res=$this->model->where($map)->delete();
		if($res){
			return SUCCESS;
		}else{
			return DELETE_FAIL;
		}		
	}
	// 获取活动类型名称
    public function ActiveInfo($data)
    {		

		foreach($data as $key=>$val){
			 $getActiveInfo = Db::name('active_type')->field('id,active_type_name')->where(array('id' => $val['prom_type']))->find();
			 if($getActiveInfo){
				$data[$key]['active_type_name'] =   $getActiveInfo['active_type_name'];
				$data[$key]['active_id'] =   $getActiveInfo['id'];
				$active_price =   $this->ActivePrice($val['prom_type'],$val['price'],$val['prom_id']);
				if($active_price ){
					$data[$key]['active_price'] =  $active_price;
				}
				if($data[$key]['prom_type']==3){
					$team_price= Db::name('team_activity')->where(array('goods_id' => $val['goods_id']))->find();
					$data[$key]['active_price'] =  $data[$key]['price']-$team_price['price_reduce'];
				}
			 }
            $data[$key]['price'] = floatval($val['price']);
		}
        return $data;
    }
	
	 // 获取活动名称
    public function ActiveTitle($id)
    {
        $active_info = Db::name('active_type')->where(array('id' => $id))->find();
        return $active_info['active_type_name'];
    }
    // 获取活动标签
    public function ActiveBiaoqian($id)
    {
        $active_info = Db::name('active_type')->where(array('id' => $id))->find();
        return $active_info['label_title'];
    }
	
	 // 获取活动商品价格  （活动id,原价,活动商品表id）
    public function ActivePrice($active_id,$price,$prom_id)
    {
		if($active_id>=9){
			//自定义活动
			$active = Db::name('active_type')->field('active_type,active_type_val')->where('id',$active_id)->find();
			if($active){
				if($active['active_type'] == 1 ){
					$active_price = $price - $active['active_type_val'];
				}elseif($active['active_type'] == 2){
					$active_price = ($price * $active['active_type_val'])/100;	
				} else {
                    $active_price = $price;
                }
				//减价过大
				if($active_price<0){
					$active_price = 0;
				}	
			}	
		}else if($active_id ==1||$active_id ==3||$active_id ==5){

			if($active_id == 1){
				//团购
				$table = 'group_goods';
			}else if($active_id == 3){
				//拼团
				$table = 'team_activity';
			}else if($active_id == 5){
				//秒杀
				$table = 'flash_goods';
			}
			$groups = Db::name($table)->field('price_type,price_reduce')->where('id',$prom_id)->find();
			if($groups){
				if($groups['price_type'] == 0 ){
					$active_price = $price - $groups['price_reduce'];
				}else{	
					$active_price = ($price * $groups['price_reduce'])/100;	
				}
				//减价过大
				if($active_price<0){
					$active_price = 0;
				}	
			}	
		}else if($active_id == 2){
			//预售
			 $groups = Db::name('goods_activity')->field('deposit')->where('act_id',$prom_id)->find();
			 $active_price = $groups['deposit'];
		}else if($active_id ==6||$active_id ==7||$active_id ==8||$active_id ==4||$active_id ==0){
			//6:满199减100;7:满99元3件;8:满2件9折;4:砍价活动价为原价
			 $active_price = $price;
		}
		return $active_price;
	  
    }
    // 敏感词 过滤
    public function word_filter($content)
    {
        $badword = Db::name('sensitive')->field('sst_name')->where(['sst_status' => 1])->select();
        $badword = array_column($badword, 'sst_name');
        // 过滤
        $badword1 = array_combine($badword,array_fill(0,count($badword),'*'));
        $str = strtr($content, $badword1);
        return $str;
    }
	//获取配置信息
	public function getCom(){
		 
		//获取配置信息
		$ConfigService=new Config();
		$config=$ConfigService->find();
		$commission = json_decode($config['commission'],true);
		return $commission;
	}
	//获取配置信息
	public function getPost(){
		 
		//获取配置信息
		$ConfigService=new Config();
		$config=$ConfigService->find();
		$express = json_decode($config['express'],true);
		return $express;
	}
	
}







