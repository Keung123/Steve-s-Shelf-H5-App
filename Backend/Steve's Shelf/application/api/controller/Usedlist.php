<?php


namespace app\api\controller;

use app\common\model\Usedlist as UsedModel;

class Usedlist extends Common
{
    protected $used;

    public function __construct(UsedModel $used)
    {
        $this->used = $used;
    }

    /**
     * 获取常用清单
     * @param int $uid 用户id
     * @param string $token
     */
    public function getUsedList()
    {
        $uid = input('request.uid');
        $token = input('request.token');
        if($uid){
            $id = $this->getUid($token, $uid);
            if(!$id){
                return $this->json('', 0, '未知参数');
            }
        } else {
            return $this->json('', 0, '获取失败');
        }
        $res = $this->used->maxGoodsDetails($uid);
        if ($res) {
            return $this->json($res);
        } else {
            return $this->json('', 0, '获取失败');
        }
    }

    /**
     * 删除操作
     * @param string $ids
     * @param int $uid
     */
    public function delete()
    {
        $ids = input("request.ids");
        $uid = input("request.uid");
        $res = $this->used->add($uid,$ids);
        if ($res) {
            return $this->json('',1,'删除成功');
        } else {
            return $this->json('', 0, '删除失败');
        }
    }
}