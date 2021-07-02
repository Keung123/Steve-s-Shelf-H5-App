<?php
namespace app\common\service;

use app\common\model\UserMessage as UserMessageModel;
use think\Db;

class UserMessage extends Base{

	public function __construct(){
		parent::__construct();
		$MessageModel=new UserMessageModel();
		$this->model=$MessageModel;
	}
	/**
     * 查出聊天列表
     */
	public function getUserList($uid, $p, $limit)
    {
        $fir = ($p-1)*$limit;
        $list = $this->model->where(function ($query) use ($uid) {

            $query->where('msg_from_uid', $uid)->whereor('msg_to_uid', $uid)->where('msg_type', 2)->where('msg_reply_id', 0);

        })->order('msg_addtime desc')->limit($fir, $limit)->select();
        return $list;
    }
    /**
     * 查询聊天记录
     */
    public function getMessageList($uid1, $uid2, $p, $limit)
    {
        $data = [$uid1,$uid2];
        $fir = ($p-1)*$limit;
        $list = $this->model->where(function ($query) use ($data) {

            $query->where('msg_to_uid|msg_from_uid', $data[0])
                ->where('msg_type', 2)->where('msg_from_uid|msg_to_uid', $data[1]);

        })->order('msg_addtime desc')->limit($fir, $limit)->select();
        if ($list) {
            // 设置 所有聊天内容为已读
            $id_arr = [];
            foreach ($list as $val) {
                $id_arr[] = $val['msg_id'];
            }
            $where['msg_id'] =array('in',implode(',', $id_arr));

            $this->model->where($where)->update(['msg_stat' => 2]);
        }
        return $list;
    }
	/**
     * 查出 最后聊天内容
     */
	public function getUserEnd($uid1, $uid2)
    {
        $data = [$uid1,$uid2];
        $info = $this->model->where(function ($query) use ($data) {

            $query->where('msg_to_uid|msg_from_uid', $data[0])
                ->where('msg_type', 2)->where('msg_from_uid|msg_to_uid', $data[1]);

        })->order('msg_addtime desc')->find();
        return $info;
    }
    /**
     * 根据用户id 查名称
     */
    public function getUserName($uid)
    {
        return Db::name('users')->where('user_id', 'eq', $uid)->value('user_name');
    }
}