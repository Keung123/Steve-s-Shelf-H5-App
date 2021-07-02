<?php

namespace app\api\controller;

use app\common\service\User as UserService;

class Question extends Common{
    protected $user;
    public function __construct()
    {
        $this->user = new UserService();
    }

    /**
     * description:问题分类列表
     * @param string name
     * @return json
     */
    public function familiar()
    {
        $category_name = trim(input('request.name'));
        $result = $this->user->getTypeList($category_name='常见问题');
        if($result == 0){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }
    /**
     * description:问题列表
     * @param int categoryId
     * @return json
     */
    public function questionList()
    {
        $category_id = input('request.categoryId');
        $result = $this->user->getCenterList($category_id);
        foreach($result['list'] as &$val){
            $val['create_time'] = date('Y-m-d H:i',$val['create_time']);
        }
        if(!$result){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }

    /**
     * description:常见问题
     * @return json
     */
    public function hotFamiliar()
    {
        $result = $this->user->getFamiliar();
        foreach($result as $key=>$val){
            $result[$key]['title'] = $key + 1 .'.'.$val['title'];
            $val['create_time'] = date('Y-m-d H:i',$val['create_time']);
        }
        if(!$result){
            return $this->json('', 0, '获取失败');
        }
        return $this->json($result);
    }

    /**
     * description: 问题内容
     * @param int contentId 内容id
     * @return \think\response\Json
     */
    public function questionContent()
    {
        $content_id = input('request.contentId');
        $result = $this->user->getMatter($content_id);
        if(!$result){
            return $this->json('', 0, '获取失败');
        }
        $result['create_time'] = date('Y-m-d H:i',$result['create_time']);
        return $this->json($result);
    }
}