<?php
namespace app\admin\controller;

use Qiniu\Auth as Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
class Upload extends Base{
	
	/*
	* 图片上传
	*/
	public function index(){
	    // 获取表单上传文件 例如上传了001.jpg
	    $file = request()->file('file');
	    // 移动到框架应用根目录/public/uploads/ 目录下
	    $info = $file->move(ROOT_PATH . 'public/uploads');
	    if($info){
	        $domain = request()->domain();
	        return json(['code'=>1,'msg'=>"",'data'=>['url'=>$domain.'/uploads/'.str_replace('\\','/', $info->getSaveName())]]);
	    }else{
	        // 上传失败获取错误信息
	        return json(['code'=>0,'msg'=>$file->getError()]);
	    }
	}
    /*
    * 上传视频到七牛云
    */
    public function fileUp()
    {
        require_once EXTEND_PATH.'Qiniu/autoload.php';
        $file = request()->file('file');
        // 要上传图片的本地路径
        $filePath = $file->getRealPath();
        $ext = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);  //后缀
        // 上传到七牛后保存的文件名
        $key = substr(md5($file->getRealPath()), 0, 5) . date('YmdHis') . rand(0, 9999) . '.' . $ext;
        // 需要填写你的 Access Key 和 Secret Key
        $accessKey = config('ACCESSKEY');
        $secretKey = config('SECRETKEY');
        // 构建鉴权对象
        $auth = new Auth($accessKey, $secretKey);
        // 要上传的空间
        $bucket = config('BUCKET');
//        $domain = config('DOMAINImage');
        $token = $auth->uploadToken($bucket);
        // 初始化 UploadManager 对象并进行文件的上传
        $uploadMgr = new UploadManager();
        // 调用 UploadManager 的 putFile 方法进行文件的上传
        list($ret, $err) = $uploadMgr->putFile($token, $key, $filePath);
        if ($err !== null) {
            return json(['code'=>0,'msg'=>$err]);
        } else {
            //返回图片的完整URL
            $url = config('QINIUHOST');
            return json(['code'=>1,'msg'=>"",'data'=>['url'=>$url.$ret['key']]]);
        }
    }
}