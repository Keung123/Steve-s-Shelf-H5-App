<?php
namespace app\admin\controller\menu;

use app\common\service\Menu as MenuService;
use app\common\service\GoodsCategory;
use app\admin\controller\Base;
use think\Db;

class Menu extends Base{
    protected $MenuService;
    protected $category;
    public function _initialize()
    {
        parent::_initialize();
        $this->MenuService = new MenuService();
        $this->category = new GoodsCategory();
    }
     /**
      *  显示页面
      */
     public function index()
     {
         if(request()->isAjax()){
             //排序
             $order=input('get.sort')." ".input('get.order');

             if(input('get.search')){
                 $map['name']=['like','%'.input('get.search').'%'];
             }
             $total = $this->MenuService->count($map);
             $rows = $this->MenuService->getList($map,'*',$order);
             return json(['total'=>$total,'rows'=>$rows]);
         }else{
             return $this->fetch();
         }
     }
     /**
      * 添加
      */
     public function add()
     {
         if(request()->isAjax()){
             $row=input('post.row/a');
             $data['icon'] = $row['icon'];
             $data['category_id'] = $row['category_id'];
             $data['sort'] = $row['sort'];
             $data['name'] = $this->category->getName($row['category_id']);
             if (!$data['name']) {
                 $this->error('请选择菜单');
             }
             $res=$this->MenuService->add($data);
             return AjaxReturn($res>0?1:0,getErrorInfo($res));
         } else {
             //获取分类列表
             $rows = $this->category->choice();
             //转为树形
             $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
             $this->assign('category',$rows);
             return $this->fetch();
         }
     }

     /**
      * 删除操作
      */
     public function delete()
     {
         $ids=input('get.ids');
         $map['id']=['in',$ids];
         $res=$this->MenuService->delete($map);
         //添加日志记录
         $this->write_log('菜单删除',$ids);
         return AjaxReturn($res);
     }

     /**
      * 修改操作
      */
     public function edit()
     {
         if(request()->isAjax()){
             $row=input('post.row/a');
             $map['icon'] = $row['icon'];
             $map['category_id'] = $row['category_id'];
             $map['name'] = (new GoodsCategory())->getName($row['category_id']);
             $map['sort'] = $row['sort'];
             $menuId['id'] = $row['id'];
             $res = $this->MenuService->save($menuId,$map);
             return AjaxReturn($res,getErrorInfo($res));
         }else{
             $map['id']=input('get.ids');
             $row=$this->MenuService->find($map);
             //获取分类列表
             $rows = $this->category->choice();
             //转为树形
             $rows=\util\Tree::makeTreeForHtml(collection($rows)->toArray(), ['primary_key' => 'category_id','parent_key'=>'pid']);
             $this->assign('category',$rows);
             $this->assign('row',$row);
             return $this->fetch();
         }
     }
}