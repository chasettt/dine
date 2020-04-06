<?php
namespace app\common\model;

class Third extends Common
{
    protected $table = 'd_ad_third';

    protected $pk = 'third_id';

    protected $resultSetType = 'collection';

    public function getThirdInfo($condition, $field = '*')
    {
        return $this->field($field)->where($condition)->find();
    }
}