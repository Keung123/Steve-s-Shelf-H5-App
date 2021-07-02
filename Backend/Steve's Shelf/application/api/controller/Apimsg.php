<?php

namespace app\api\controller;

use app\common\service\User as UserService;
use think\Db;
use getui\Pushs;

/**
 * swagger: 聊天相关
 */
class Apimsg extends Common
{
    protected $user;
    public $baseurl = '';
    public function __construct()
    {
        $this->user = new UserService();
        $this->baseurl = request()->domain();
    }


    /**
     * post: 显示聊天详情
     * path: msgView
     * method: msgView
     * param: uid - {int} 发送人Id
     * param: touid - {int} 接收人Id
     */
    public function msgView()
    {
        $uid = input("uid");
        $touid = input("touid");
        $where =  "(uid=" . $uid . " and touid=" . $touid . ") or (uid=" . $touid . " and touid=" . $uid . ")";
        $list = Db::name("msg")->where($where)->order("id desc")->limit(100)->select();
		if($uid){
			db("msg_list")->where("touid=" . $uid)->where("uid=" . $touid)->setField("status", 1);
		}

        foreach ($list as $k => $v) {
            if ($v['uid'] == $uid) {
                $id = $v['touid'];
            } else {
                $id = $v['uid'];
            }
		
            $list[$k]['u'] = db("users")->where("user_id=" . $v['uid'])->field("user_id,user_name,user_avat")->find();
            if (!$list[$k]['u']) {
                $list[$k]['u']['user_avat'] = '';
                $list[$k]['u']['user_name'] = '游客';
            }
            if ($v['looked'] == 0) {
                if ($uid == $v['touid']) {
                    db("msg")->where("id=" . $v['id'])->setField("looked", 1);
                }
            }
        }

        $ret['code'] = 0;
        $ret['data'] = array_reverse($list);
        $ret['count'] = count($list);
        return json($ret);
    }

    /**
     * post: 客服显示聊天详情
     * path: msgView
     * method: msgView
     * param: uid - {int} 发送人Id
     * param: touid - {int} 接收人Id
     */
    public function kfmsgView()
    {
        $uid = input("uid");
        $kf_id = input("kf_id");
        $where =  "(uid=" . $uid . " and kf_id=" . $kf_id . ") or (kf_id=" . $kf_id . " and touid=" . $uid . ")";
        $list = Db::name("msg")->where($where)->order("id desc")->limit(100)->select();
		if($uid){
			db("msg_list")->where("touid=" . $uid)->where("kf_id=" . $kf_id)->setField("status", 1);
		}
        foreach ($list as $k => $v) {
            if ($v['uid'] == $uid) {
                $id = $v['touid'];
            } else {
                $id = $v['uid'];
            }
			if($v['type'] == 2 && !($v['content'])){
				$list[$k]['content'] = $v['content'];
			}

            $list[$k]['u'] = db("users")->where("user_id=" . $v['uid'])->field("user_id,user_name,user_avat")->find();
            if (!$list[$k]['u']) {
				$kefu = Db::name('kefu')->where('kefu_id',$v['kf_id'])->find();
				$list[$k]['u']['user_avat'] = $kefu['kefu_avat'];
                $list[$k]['u']['user_name'] = $kefu['kefu_name']; 
            }
            if ($v['looked'] == 0) {
                if ($uid == $v['touid']) {
                    db("msg")->where("id=" . $v['id'])->setField("looked", 1); 
                }
            }
        }

        $ret['code'] = 0;
        $ret['data'] = array_reverse($list);
        $ret['count'] = count($list);
        return json($ret);
    }

    /**
     * post: 显示聊天记录列表
     * path: msgList
     * method: msgList
     * param: uid - {int} 获取谁的消息列表uid
     */
    public function msgList()
    {
        $uid = input("uid");
        $list_1 = db("msg_list")->where("(uid=" . $uid . " or touid=" . $uid.') and kf_id>0')->order("date desc")->group('kf_id')->limit(100)->select();
        $list_2 = db("msg_list")->where("(uid=" . $uid . " or touid=" . $uid.') and kf_id=0')->order("date desc")->group('uid')->limit(100)->select();
        if(!empty($list_1) && !empty($list_2)){
            $list = array_merge($list_1,$list_2);
        }elseif(!empty($list_1)){
            $list = $list_1;
        }elseif(!empty($list_2)){
            $list = $list_2;
        }else{
            $list = [];
        }
        
        if($list){
            foreach ($list as $k => $v) {
                if ($v['uid'] == $uid) {
                    $id = $v['touid'];
                } else {
                    $id = $v['uid'];
                }
                if ($v['kf_id']) {
                    $list[$k]['num'] = db("msg")->where("touid=" . $uid . " and looked=0 and kf_id = ". $v['kf_id'])->count();
                } else {
                    $list[$k]['num'] = db("msg")->where("touid=" . $uid . " and uid=" . $id . "  and looked=0")->count();
                }
               $list[$k]['u'] = db("users")->where("user_id=" . $id)->field("user_id,user_name,user_avat")->find();
                if(!$list[$k]['u']){
                      $kefu = db("kefu")->where("kefu_id=" . $v['kf_id'])->field("kefu_id,kefu_name,kefu_avat")->find();
                      if($kefu){
                        $list[$k]['u']['user_id'] = $kefu['kefu_id'];
                        $list[$k]['u']['user_name'] = $kefu['kefu_name'];
                        $list[$k]['u']['user_avat'] = $kefu['kefu_avat'];  
                      }
                }
                $list[$k]['date'] = date('Y-m-d H:i:s', $v['date']);
            }
        }
        
        $ret['kefu_id'] = 1;//默认客服
        $ret['code'] = 0;
        $ret['data'] = $list;
        $ret['count'] = count($list);
        return json($ret);
    }    
	/**
     * post: 显示聊天记录列表
     * path: msgList
     * method: msgList
     * param: uid - {int} 获取谁的消息列表uid
     */
	public function kfmsgList()
    {
        $uid = input("uid");
        $list = db("msg_list")->where("uid=" . $uid . " or touid=" . $uid)->order("date desc")->limit(100)->select();

        foreach ($list as $k => $v) {
            if ($v['uid'] == $uid) {
                $id = $v['touid'];
            } else {
                $id = $v['uid'];
            }
            $list[$k]['num'] = db("msg")->where("touid=" . $uid . " and uid=" . $id . "  and looked=0")->count();
            $list[$k]['u'] = db("users")->where("user_id=" . $id)->field("user_id,user_name,user_avat")->find();
            $list[$k]['date'] = date('Y-m-d H:i:s', $v['date']);
        }

        $ret['kefu_id'] = 1;//默认客服
        $ret['code'] = 0;
        $ret['data'] = $list;
        $ret['count'] = count($list);
        return json($ret);
    }

    /**
     * post: 添加聊天内容
     * path: msgAdd
     * method: msgAdd
     * param: uid - {int} 消息发送者
     * param: touid - {int} 消息接收者
     * param: content - {strinb} 消息内容
     * param: type - {int} = [1|2|3] 类型(1: 文字, 2: 图片, 3: 语音)
     * param: genre - {int} = [0|1] 类型(消息类型:0普通聊天，1客服聊天)
     */
    public function msgAdd()
    {
        $uid = input("uid");
        $data['touid'] = input("touid", 1);
        $content = input("content");
        $data['uid'] = $uid;
        $type = input("type");
		//消息类型
		if(!input("genre")){
			  $genre = 0;
		}
        $genre = input("genre");


        $data['content'] = $content;
        $data['type'] = $type;
        $data['date'] = time();
		//普通聊天
		if(!$genre){
			 if ($uid == $data['touid']) {
				$ret['code'] = 1;
				$ret['msg'] = '不可以与自己聊天哦！';
				return json($ret);
			}
		}
       

        $do = db("msg")->insert($data);
        if ($do) {
			//推送 
			$msg = [
				'content'=>$content,//透传内容
				'title'=>'您有一条好友信息！',//通知栏标题
				'text'=>$content,//通知栏内容
				'curl'=>request()->domain(),//通知栏链接
			];
			$clientId = $this->getClient($data['touid']);
			 
			 if($clientId){
				 $datas=array(
				0=>['client_id'=>$clientId],
				'system'=>2,//1为ios	
				);
				$Pushs = new Pushs();
			 }
			
            $ch = db("msg_list")->where("(uid=" . $uid . " and touid=" . $data['touid'] . ") or (uid=" . $data['touid'] . " and touid=" . $uid . ")")->find();

            if ($type == 2) {
                $content = '[图片]';
            }
            if ($type == 3) {
                $content = '[语音]';
            }

            if ($ch) {
                $da['id'] = $ch['id'];
                $da['content'] = $content;
                $da['date'] = time();
                db("msg_list")->update($da);
            } else {
                $da['uid'] = $data['uid'];
                $da['touid'] = $data['touid'];
                $da['content'] = $content;
                $da['date'] = time();
                db("msg_list")->insert($da);
            }
            $ret['code'] = 0;
            $ret['msg'] = 'ok';
            return json($ret);
        }

 
        $ret['code'] = 1;
        $ret['msg'] = 'error';
        return json($ret);

    }   
	/**
     * post: 添加客服聊天内容
     * path: msgAdd
     * method: msgAdd
     * param: uid - {int} 消息发送者
     * param: touid - {int} 消息接收者
     * param: content - {strinb} 消息内容
     * param: type - {int} = [1|2|3] 类型(1: 文字, 2: 图片, 3: 语音)
     * param: genre - {int} = [0|1] 类型(消息类型:0普通聊天，1客服聊天)
     */
    public function kfmsgAdd()
    {
        $uid = input("uid");
        $data['kf_id'] = input("kf_id", 1);
        $content = input("content");
        $data['uid'] = $uid;
        $type = input("type");
		if($type == 5){
			$result = $this->replyMessage($uid,$data['kf_id'],$content);
			if($result){
				$ret['code'] = 0;
				$ret['msg'] = 'ok';
				return json($ret);
			}else{
				 $ret['code'] = 1;
				 $ret['msg'] = 'error';
				 return json($ret);
			}
		}
		//消息类型
		if(!input("genre")){
			  $genre = 1;
		}
        $genre = input("genre");
        if ($type == 4) {
            $content = $content;
        }

        $data['content'] = $content;
        $data['type'] = $type;
        $data['date'] = time();
        $data['status'] = 0;
        $data['touid'] = 0;
        $data['genre'] = $genre;
	
        $do = db("msg")->insert($data);
        if ($do) {
            $ch = db("msg_list")->where("(uid=" . $uid . " and kf_id=" . $data['kf_id'] . ") or (kf_id=" . $data['kf_id'] . " and touid=" . $uid . ")")->find();

            if ($type == 2) {
                $content = '[图片]';
            }
            if ($type == 3) {
                $content = '[语音]';
            }

            if ($ch) {
                $da['id'] = $ch['id'];
                $da['content'] = $content;
                $da['genre'] = $genre;
                $da['date'] = time();
                db("msg_list")->update($da);
            } else {
                $da['uid'] = $data['uid'];
                $da['kf_id'] = $data['kf_id'];
                $da['touid'] = 0;
                $da['content'] = $content;
                $da['date'] = time();
                $da['genre'] =  1;
                $da['status'] =  0;
                db("msg_list")->insert($da);
            }
            $ret['code'] = 0;
            $ret['msg'] = 'ok';
            return json($ret);
        }

        $ret['code'] = 1;
        $ret['msg'] = 'error';
        return json($ret);

    }

    /**
     * post: 添加聊天图片
     * path: upload
     * method: upload
     * param: uploadkey - {file} 消息发送者
     */
    public function upload()
    {
        $image = \think\Image::open(request()->file('uploadkey'));

        $image->thumb(300, 300, \think\Image::THUMB_SCALING);
        $savePath = './uploads/xinxi/' . date("Ymd") . "/";
        $saveName = uniqid() . '.jpg';
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }
        $image->save($savePath . $saveName);
        $url = $savePath . $saveName;

        $ret['pic'] = $this->baseurl . str_replace("./", "/", $url);
        $ret['code'] = 0;

        return json($ret);
    }

    /**
     * post: 文件上传
     * path: upfile
     * method: upfile
     * param: uploadkey - {file} 消息发送者
     */
    public function upfile()
    {
        // 获取表单上传文件
        $file = request()->file('uploadkey');

        if (empty($file)) {
            $this->error('请选择上传文件');
        }
        // 移动到框架应用根目录/public/uploads/ 目录下
        $savePath = './uploads/xinxi/';
        $info = $file->move($savePath);
        if ($info) {
            $url = $savePath . $info->getSaveName();

            $ret['code'] = 0;
            $ret['url'] = $this->baseurl . str_replace("./", "/", $url);
            return json($ret);

        } else {
            // 上传失败获取错误信息
            $this->error($file->getError());
        }

    }
	
    public function kefuList()
    {
       $data = db("kefu")->where('status',0)->field("kefu_id,kefu_name,kefu_avat")->select();
	   $now = $data[array_rand($data)];
	   if(!$now){
			return $this->json('', 0, '获取失败');
		}
	   return $this->json($now);

    }
	/**
	 *   自动回复
	 *  
	 */
	   public function replyMessage($touid,$kf_id,$content_id)
    {
		$touid = $touid;
        $data['kf_id'] = $kf_id;
        $content_id = $content_id;
		$row = Db('content')->where('content_id',$content_id)->field('content')->find();
		if($row['content']){
			$content =  strip_tags($row['content']);	
		}else{
			$content = '暂时内容！';
		}
        
        $data['touid'] = $touid;
        $data['uid'] = 0;
        $type = 1;
		//消息类型
		if(!input("genre")){
			  $genre = 1;
		}
        $genre = input("genre");

        $data['content'] = $content;
        $data['type'] = $type;
        $data['date'] = time();
		 
        $res = db("msg")->insert($data);
        if ($res) {
		 
            $ch = db("msg_list")->where($where2)->find();
            if ($ch) {
                $da['id'] = $ch['id'];
                $da['content'] = $content;
                $da['date'] = time();
                db("msg_list")->update($da);
            } else {
                $da['uid'] = $data['uid'];
                $da['touid'] = $data['touid'];
                $da['kf_id'] = $data['kf_id'];
                $da['content'] = $content;
                $da['status'] = 0;
                $da['date'] = time();
                db("msg_list")->insert($da);
            }
        }
        return  $res;
	}

    /**
     *  客服消息  活动消息
     */
    public function NewsMatter(){
        $content_id = input('request.content_id');
        $result = $this->user->getMatter($content_id);
        if(!$result){
            return $this->json('', 0, '获取失败');
        }
        $result['create_time'] = date('Y-m-d H:i',$result['create_time']);
        return $this->json($result);
    }
    /**
     *  客服消息  在线客服
     */
    public function NewsOnline(){
        $uid = input('request.uid');
        $token = input('request.token');
        $uid = $this->getUid($token, $uid);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $result = $this->user->getOnline($uid);
        if(!$result){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }
    /**
     *  客服消息  在线客服
     */
    public function sendOnline(){
        $uid = input('request.uid');
        $token = input('request.token');
        $touid = input('request.touid');
        $uid = $this->getUid($token, $uid);
        if(!$uid){
            return $this->json('', 0, '未知参数');
        }
        $content = input('request.content');//内容
        $type = input('request.type');//1文字 2图片 3语音
        $data = [
            'genre' => 1,
            'content' => $content,
            'type' => $type,
            'uid' => $uid,
            'touid' => $touid,
        ];
        $res = $this->user->sendOnline($data);
        if(!$res){
            return $this->json('', 0, '获取失败');
        }
        return $this->json('', 1, '发送成功');
    }
}
	