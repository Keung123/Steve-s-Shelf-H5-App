<?php
namespace app\common\service;

use app\common\model\GoodsCategory as GoodsCategoryModel;

use  think\Db;

class GoodsCategory extends Base{
	// public $model;
	public function __construct(){
		parent::__construct();
		$GoodsCategoryModel = new GoodsCategoryModel();
		$this->model = $GoodsCategoryModel;
	}

	/*
	 * 获取分类
	 */
	public function getCate($where, $field = '*', $order = 'weigh desc'){

		return parent::select($where, $field, $order);
	}

	/*
	 * 全部分类
	 */
	public function allCate(){
		$list = $this->model->where('status', 'normal')->field('category_id,category_name,pid,image')->order('weigh asc,pid asc')->select();
		$arr = [];
		foreach($list as $k => &$v){
			if($v['pid'] == 0){
				$arr[$v['category_id']]['cate_id'] = $v['category_id'];
				$arr[$v['category_id']]['cate_name'] = $v['category_name'];
			}
			else{				
				$arr[$v['pid']]['list'][] = $v;
				unset($v['pid']);
			}
		}
		$tmp = [];
		$i = 0;
		foreach($arr as $val){
			//若上级分类不存在
			if(!$val['cate_id']){
				continue;
			}
			$tmp[$i] = $val;
			$i++;
		}
		return $tmp;
	}

	/*
     * 判断分类是否有子级
     */
    public function jdugeCategory($ids)
    {
		$idarr = explode(',',$ids);
		if(count($idarr)>0){
			foreach($idarr as $val){
				$res = Db::name('goods_category')->where(['pid' =>$val])->find();
				if(!$res){
					return 0;
				}
				return $res;
			}
		}
		return 1;
    }

    /**
     * 获取分类名称
     */
    public function getName($id)
    {
        $data = Db::name('goods_category')->field('category_name')->where('category_id = '.$id)->find();
        return $data['category_name'];
    }

    /**
     * 过滤分类
     */
    public function choice()
    {
        //获取九宫格已选择的分类
        $menus_ids = Db::name('menu')->field('category_id')->select();
        $menus_ids = array_column($menus_ids,'category_id');

        $data = Db::name('goods_category')->field('*')->where('pid = 0')->select();
        foreach ($data as $key => $val) {
            foreach ($menus_ids as $v) {
                //if ($val['category_id'] == $v) {
                    //unset($data[$key]);
                //}
            }
        }
        $data = array_filter($data);
        return $data;
    }
}