<?php
namespace app\api\controller;
header('Content-type:text/html;charset=utf8');
use app\common\service\User as UserService;
use app\common\service\UserMessage;
use think\Db;
class Message extends Common{
	protected $uid;
	protected $userService;
	protected $message;
	public function __construct()
    {
		parent::__construct();
		$this->userService = new UserService();
		$this->message = new UserMessage();
		$user_id = input('request.uid');
		$token = input('request.token');
		/*if($user_id && $token){
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
		}*/
	}

	/**
	 * 消息模块首页
	 */
	public function index()
    {
		$uid = $this->uid;
		$p = input('request.p', 1);
		$limit = 20;
		$list = $this->message->getUserList($uid, $p, $limit);
		if ($list) {
		    foreach ($list as $key =>$val) {
		        $list[$key]= $this->message->getUserEnd($val['msg_from_uid'], $val['msg_to_uid']);
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
        $list = $this->message->getMessageList($uid, $to_uid, $p, $limit);
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
        $data = [
            'msg_reply_id' => $msg_reply_id,
            'msg_from_uid' => $uid,
            'msg_from_uname' => $this->message->getUserName($uid),
            'msg_to_uid' => $to_uid,
            'msg_to_uname' => $this->message->getUserName($to_uid),
            'msg_content' => $message,
            'msg_type' => 2,
            'msg_addtime' => time()
        ];
        $res = $this->message->add($data);
        if ($res) {
            return $this->json([], 1, '发送成功');
        } else {
            return $this->json([], 0, '发送失败');
        }
    }

    /**
     * 消息页面（我的上级）
     * @param int uid
     * @param string token
     * @return json
     */
    public function asService()
    {
        $uid = $this->uid;
        $tree_info = Db::name('users_tree')->alias('a')->join('__USERS__ b', 'a.t_p_uid=b.user_id')->where('a.t_uid', $uid)->field('b.user_id,b.user_name,b.user_avat,b.user_mobile')->find();
        if(!$tree_info){
            return $this->json('', 0, '未找到售后服务人');
        }
        return $this->json($tree_info);
    }

    /**
     *  消息  活动消息
     */
    public function activeNews(){
        $result = $this->userService->getCenter('公告消息');
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }

    /**
     * 获取消息列表
     * @param int uid
     * @param string token
     * @param int page 当前页数
     * @return json
     */
    public function getMessage()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $page = input('request.page',1);
        $size = 10;
        $start = ($page-1)*$size;
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $where = [
            'mp_address'=>0,
            'mp_send_time'=>['<=',time()],
            'mp_type'=>1,
        ];
        $is_seller = Db::name('users')->where(['user_id'=>$uid])->value('is_seller');
        if($is_seller){
            $s_grade = Db::name('store')->where(['s_uid'=>$uid])->value('s_grade');
            if(!$s_grade) return $this->json('', 0, '未知参数');
            $where['mp_name'] = ['in',[0,5,$s_grade+1]];
        }else{
            //vip
            $where['mp_name'] = ['in',[0,1]];
        }
        $total = Db::name('message_push')->where($where)->count();
        $messagePush = Db::name('message_push')->where($where)->limit($start,$size)->order('mp_add_time desc')->field('mp_id,mp_content,mp_add_time')->select();
        if($messagePush){
            $mp_id = array_column($messagePush,'mp_id');
            $md_where = [
                'user_id'=>$uid,
                'mp_id'=>['in',$mp_id],
                'md_type'=>1
            ];
            $nread_mp_id = Db::name('message_descript')->where($md_where)->column('mp_id');
            foreach ($messagePush as &$one){
                $one['mp_add_time'] = date('Y-m-d H:i',$one['mp_add_time']);
                $one['is_read'] = 1;//已读
                if(empty($nread_mp_id)) continue;
                if(in_array ( $one['mp_id'] ,  $nread_mp_id )){
                    $one['is_read'] = 0;//未读
                }
            }
            //改为已读
            Db::name('message_descript')->where(['mp_id'=>['in',$nread_mp_id]])->update(['md_type'=>2]);
        }
        return $this->json(['message'=>$messagePush,'total'=>$total], 1, '获取成功');
    }

    /**
     * 是否有新消息
     * @param int uid
     * @param string token
     * @return Json
     */
    public function isNewMessage()
    {
        $user_id = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $user_id);
        $where = [
            'user_id'=>$uid,
            'md_type'=>1,
            'md_send_time'=>['<=',time()]
        ];
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $count = Db::name('message_descript')->where($where)->count();
        return $this->json($count, 1, '获取成功');
    }

    /**
     *  获取最新消息
     * @param string $token
     * @param int $uid
     */
    public function getNewMessage()
    {
        //获取最新的活动消息
        //$active = Db::name('content_category')->field('title,createtime')->order('createtime desc')->find();

        $active = Db::name('content')->where('category_id',4)->field('title,create_time')->order('create_time desc')->find();
        $uid = input("request.uid");
        //获取最新的客户消息
        // $service = Db::name('message_descript a')
        //          ->field('b.mp_content as title,a.md_send_time as createtime')
        //          ->join('message_push b','a.mp_id = b.mp_id')
        //          ->where('b.mp_send_status = 1')
        //          ->order('a.md_send_time desc')
        //          ->find();
        $list = Db::name("msg")->where($where)->order("id desc")->limit(100)->select();
        $service = Db::name('msg_list')
                 ->field('content as title,date as createtime')
                 ->where("uid = {$uid}")
                 ->order('date desc')
                 ->find();
        $data = array();
        //type 1活动 2客户消息
        //转换时间格式
        if ($active) {
            $active['createtime'] = date('Y-m-d H:i:s',$active['create_time']);
            $active['type'] = 1;
            $data[] = $active;
        }
        if ($service) {
            $service['createtime'] = date('Y-m-d H:i:s',$service['createtime']);
            $service['type'] = 2;
            $data[] = $service;
        }
        return $this->json($data,1,'获取成功');
    }

    /**
     * 消息详情页
     * @param int $category_id 消息id
     */
    public function getDetails()
    {
        $id = input("request.category_id");
        $data = Db::name('content')
            ->field('content_id,title,keywords,FROM_UNIXTIME(create_time,"%Y-%m-%d %H:%i:%s") as createtime,picture as img,content')
            ->where('content_id = '.$id)
            ->find(); 

        // $data = Db::name('content_category')
        //     ->field('category_id,title,keywords,description,FROM_UNIXTIME(createtime,"%Y-%m-%d %H:%i:%s") as createtime,img')
        //     ->where('category_id = '.$id)
        //     ->find();
        if ($data) {
            return $this->json($data,1,'获取成功');
        }
        return $this->json('',0,'获取失败');
    }
}