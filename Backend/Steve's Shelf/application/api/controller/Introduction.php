<?php

namespace app\api\controller;

use app\common\service\Introduction as Intro;

class Introduction extends Common
{
    protected $intro;

    public function __construct()
    {
        $this->intro = new Intro();
    }

    /**
     * 引导页图片
     */
    public function index()
    {
        $data = $this->intro->details();

        if ($data) {
            return $this->json($data);
        }
        return $this->json('',0,'获取失败');
    }
}