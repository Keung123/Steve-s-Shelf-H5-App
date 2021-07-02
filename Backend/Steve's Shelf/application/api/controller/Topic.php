<?php
namespace app\api\controller;

use app\common\service\Topic as TopicService;
use app\common\service\User as UserService;

class Topic extends Common {
    protected $topic;
    protected $user;
    public function __construct()
    {
        $this->topic = new TopicService();
        $this->user = new UserService();
    }

    /**
	* 话题列表
     * @param int page 分页,默认值1
     * @return json
	*/
	public function index()
    {
		$p = input('request.page', 1);
        $list = $this->topic->topicList($p);
		if(!$list){
    		return $this->json('', 0, '获取失败');
    	}
    	return $this->json($list);
	}
	
	/**
	 * 话题参与详情
     * @param int topicId 话题id
     * @param int page 分页id
     * @param int type  1明星专访 ; 2 话题
     * @param int uid
     * @param string token
     * @param int hottest 1 最热 2最新
     * @return json
	 */
	public function topicInfo()
    {
		$uid = input('request.uid');
		$token = input('request.token');
		$tp_id = input('request.topicId');
		$p = input('request.page', 1);
		$type = input('request.type');
		$hottest = input('request.hottest');
		$uid = $this->getUid($token, $uid);
		if(!$uid){
			return $this->json('', 0, '未知参数');
		}
        $list = $this->topic->topicInfo($uid,$tp_id,$p,$type,$hottest);
		if(!$list){
    		return $this->json('', 0, '获取失败');
    	}
    	return $this->json($list);
	}

    /**
     * 素材分类
     * @param int type 1素材分类 2 话题分类
     * @return json
     */
    public function mateCat()
    {
        $type = input('request.type');
        $list = $this->user->mateCat($type);
        if($list){
            return $this->json($list);
        }
        else return $this->json('', 0, '暂无数据');
    }

    /**
     * 点赞
     * @param int uid
     * @param int type 1明星专访 2 话题
     * @param int topicId
     * @param string token
     * @return json
     */
    public function giveLike()
    {
        $user_id = input('request.uid');
        $type = input('request.type');
        $topicId = input('request.topicId');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $res  = $this->user->giveLike($uid,$topicId,$type);
        if($res==-1){
            return $this->json('', -1, '已经点赞了！');
        }else if($res==1){
            return  $this->json('', 1, '点赞成功');
        }else{
            return  $this->json('', 0, '点赞失败');
        }

    }
    /**
     * 发现
     * @param int page 分页参数
     * @param int type 0素材1明星专访2话题3推荐
     * @param int cat_id
     * @param string token
     * @return json
     */
    public function mateZone()
    {
        $user_id = input('request.uid', 0);
        if($user_id){
            $token = input('request.token');
            $cat_id = input('request.cat_id');
            $type = input('request.type');
            $user_id = $this->getUid($token, $user_id);
            if(!$user_id){
                return $this->json('', 0, '未知参数');
            }
        }
        $p = input('request.page');
        $p = $p ? $p : 1;
        $mate_info = $this->user->userMaterial($user_id, $p,$cat_id,$type);
        return $this->json($mate_info);
    }
}