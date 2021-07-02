<?php
/**
 * Created by PhpStorm.
 * User: benbenkeji
 * Date: 2018/10/22
 * Time: 8:33
 */

namespace app\common\service;
use think\Db;

class Topic extends Base{

    public function __construct(){
        $SensitiveModel= new \app\common\model\Topic();
        $this->model=$SensitiveModel;
    }
	/*
	* 话题列表
	*/
	public function topicList($p){
		$num = 10;
		$p = $p ? $p : 1;
		$s = ($p - 1) * $num;
		$map = [
			'tp_status'=>0,
		];
		$list = $this->model->where($map)->order('tp_addtime desc')->limit($s, $num)->select();
		if($list){
			foreach($list as $key=>$val){
				$uid_arr = Db::name('users_material')->where('m_cat_id',$val['tp_type'])->limit(0,3)->group('m_uid')->column('m_uid');
				if($uid_arr){
					$uid_str = implode(',',$uid_arr);
					$avat_arr = Db::name('users')->where(array('user_id'=>['in',$uid_str]))->column('user_avat');
					$list[$key]['avat_list'] = $avat_arr;
				}
				
			}
		
		}
		return $list;
	}
	/*
	* 话题详情
	*/
	public function topicInfo($user_id,$tp_id,$p,$type,$hottest){
		$num = 10;
		$p = $p ? $p : 1;
		$s = ($p - 1) * $num;
		$row = Db::name('topic')->where('tp_id',$tp_id)->field('tp_id,tp_title,tp_banner,tp_partake_num,tp_img,tp_video,tp_content,tp_type,tp_store_id,tp_goods_id,tp_like,tp_addtime,tp_user_id')->find();
		if($row){
			if($type == 2){
				if($hottest == 1){
					$order = 'a.m_like desc';
				}else{
					$order = 'a.mate_add_time desc';
				}
				$material =  Db::name('users_material')
				->alias('a')
				->join('users b','a.m_uid=b.user_id')
				->where(array('a.m_cat_id'=>$tp_id,'a.m_type'=>2))
				->field('a.m_id,a.mate_content,a.mate_thumb,a.mate_video,a.m_goods_id,b.user_name,b.user_avat,a.m_like,a.mate_add_time')
				->limit($s, $num)
				->order($order)
				->select();
				$material1 =  Db::name('users_material')
				->alias('a')
				->join('users b','a.m_uid=b.user_id')
				->where(array('a.m_cat_id'=>$tp_id,'a.m_type'=>2))
				->Distinct(true)->field('m_uid')
				->select();
				$num=count($material1);
				$row['tp_partake_num']=$num;
                $total =  Db::name('users_material')
                    ->alias('a')
                    ->join('users b','a.m_uid=b.user_id')
                    ->where(array('a.m_cat_id'=>$tp_id,'a.m_type'=>2))
                    ->field('a.m_id,a.mate_content,a.mate_thumb,a.mate_video,a.m_goods_id,b.user_name,b.user_avat,a.m_like,a.mate_add_time')
                    ->count();
                if ($material) {
                    $goodsService = new Goods();
                    foreach ($material as &$val) {
                        if ($val['m_goods_id']) {
                            $goods = Db::name('goods')->where('goods_id',$val['m_goods_id'])->field('goods_name,price,picture,commission,vip_price,prom_type,prom_id,show_price')->find();
                            if($goods){
                                $commission = $this->getCom();
                                //开启 返利
                                if($commission['shop_ctrl'] == 1){
                                    $f_p_rate = $commission['f_s_rate'];
                                }else{
                                    $f_p_rate = 100;
                                }
                                $active_price = $goodsService->getActivePirce($goods['price'],$goods['prom_type'],$goods['prom_id']);
                                $goods['dianzhu_price'] = floor($active_price * $goods['commission']/ 100 * $f_p_rate)/100;
                                $goods['dianzhu_price'] = floor($active_price * $goods['commission']/ 100 * $f_p_rate)/100;
                                $goods['dianzhu_price'] = sprintf('%0.2f', $goods['dianzhu_price']);
                                $goods['dianzhu_price'] = floatval($goods['dianzhu_price']);
                                $goods['vip_price'] = sprintf('%0.2f', $goods['vip_price']);
                                $goods['vip_price'] = floatval($goods['vip_price']);
                                $goods['show_price'] = sprintf('%0.2f', $goods['show_price']);
                                $goods['show_price'] = floatval($goods['show_price']);
                                $goods['price'] = sprintf('%0.2f', $goods['price']);
                                $goods['price'] = floatval($goods['price']);
                                $val['goods_info'] =$goods;
                            }
                        }
                    }
                }

				$material = $this->getInfo($user_id,$material,$type);
				$row['material'] = $material;
				$row['total'] = $total;
			}else{
				$useInfo = Db::name('users')->where('user_id',$row['tp_user_id'])->field('user_name,user_avat')->find();
				if($useInfo){
					$row['useInfo'] = $useInfo;
				}
				$goodsInfo = Db::name('goods')->where('goods_id',$row['tp_goods_id'])->field('goods_name,picture,stock,price,vip_price')->find();
				if($useInfo){
					$row['goodsInfo'] = $goodsInfo;
				}
				if($user_id){
					$row['favorite'] = 0;
					$row['like'] = 0;
					$reslut = Db::name('favorite')->where(['favor_type' => 3, 'f_uid' => $user_id, 'f_goods_id' => $row['tp_id']])->find();
					if($reslut){
						$row['favorite'] = 1;
					}
					//是否点赞
					$reslut = Db::name('like')->where(['l_uid' => $user_id, 'l_topic_id' => $row['tp_id'],'l_type'=>1])->find();
					if($reslut){
						$row['like'] = 1;
					}
				}	
			}
			$row['tp_addtime'] = date('m月d日',$row['tp_addtime']);
		}
		return $row;
	}
	
	/*
	 * 点赞收藏
	*/
	public function getInfo($user_id,$list,$type){
		foreach($list as &$v){
			$v['mate_add_time'] = date('Y-m-d H:i',$v['mate_add_time']);
			$v['favorite'] = 0;
			$v['like'] = 0;
			//是否收藏
			if($user_id){
				$res = Db::name('favorite')->where(['favor_type' => 3, 'f_uid' => $user_id, 'f_goods_id' => $v['m_id']])->find();
				if($res){
					$v['favorite'] = 1;
				}
				//是否点赞
				$reslut = Db::name('like')->where(['l_uid' => $user_id, 'l_topic_id' => $v['m_id'],'l_type'=>2])->find();
				if($reslut){
					$v['like'] = 1;
				}
			}	
			if($type == 2){
				$links = Db::name('like')->where(array('l_type'=>2,'l_topic_id'=>$v['m_id']))->count();
				$v['like_num'] = $links;
			}else{
				$links = Db::name('like')->where(array('l_type'=>1,'l_topic_id'=>$v['tp_id']))->count();
				$v['like_num'] = $links;
			}
		}
		return $list;
	}
}