<?php

namespace app\api\controller;

use app\common\service\User as UserService;
use think\Db;

class Material extends Common{
    protected $userService;
    public function __construct()
    {
        $this->userService = new UserService();
    }

    /**
     * description:我的素材
     * @param int uid
     * @param int page
     * @param string token
     * @return json
     */
    public function myMaterial(){
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $p = input('request.page');
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $mate_info = $this->userService->userMate($uid, $p);
        if($mate_info){
            return $this->json($mate_info);
        }
        return $this->json('', 0, '暂无数据');
        return $this->json($mate_info);
    }

    /**
     * description:素材编辑
     * @param integer uid
     * @param string token
     * @param integer goodsId
     * @param string content
     * @param thumb
     * @param mateVideo
     * @param integer cateId
     * @param integer m_id
     * @param integer type
     * @return json
     */
    public function materialEdit()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $data = [
            'm_goods_id' => input('request.goodsId',0),
            'mate_content' => input('request.content'),
            'mate_add_time' => time(),
            'mate_thumb' => input('request.thumb'),
            'mate_video' => input('request.mateVideo'),
            'm_cat_id' => input('request.cateId'),
            'm_id' => input('request.m_id') ? input('request.m_id') : 0,
            'm_type' => input('request.type') ? input('request.type') : 0
        ];
        if(!$uid || !$data){
            return $this->json('', 0, '未知参数');
        }
        $res = $this->userService->mateEdit($uid, $data);
        if($res){
            //话题参与量增加
            if($data['type'] == 2){
                Db::name('topic')->where('tp_id',$data['m_cat_id'])->setInc('tp_partake_num');
            }
            return $this->json('', 1, '保存成功');
        }
        else if($res  == -1){
            return $this->json('', 0, '未提交新内容');
        }
        return $this->json('', 0, '保存失败');
    }

    /**
	 * description:素材详情
     * @param int uid
     * @param string token
     * @param int mateId
     * @return json
	 */
    public function materialDetails()
    {
        $user_id = input('request.uid');
        $type = input('type', '');
        if($user_id){
            $token = input('request.token');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }
        $mate_id = input('request.mateId');
        $mate_info = $this->userService->mateInfo($user_id, $mate_id, $type);
        return $this->json($mate_info);
    }

    /**
     * description:素材删除
     * @param int uid
     * @param int token
     * @param int mateId
     * @return json
     */
    public function materialDel(){
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $mate_id = input('request.mateId');
        if(!$uid || !$mate_id){
            return $this->json('', 0, '未知参数');
        }
        $res = $this->userService->mateDel($uid, $mate_id);
        return $this->json($res);
        if($res){
            return $this->json('', 1, '删除成功');
        }
        else return $this->json('', 0, '删除失败');
    }

    /**
     * description:素材-搜索商品
     * @param int uid
     * @param string token
     * @param string goodsName
     * @return json
     */
    public function materialSearch()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $goods_name = input('request.goodsName');
        $goods_info = $this->userService->mateSearch($uid, $goods_name);
        if($goods_info){
            return $this->json($goods_info);
        }else{
            return $this->json('', 0, '暂无数据');
        }

    }
}
