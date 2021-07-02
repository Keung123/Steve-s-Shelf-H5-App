<?php
/**
 * Created by PhpStorm.
 * User: benbenkeji
 * Date: 2018/10/15
 * Time: 14:58
 */
namespace app\common\service;

use app\common\model\Sensitive;

class Sen extends Base{

    public function __construct(){
        $SensitiveModel= new Sensitive();
        $this->model=$SensitiveModel;
    }
    public function pure($content)
    {
        //根据位置引用
        require_once (EXTEND_PATH . 'pscws4.class.php');
        $sw = new \PSCWS4();
        $sw->set_charset('utf8');
        $sw->set_dict(config('scws.dict'));
        $sw->set_rule(config('scws.rule'));
        $sw->set_ignore(true);
        $sw->set_multi(true);
        $sw->send_text($content);

        $words = [];
        while ($word = $sw->get_result()) {
            $words = array_merge($words, array_column($word, 'word'));
        }

        $sw->close();
        if (empty($words)) {
            return false;
        }

        $is_sen = $this->model->where('sst_name', 'in', $words)->count();
        return $is_sen ? true: false;
    }
}