<?php
/**
 * Created by PhpStorm.
 * User: 7howe
 * DateTime: 2019/3/21 16:38
 */

namespace app\api\controller;


class Invite extends Common
{
    private $uid;

    public function __construct()
    {
        parent::__construct();
        $user_id = input('request.uid');
        $token = input('request.token');
        if (isset($user_id) && $user_id && $token) {
            $uid = $this->getUid($token, $user_id);
            if (!$uid) {
                echo json_encode(['data' => [], 'status' => 0, 'msg' => '未知参数'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $this->uid = $uid;
        }
    }

    public function getinfo()
    {
        $code = db('users')->where('user_id', $this->uid)->value('s_invite_code');
        $invite_user_num = db('yinzi')->where(['yin_uid' => $this->uid, 'yin_type' => 1])->count();
        //获得元宝
        $invite_yinzi_num = db('yinzi')->where(['yin_uid' => $this->uid, 'yin_type' => 1])->sum('yin_amount');

        $data = [
            'invite_code' => $code,
            'user_num' => $invite_user_num ?: 0,
            'yz_num' => $invite_yinzi_num ?: 0,
        ];
        if (empty($code)) {
            return $this->json('', 0, '未生成邀请码');
        }
        return $this->json(['invite_code' => $data]);
    }
}