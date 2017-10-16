<?php
namespace App\Controller;
class Tools extends \CLASSES\WebBase
{
    public function __construct($swoole)
    {
        parent::__construct($swoole);
    }
    public function index()
    {
        $data = array();
        $data['datetime'] = $this->serverTime();
        $this->exportData($data);
    }

    private function serverTime()
    {
        return array(
            'timestamp' => time(),
            'date'      => date('Y-m-d'), //2009-10-10
            'his'       => date('H:i:s'), //22:10:10
        );
    }

    public function subTotal()
    {
        $result = 0;
        if (!empty($_REQUEST['worker']))
        {
            $worker = array();
            foreach ($_REQUEST['worker'] as $key => $val)
            {
                $worker[$key][0] = isset($val[1]['personNum']) ? intval($val[1]['personNum']) : 0;
                $worker[$key][1] = isset($val[1]['money']) ? floatval($val[1]['money']) : 0;
                $worker[$key][2] = isset($val[2]['startTime']) ? strtotime($val[2]['startTime']) : 0;
                $worker[$key][3] = isset($val[2]['endTime']) ? strtotime($val[2]['endTime']) : 0;
                $result += $worker[$key][0] * $worker[$key][1] * (ceil($worker[$key][3] - $worker[$key][2]) / 3600 / 24 + 1);
            }
        }
        return $result;
    }
}