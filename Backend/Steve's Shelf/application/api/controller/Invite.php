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
        $data = [
            'invite_code' => $code,
        ];
        if (empty($code)) {
            return $this->json('', 0, '未生成邀请码');
        }
        return $this->json(['invite_code' => $data]);
    }

    public function getFriends()
    {
        $level = input('level', 0);
        $uid = $this->uid;
        if ($level == 0) {
            $fids= db('users_tree')->where('t_p_uid', $uid)->column('t_uid');
        } else {
            $fids =  db('users_tree')->where('t_g_uid', $uid)->column('t_uid');
        }
        if ($fids) {
            $friends = db('users')->field('user_name,user_avat,CONCAT(LEFT(user_mobile,3),"****",RIGHT(user_mobile,4)) as phone,FROM_UNIXTIME(user_reg_time,"%Y-%m-%d") as regtime')->where('user_id', 'in', $fids)->select();
            return $this->json($friends);
        } else {
            return $this->json($fids,0, '数据为空');
        }
    }
}