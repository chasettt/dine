<?php

namespace app\api\controller;

class Love extends Base
{
    public function list()
    {
        return $this->returnMsg(200, 'success', []);
    }
}