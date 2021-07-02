<?php
namespace app\api\controller;
header('Content-type:text/html;charset=utf8');
use app\common\service\User as UserService;
use app\common\service\UserMessage as UserMessage;
use think\Db;
class Message extends Common{
	private $uid;
	public function __construct(){
		parent::__construct();
		$this->user = new UserService();
		$user_id = input('request.uid');
		$token = input('request.token');
		if($user_id && $token){
			$uid = $this->getUid($token, $user_id);
			if(!$uid){
				echo json_encode(['data' => [],'status' => 0,'msg' => '未知参数'], JSON_UNESCAPED_UNICODE);
				exit;
			}
			$this->uid = $uid;
		}
		else{
			echo json_encode(['data' => [],'status' => 0,'msg' => '未知参数'], JSON_UNESCAPED_UNICODE);
			exit;
		}
	}

	/*
	 * 消息模块首页
	 */
	public function index(){
		$uid = $this->uid;
		$p = input('request.p', 1);
		$limit = 20;
		$message_model = new UserMessage();
		$list = $message_model->getUserList($uid, $p, $limit);
		if ($list) {
		    foreach ($list as $key =>$val) {
		        $list[$key]= $message_model->getUserEnd($val['msg_from_uid'], $val['msg_to_uid']);
            }
        }
		return $this->json($list);
	}
    /**
     * 查看历史消息
     */
    public function getHistory()
    {
        $uid = $this->uid;
        $to_uid = input("to_uid");
        if (!$to_uid) {
            return $this->json([], 0, '没有找到该接受人');
        }
        $p = input('request.p', 1);
        $limit = 20;
        $message_model = new UserMessage();
        $list = $message_model->getMessageList($uid, $to_uid, $p, $limit);
        return $this->json($list);
    }
	/**
     * 发送消息
     */
	public function sendMessage()
    {
        $uid = $this->uid;
        $to_uid = input("to_uid");
        if (!$to_uid) {
            return $this->json([], 0, '没有找到该接受人');
        }
        $message = input('message', '');
        if (empty($message)) {
            return $this->json([], 0, '消息内容不能为空');
        }
        $msg_reply_id = input('reply_id', 0);
        $message_model = new UserMessage();
        $data = [
            'msg_reply_id' => $msg_reply_id,
            'msg_from_uid' => $uid,
            'msg_from_uname' => $message_model->getUserName($uid),
            'msg_to_uid' => $to_uid,
            'msg_to_uname' => $message_model->getUserName($to_uid),
            'msg_content' => $message,
            'msg_type' => 2,
            'msg_addtime' => time()
        ];
        $res = $message_model->add($data);
        if ($res) {
            return $this->json([], 1, '发送成功');
        } else {
            return $this->json([], 0, '发送失败');
        }
    }

    /*
     * 消息页面（我的上级）
     */
    public function asService(){
        $uid = $this->uid;
        $tree_info = Db::name('users_tree')->alias('a')->join('__USERS__ b', 'a.t_p_uid=b.user_id')->where('a.t_uid', $uid)->field('b.user_id,b.user_name,b.user_avat,b.user_mobile')->find();
        if(!$tree_info){
            return $this->json('', 0, '未找到售后服务人');
        }
        return $this->json($tree_info);
    }

}