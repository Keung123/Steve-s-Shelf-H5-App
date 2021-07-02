<?php
namespace app\api\controller;

use Qiniu\Storage\ResumeUploader;
use think\config;
//引入七牛云的相关文件
use Qiniu\Auth as Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use Qiniu\Config as qiniuConfig;
class Niuyun
{
    /*
     * 上传视频到七牛云
     * 七牛php-sdk会判断文件大小，根据文件大小选择使用表单上传和分片上传
     */
    public function videoUp()
    {
        require_once EXTEND_PATH.'Qiniu/autoload.php';
        $file = request()->file('uploadkey');
        if (empty($file)) {
            return $this->json('', 0, '没有上传文件');
        }
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
            return $this->json('', 0, '上传失败');
        } else {
            //返回图片的完整URL
            $url = config('QINIUHOST');
            return $this->json(['img' => $url.$ret['key']]);
        }
    }
    /*
    * 返回json格式数据
    */
    public function json($data=[],$status=1,$msg="获取成功"){
        return json(['data'=>$data,'status'=>$status,'msg'=>$msg]);
        exit;
    }
    //七牛base64上传方法
    public function phpCurlImg($remote_server,$post_string,$upToken)
    {
        $headers = array();
        $headers[] = 'Content-Type:application/octet-stream';
        $headers[] = 'Authorization:UpToken ' . $upToken;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remote_server);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    /*
     * 七牛云 上传  base64
     */
    function qiniu_upload($base64_all)
    {
//        $base64_all = input('uploadkey'); // 测试使用
        file_put_contents('./a.txt', $base64_all.PHP_EOL);
        require_once EXTEND_PATH.'Qiniu/autoload.php';
        $accessKey = config('ACCESSKEY');
        $secretKey = config('SECRETKEY');
        $bucket = config('BUCKET');
        $auth = new Auth($accessKey, $secretKey);
        $upToken = $auth->uploadToken($bucket, null, 3600);//获取上传所需的token
        $base64_all = trim($base64_all);
        if($base64_all){
            $all_img = explode('data:',$base64_all);
            $image = [];
            foreach($all_img as $val){
                if($val){
                    //生成图片key
                    preg_match('/image\/(\w+);base/',$val,$match);
                    $name = date('YmdHis') . rand(0, 99999).'.'.$match[1];
                    $Key = base64_encode($name);
                    $val = str_replace('image/'.$match[1].';base64,', '', $val);
                    $val = trim($val,',');
                    $ret = $this->phpCurlImg("http://up-z1.qiniup.com/putb64/-1/key/".$Key,$val,$upToken);
                    $ret = json_decode($ret, true);
                    if ($ret) {
                        $url = config('QINIUHOST');
                        $image[] = $url.$ret['key'];  // 获取上传后的url
                    } else {
                        return ['code' => 0, 'msg' => '上传图片失败'];
                    }
                }
            }
            return ['code' => 1, 'data' => implode(',', $image)];
        }
        return ['code' => 0, 'msg' => '未找到图片文件'];
    }
    /*
 * 七牛云 上传  base64
 */
    function qiniu_base_upload()
    {
        $base64_img = input('base64_img', '');
        if (!$base64_img) {
            return $this->json('', 0, '上传失败');
        }
        file_put_contents('./a.txt', $base64_img.PHP_EOL);
        require_once EXTEND_PATH.'Qiniu/autoload.php';
        $accessKey = config('ACCESSKEY');
        $secretKey = config('SECRETKEY');
        $bucket = config('BUCKET');
        $auth = new Auth($accessKey, $secretKey);
        $upToken = $auth->uploadToken($bucket, null, 3600);//获取上传所需的token
        $base64_img = trim($base64_img, 'data:');
        //生成图片key
        preg_match('/image\/(\w+);base/',$base64_img,$match);
        $base64_img = str_replace('image/'.$match[1].';base64,', '', $base64_img);
        $name = date('YmdHis') . rand(0, 99999).'.png';
        $Key = base64_encode($name);
        $ret = $this->phpCurlImg("http://up-z1.qiniup.com/putb64/-1/key/".$Key,$base64_img,$upToken);
        $ret = json_decode($ret, true);
        if ($ret) {
            $url = config('QINIUHOST');
            return $this->json(['img' => $url.$ret['key']]);
        } else {
            return $this->json('', 0, '上传失败');
        }


    }
    /*
     * 后台上传 分片
     * 备用
     */
    public function fileUp()
    {
        require_once EXTEND_PATH.'Qiniu/autoload.php';
        $accessKey = 'zusmRS0yGAOOEnljjpQjYiZSTfzgghr_gYbLaq-g';
        $secretKey = 'UsvhZHuppcPK0SJ_u66S_OQC5IXB2qa1KesLNwSe';
        $auth = new Auth($accessKey, $secretKey);
        $bucket = 'block';
        // 生成上传Token
        $token = $auth->uploadToken($bucket);
        // 构建 UploadManager 对象
        // $uploadMgr = new UploadManager();

        $config = new qiniuConfig();
        $file_ext = explode('.', $_FILES['uploadkey']['name']);
        $fileName = time().$file_ext[1];
        $inputStream = fopen($_FILES['uploadkey']['tmp_name'], 'r');
        $type = $_FILES['uploadkey']['type'];
        $size = filesize($_FILES['uploadkey']['tmp_name']);

        if ($_FILES){
            $ResumeUploader = new ResumeUploader($token,$fileName, $inputStream, $size, ['fileSize', 'encodedKey', 'encodedMimeType', 'encodedUserVars'], $type, $config);
            $result = $ResumeUploader->upload('uploadkey');
            var_dump($result);
        }

    }
}