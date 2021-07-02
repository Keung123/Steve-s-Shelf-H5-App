<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/3/28 10:24
 */
namespace app\admin\controller\integral;

use app\common\service\Goods as GoodsService;
use app\admin\controller\Base;

class Integral extends Base
{
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
}