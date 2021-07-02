<?php
namespace app\index\controller;
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
use think\Controller;
use app\common\service\Config as ConfigService;
class Index extends Controller
{
    //2019新年第一更
    public function _initialize()
    {
        parent::_initialize();
    }
    //下载页面
    public function index(){
        $ConfigService=new ConfigService();
        $res = $ConfigService->find();
        $this -> assign('config',$res);
    	return view();
    }
    //注册页面
    public function register()
    {
    	$user_id = input('v_id');
    	$this -> assign('user_id',$user_id);
       	return view('register');
    }
    public function art_detail(){
        $id = input('id');
        $detail = db('article') -> alias('a')
                    -> field('a.*,ac.name')
                    -> join('article_category ac','ac.id = a.category_id','LEFT') 
                    -> where('a.id', $id)->find();
        $this -> assign('view',$detail);
        return view();
    }
}
