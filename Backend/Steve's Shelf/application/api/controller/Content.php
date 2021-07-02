<?php
namespace app\api\controller;

use app\common\service\Content as ContentService;

class Content extends Common{
    protected $content;
    public function __construct()
    {
        $this->content = new ContentService();
    }

    /**
     * 内容详情
     */
    public function info()
    {
        $id=input('get.content_id');
        $map['content_id']=$id;
        $info=$this->content->find($map);
        return $this->json($info);
    }

    /**
     * 获取内容--xchen 
     */
    public function getContent()
    {
        $type = input('request.type', 0);   // usercom：用户协议
        $info = $this->content->getContent($type);
        if(!$info['code']){
            return $this->json([], 0, $info['msg']);
        }
        return $this->json($info['data']);
    }

    /**
     * 新手课堂
     */
    public function newClass()
    {
        $category_id = db('content_category')->where('category_name','新手课堂')->value('category_id');
        if($category_id){
            $list = db('content')->where(['category_id'=>$category_id,'status'=>'normal'])->field('content_id,title,description,picture,content')->order('weigh desc')->select();
        }else{
            $list = [];
        }
        if (!empty($list)) {
            return  $this->json($list);
        } else {
            return  $this->json([], 0);
        }
    }

    /**
     * 新手课堂详情
     * @param integer contentId
     * @return \think\response\Json
     */
    public function classDetail()
    {
        $content_id = input('request.contentId');
        $content = db('content')->where(['content_id'=>$content_id,'status'=>'normal'])->field('content,title')->find();
        if (!empty($content)) {
            return  $this->json($content);
        } else {
            return  $this->json([], 0);
        }
    }
}