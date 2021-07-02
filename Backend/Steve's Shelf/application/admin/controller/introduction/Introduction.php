<?php

namespace app\admin\controller\introduction;

use app\common\service\Introduction as Intro;
use app\common\model\Introduction  as IntroModel;
use think\Db;
use think\Request;
use app\admin\controller\Base;

class Introduction extends Base
{
    protected $intro;
    protected $introModel;
    public function __construct()
    {
        parent::__construct();
        $this->intro = new Intro();
        $this->introModel = new IntroModel();
    }

    /**
     * 引导页列表
     */
    public function index()
    {
        if (request()->isAjax()) {
            $res = $this->intro->details();
            $res = array_values($res);
            $total = count($res);
            return json(['total'=>$total,'rows'=>$res]);
        }
        return $this->fetch();
    }

    /**
     * 引导页添加
     */
    public function add()
    {
        if (request()->isAjax()) {
            $params = input('post.row/a');
            $res = $this->intro->addImg($params);
            return AjaxReturn($res,getErrorInfo($res));
        }
        return $this->fetch();
    }

    /**
     * 引导页删除
     */
    public function delete()
    {
        $ids=input('get.ids');
        $map['id']=['in',$ids];
        $res = Db::name('introduction')->where(['id' => ['in', $ids]])->delete();
        return AjaxReturn($res);
    }
}