<?php

namespace app\admin\controller\sms;

use app\admin\controller\Base;
use app\common\service\PayWeChatAli as PayWeChatAliService;

class Pay extends Base
{
    public $payWeChatAliService;

    public function __construct()
    {
        parent::__construct();
        $this->payWeChatAliService = new PayWeChatAliService();
    }
}