<?php
namespace app\api\controller;

use think\Controller;
use think\Db;
use think\Request;
use app\common\service\Goods as GoodsService;
use app\common\service\Config;
class Common extends Controller {
	public function __construct(){
        parent::__construct();
		header("Access-Control-Allow-Origin: *"); // 允许任意域名发起的跨域请求  
		header('Access-Control-Allow-Headers: X-Requested-With,X_Requested_With');
		header("Access-Control-Allow-Credentials: true");
		if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS'){
		    exit();
		}
        // 调用定时任务
        $goods_service = new GoodsService();
        $goods_service->timedTask();
	}
	public function getCom()
    {

		//获取配置信息
		$ConfigService=new Config();
		$config=$ConfigService->find();
		$commission = json_decode($config['commission'],true);
		return $commission;
	}
	/**
	 * 返回json格式数据
	 */
	public function json($data=[],$status=1,$msg="获取成功")
    {
		return json(['data'=>$data,'status'=>$status,'msg'=>$msg]);
		exit;
	}

    /**
	 * 根据token获取uid
	 */
    public function getUid($token, $uid)
    {
        if (!$token) {
            return false;
        }
        $userinfo = Db::name('users')->where(array('token' => $token))->find();
        if (!$userinfo) {
            return false;
        }
        if ($userinfo['user_id'] == $uid) {
            return $userinfo['user_id'];
        } else {
            return false;
        }
    }

    /**
     * 图片上传
     */
    public function imgUpload(){
    	$oImg = $_FILES['uploadkey'];
    	if($oImg['error']){
    		return $this->json('', 0, '上传图片失败');
    	}

    	//处理图片
    	$base_info = getimagesize($oImg['tmp_name']);
    	$width = $base_info[0];
    	$height = $base_info[1];
    	switch($base_info[2]){
    		case 1 : $obj = imagecreatefromgif($oImg['tmp_name']); break;
    		case 2 : $obj = imagecreatefromjpeg($oImg['tmp_name']); break;
    		case 3 : $obj = imagecreatefrompng($oImg['tmp_name']); break;
    	}

    	$new_w = 150;
    	$new_h = 150;
    	$new_obj = imagecreatetruecolor($width, $height);
    	imagecopyresampled($new_obj, $obj, 0, 0, 0, 0, $width, $height, $width, $height);

    	//上传图片
    	$path_info = pathinfo($oImg['name']);
    	$tmp_name = $this->imgName($path_info['extension']);
    	$tmp_p_dir = ROOT_PATH.'public'.DS.'uploads'.DS;
    	$tmp_c_dir = date('Ymd', time());
    	if(!is_dir($tmp_p_dir.$tmp_c_dir)){
    		mkdir($tmp_p_dir.$tmp_c_dir, 0777, true);
    	}
    	while(file_exists($tmp_p_dir.$tmp_c_dir.DS.$tmp_name)){
    		$tmp_name = $this->imgName($path_info['extension']);
    	}
    	switch($base_info[2]){
    		case 1 : imagegif($new_obj, $tmp_p_dir.$tmp_c_dir.DS.$tmp_name); break;
    		case 2 : imagejpeg($new_obj, $tmp_p_dir.$tmp_c_dir.DS.$tmp_name); break;
    		case 3 : imagepng($new_obj, $tmp_p_dir.$tmp_c_dir.DS.$tmp_name); break;
    	}

    	imagedestroy($obj);
    	imagedestroy($new_obj);
        return $this->json(['img' => '/uploads/'.$tmp_c_dir.'/'.$tmp_name]);
    }

    /**
     * 生成图片名
     */
    public function imgName($ext){
    	return date('His', time()).sprintf('%03d',microtime() * 1000).mt_rand(0,999).'.'.$ext;
    }
    /**
     * @param $data //传递过来的参数
     * @param $checkName //必传的参数
     * @return bool
     * 判断指定参数是否为空
     */
    //判断是否为空
    public static function checkEmploy($data,$checkName){
        foreach($checkName as $key=>$value){
            if($data[$value]==''){
                echo json_encode(array('status'=>0,'msg'=>'缺少'.$value.'参数','data'=>[]));exit;
            }
        }
        return true;
    }

    /**
     * @param $where
     * 获得省市区信息
     */
    public function getRegion($where){
        $regionName=Db::name("region")->where($where)->value("region_name");
        return $regionName;
    }

    /**
     * base64图片处理
     */
    public function imgBaseUpload($imgbase){
        if($imgbase){
            $all_img = explode('data:',$imgbase);
            $image = [];
            foreach($all_img as $val){
                if($val){
                    preg_match('/image\/(\w+);base/',$val,$match);

                    //生成目录
                    $img_p_dir = ROOT_PATH.'uploads'.DS;
                    $img_c_dir = date('Ymd', time());

                    if(!is_dir($img_p_dir.$img_c_dir)){
                        mkdir($img_p_dir.$img_c_dir,0777,true);
                    }

                    $img_name = $this->imgName($match[1]);
                    while(file_exists($tmp_p_dir.$tmp_c_dir.DS.$img_name)){
                        $img_name = $this->imgName($match[1]);
                    }
                    $img_path = $img_p_dir.$img_c_dir.DS.$img_name;
                    $img_float = explode(',',$val);
                    if(file_put_contents($img_path, base64_decode($img_float[1]))){
                        $image[] = '/uploads/'.$img_c_dir.'/'.$img_name;                        
                    }
                    else{
                        return ['code' => 0, 'msg' => '上传图片失败'];                        
                    }
                }
            }
            return ['code' => 1, 'data' => implode(',', $image)]; 
        }
        return ['code' => 0, 'msg' => '未找到图片文件'];
    }
	
	 /**
     * @param $where
     * 获得省市区信息
     */
    public function getClient($uid){
        $client_id=Db::name("users")->where('user_id',$uid)->value("client_id");
        return $client_id;
    }
	 /**
     * @param  
     * 上传视频
		upload_max_filesize = 500M
		post_max_size = 500M
		memory_limit = 512M
		max_execution_time = 600 
     */
	public function moveUpload备份(){
			$move = $_FILES['uploadkeys'];
			$root_path=ROOT_PATH.'Uploads';
			 
			//上传图片
			$path_info = pathinfo($move['name']);
			$path_size = $move['size'];
			$max_size = 70*1024*1024;
			$tmp_name = $this->imgName($path_info['extension']);
			$file_name = $path_info['extension'];
			if($path_size>$max_size){
				return $this->json('', 0, '视频大于50M');
			}
			if($file_name != 'mp4'){
				return $this->json('', 0, '视频格式不是MP4');
			}
			$tmp_p_dir = ROOT_PATH.'public'.DS.'uploads/video'.DS;
			$tmp_c_dir = date('Ymd', time());
			if(!is_dir($tmp_p_dir.$tmp_c_dir)){
				mkdir($tmp_p_dir.$tmp_c_dir, 0777, true);
			}
			while(file_exists($tmp_p_dir.$tmp_c_dir.DS.$tmp_name)){
				$tmp_name = $this->imgName($path_info['extension']);
			}
			if(!move_uploaded_file($move['tmp_name'], $tmp_p_dir.$tmp_c_dir.DS.$tmp_name)){
				return $this->json('', 0, '上传视频失败');
			}
			else{
				return $this->json(['img' => '/uploads/video/'.$tmp_c_dir.'/'.$tmp_name]);
			}			
		 
	}
	public function moveUpload(){
		$move_curl =Request()->post("move_curl");
		header('content-type:text/html;charset=utf8');
		$ch = curl_init();
		//加@符号curl就会把它当成是文件上传处理
		$data = array('move'=>'@'.$move_curl);
		curl_setopt($ch,CURLOPT_URL, $_SERVER['HTTP_HOST']."/api/Common/uploading");
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		$result = curl_exec($ch);
		curl_close($ch);
		if(!$result){
			return $this->json('', 0, '上传视频失败');
		}
		else{
			return $this->json(['img' => $result]);
		}			
	}
	/**
     * @param  
     * 上传视频
		upload_max_filesize = 500M
		post_max_size = 500M
		memory_limit = 512M
		max_execution_time = 600 
     */
	public function uploading(){
			$move = $_FILES['move'];
			$root_path=ROOT_PATH.'Uploads';
			//上传视频
			$path_info = pathinfo($move['name']);
			$path_size = $move['size'];
			$max_size = 70*1024*1024;
			$tmp_name = $this->imgName($path_info['extension']);
			$file_name = $path_info['extension'];
			if($path_size>$max_size){
				return $this->json('', 0, '视频大于50M');
			}
			if($file_name != 'mp4'){
				return $this->json('', 0, '视频格式不是MP4');
			}
			$tmp_p_dir = ROOT_PATH.'public'.DS.'uploads/video'.DS;
			$tmp_c_dir = date('Ymd', time());
			if(!is_dir($tmp_p_dir.$tmp_c_dir)){
				mkdir($tmp_p_dir.$tmp_c_dir, 0777, true);
			}
			while(file_exists($tmp_p_dir.$tmp_c_dir.DS.$tmp_name)){
				$tmp_name = $this->imgName($path_info['extension']);
			}
			if(!move_uploaded_file($move['tmp_name'], $tmp_p_dir.$tmp_c_dir.DS.$tmp_name)){
				echo ('上传视频失败');
			}
			else{
				echo('/uploads/video/'.$tmp_c_dir.'/'.$tmp_name);
			}			
		 
	}


    // 日志记录
    public function write_log($remark, $id)
    {
        $add['uid'] = session('admin_id');
        $add['ip_address'] = request()->ip();
        $add['controller'] = request()->controller();
        $add['action'] = request()->action();
        $add['remarks'] = $remark;
        $add['number'] = $id;
        $add['create_at'] = time();
        db('web_log')->insert($add);

    }
	//获取配置信息
	public function getPick(){
		 
		//获取配置信息
		$ConfigService=new Config();
		$config=$ConfigService->find();
		$pick = json_decode($config['pick'],true);
		return $pick;
	}
			

}