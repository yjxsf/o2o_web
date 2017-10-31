<?php
/**
 * 订单接口
 */
namespace App\Controller;

class Orders extends \CLASSES\WebBase
{
    public function __construct($swoole)
    {
        parent::__construct($swoole);
        $this->orders_dao = new \WDAO\Orders();
        //$this->db->debug = 1;
    }

    public function index()
    {
        $action = (isset($_REQUEST['action']) && '' != trim($_REQUEST['action'])) ? trim($_REQUEST['action']) : 'info';
        if ('' != trim($action))
        {
            $this->$action();
        }
    }

    /**
     * 订单详情
     */
    private function info()
    {
        $data = array();
        if (isset($_REQUEST['o_id']) && intval($_REQUEST['o_id']) > 0) $data['o_id'] = intval($_REQUEST['o_id']); //订单id
        if (!empty($data) && isset($data['o_id']) && 0 < intval($data['o_id']))
        {
            $info = $this->orders_dao->infoData(intval($data['o_id']));
            if (!empty($info))
            {
                $this->exportData($info);
            }
        }
        $this->exportData();
    }

    /**
     * 雇主确认订单/任务
     */
    private function employerConfirm()
    {
        $data = array();
        if (isset($_REQUEST['o_id']) && intval($_REQUEST['o_id']) > 0) $data['o_id'] = intval($_REQUEST['o_id']); //订单id
        if (isset($_REQUEST['t_id']) && intval($_REQUEST['t_id']) > 0) $data['t_id'] = intval($_REQUEST['t_id']); //任务id
        if (isset($_REQUEST['u_id']) && intval($_REQUEST['u_id']) > 0) $data['u_id'] = intval($_REQUEST['u_id']); //雇主id
        if (!empty($data) && isset($data['o_id']) && isset($data['t_id']) && isset($data['u_id']))
        {
            $info = $this->orders_dao->listData($data + array('pager' => 0, 'limit' => 1, 'order' => 'o_id desc'));
            if (isset($info['data']) && !empty($info['data'][0]))
            {
                if (isset($info['data'][0]['o_confirm']) && $info['data'][0]['o_confirm'] == 2) $this->exportData('已经确认过了，无需再次确认');
                //更新了订单状态
                $result = $this->orders_dao->updateData(array('o_confirm' => 2), $data);
                if (!$result)
                {
                    $this->exportData('failure');
                }
                $this->exportData('success');
            }
        }
        $this->exportData('failure');
    }

    /**
     * 工人确认订单/任务 并开始工作
     */
    private function workerConfirm()
    {
        $data = array();
        if (isset($_REQUEST['o_id']) && intval($_REQUEST['o_id']) > 0) $data['o_id'] = intval($_REQUEST['o_id']); //订单id
        if (isset($_REQUEST['t_id']) && intval($_REQUEST['t_id']) > 0) $data['t_id'] = intval($_REQUEST['t_id']); //任务id
        if (isset($_REQUEST['o_worker']) && intval($_REQUEST['o_worker']) > 0) $data['o_worker'] = intval($_REQUEST['o_worker']); //工人id

        if (!empty($data) && isset($data['o_id']) && isset($data['t_id']) && isset($data['o_worker']))
        {
            $info = $this->orders_dao->listData($data + array('pager' => 0, 'limit' => 1, 'order' => 'o_id desc'));

            if (isset($info['data'][0]) && !empty($info['data'][0]))
            {
                if (isset($info['data'][0]['o_confirm']) && intval($info['data'][0]['o_confirm']) == 1)
                {
                    $this->exportData('已经确认过了，无需再次确认');
                }

                //更新了订单状态
                $result = $this->orders_dao->updateData(array('o_confirm' => 1), $data);
                if (!$result)
                {
                    $this->exportData('failure');
                }

                //更新任务状态
                if (isset($info['data'][0]['t_id']) && 0 < intval($info['data'][0]['t_id']))
                {
                    $worker_dao = new \WDAO\Task_ext_worker();
                    $workers_result = $worker_dao->countData(array('t_id' => intval($info['data'][0]['t_id'])));
                    $orders_result  = $this->orders_dao->countData(array('t_id' => intval($info['data'][0]['t_id']), 'o_confirm' => 1));
                    $task_dao = new \WDAO\Tasks();
                    if ($workers_result == $orders_result)
                    {//全开工
                        $task_dao->updateData(array('t_status' => 2), array('t_id' => intval($info['data'][0]['t_id'])));
                    }
                    else
                    {//半开工
                        $task_dao->updateData(array('t_status' => 5), array('t_id' => intval($info['data'][0]['t_id'])));
                    }
                    //变更工人状态为忙
                    $user_dao = new \WDAO\Users(array('table' => 'users'));
                    $users_dao->taskStatus($data['o_worker'], '1');
                }
                $this->exportData('success');
            }
        }
        $this->exportData('failure');
    }

    /**
     * 成单
     */
    private function create()
    {
        //$this->db->debug = 1;
        $data = array();
        //任务工人关系id
        if (isset($_REQUEST['tew_id']) && intval($_REQUEST['tew_id']) > 0) $data['tew_id'] = intval($_REQUEST['tew_id']);
        //任务id
        if (isset($_REQUEST['t_id']) && intval($_REQUEST['t_id']) > 0) $data['t_id'] = intval($_REQUEST['t_id']);
        //工人id
        if (isset($_REQUEST['o_worker']) && intval($_REQUEST['o_worker']) > 0) $data['o_worker'] = intval($_REQUEST['o_worker']);
        //发起人id
        if (isset($_REQUEST['o_sponsor']) && intval($_REQUEST['o_sponsor']) > 0) $data['o_sponsor'] = intval($_REQUEST['o_sponsor']);
//print_r($_REQUEST);exit;
        if (!isset($data['tew_id']) || !isset($data['t_id']) || !isset($data['o_worker']) || !isset($data['o_sponsor'])) $this->exportData('failure');


        //验证任务是否存在
        $worker_dao = new \WDAO\Task_ext_worker();
        $worker_result = $worker_dao->listData(array(
            'pager' => 0,
            'fields' => 'tasks.*, task_ext_worker.*',
            'where' => 'task_ext_worker.tew_id = ' . intval($data['tew_id']) . ' and task_ext_worker.t_id =' . intval($_REQUEST['t_id']),
            'join' => array('tasks', 'tasks.t_id = task_ext_worker.t_id'),
            'order' => 'task_ext_worker.tew_id desc',
            ));
        $worker_result = isset($worker_result['data'][0]) ? $worker_result['data'][0] : array();

        if (empty($worker_result) || !isset($worker_result['tew_worker_num']) || $worker_result['tew_worker_num'] < 1 ||
            !isset($worker_result['t_id']) || $worker_result['t_id'] <= 0 ||
            !isset($worker_result['t_author']) || $worker_result['t_author'] <= 0 ||
            !isset($worker_result['tew_price']) || $worker_result['tew_price'] <= 0 ||
            !isset($worker_result['tew_id']) || $worker_result['tew_id'] <= 0 ||
            !isset($worker_result['tew_skills']) || $worker_result['tew_skills'] <= 0)
        {
            $this->exportData('数据异常,请稍后重试');
        }

        //传参是否已经存储成功
        $orders_count = $this->orders_dao->countData(array('t_id' => $worker_result['t_id'], 'u_id' => $worker_result['t_author'], 'o_worker' => $data['o_worker'], 'tew_id' => $worker_result['tew_id'], 's_id' => $worker_result['tew_skills']));
        if ($orders_count > 0) $this->exportData('已经成功邀约，无需再次邀约。');
        //已成单数量判断
        $orders_count = $this->orders_dao->countData(array('u_id' => $worker_result['t_author'], 't_id' => $worker_result['t_id'], 'tew_id' => $worker_result['tew_id'], 's_id' => $worker_result['tew_skills'], 'where' => 'o_confirm > 0 and o_status in (0,1)'));
        if ($orders_count >= $worker_result['tew_worker_num']) $this->exportData('来晚咯，已经被人捷足先登。');

        $curtime = time();
        $result = $this->orders_dao->addData(array(
            't_id' => $worker_result['t_id'],
            'u_id' => $worker_result['t_author'],
            'o_worker' => $data['o_worker'],
            'o_amount' => $worker_result['tew_price'],
            'o_in_time' => $curtime,
            'o_last_edit_time' => $curtime,
            'tew_id' => $worker_result['tew_id'],
            's_id' => $worker_result['tew_skills'],
            'o_sponsor' => $data['o_sponsor'],
        ));
        if (!$result)
        {
            $this->exportData('failure');
        }

        //成单即更改订单状态为洽谈中
        $task_dao = new \WDAO\Tasks();
        $task_dao->updateData(array(
            't_status' => 1
        ),array(
            't_id' => $worker_result['t_id'],
            't_status' => 0,
        ));

        $this->exportData('success');
    }

    /**
     * 订单雇主改价
     */
    private function price()
    {
        //$this->db->debug = 1;
        $data = $tmp = $task_param = array();
        //任务工人关系id
        if (isset($_REQUEST['tew_id']) && intval($_REQUEST['tew_id']) > 0) $data['tew_id'] = $task_param['tew_id'] = intval($_REQUEST['tew_id']);
        //任务id
        if (isset($_REQUEST['t_id']) && intval($_REQUEST['t_id']) > 0) $tmp['t_id'] = intval($_REQUEST['t_id']);
        //雇主id
        if (isset($_REQUEST['t_author']) && intval($_REQUEST['t_author']) > 0) $data['t_author'] = intval($_REQUEST['t_author']);
        //传递单价
        if (isset($_REQUEST['amount']) && intval($_REQUEST['amount']) > 0) $data['tew_price'] = floatval($_REQUEST['amount']);
        //工人数量
        if (isset($_REQUEST['worker_num']) && intval($_REQUEST['worker_num']) > 0) $data['worker_num'] = intval($_REQUEST['worker_num']);
        //开始时间
        if (isset($_REQUEST['start_time']) && intval($_REQUEST['start_time']) > 0) $data['start_time'] = strtotime($_REQUEST['start_time']);
        //结束时间
        if (isset($_REQUEST['end_time']) && intval($_REQUEST['end_time']) > 0) $data['end_time'] = strtotime($_REQUEST['end_time']);
        //工人id
        if (isset($_REQUEST['o_worker']) && intval($_REQUEST['o_worker']) > 0) $data['o_worker'] = intval($_REQUEST['o_worker']);
        //print_r($data);exit;
        if (!empty($data) && isset($data['tew_id']) && $data['tew_id'] > 0 &&
            isset($tmp['t_id']) && $tmp['t_id'] > 0 &&
            isset($data['t_author']) && $data['t_author'] > 0 &&
            isset($data['tew_price']) && $data['tew_price'] > 0 &&
            isset($data['worker_num']) && $data['worker_num'] > 0 &&
            isset($data['o_worker']) && $data['o_worker'] > 0)
        {
            //print_r($data);exit;
            //1：根据条件 获取该条订单信息
            $task_dao = new \WDAO\Task_ext_worker();
            $task_param['join'] = array('tasks', 'tasks.t_id = task_ext_worker.t_id');
            $task_param['where'] = ' task_ext_worker.t_id = "' . $tmp['t_id'] . '" and task_ext_worker.tew_id = "' . $data['tew_id'] . '" and tasks.t_author = "' . $data['t_author'] . '"';
            $task_param['pager'] = 0;
            $task_param['fields'] = 'tasks.*, task_ext_worker.*';
            $task_data = $task_dao->listData($task_param);
            if (!empty($task_data['data'][0]))
            {
                $task_data = $task_data['data'][0];
                $orders_data = $this->orders_dao->listData(array(
                    'tew_id' => $data['tew_id'],
                    't_id' => $tmp['t_id'],
                    'u_id' => $data['t_author'],
                    's_id' => $task_data['tew_skills'],
                    'pager' => 0,
                ));

                if (empty($orders_data['data']))
                {
                    $this->exportData('数据异常');
                }

                $tmp['workers'] = $tmp['confirm'] = array();
                foreach ($orders_data['data'] as $key => $val)
                {
                    if (isset($val['o_worker']) && $val['o_worker'] > 0)
                    {
                        $tmp['workers'][$key] = $val['o_worker'];
                    }
                    if (isset($val['o_confirm']) && $val['o_confirm'] == 1)
                    {
                        $tmp['confirm'][$key] = $val['confirm'];
                    }
                    if (isset($val['o_amount']) && $val['o_worker'] == $data['o_worker'] &&
                        isset($val['tew_id']) && $data['tew_id'] == $val['tew_id'] &&
                        isset($val['t_id']) && $tmp['t_id'] == $val['t_id'])
                    {
                        $tmp['original_amount'] = $val['o_amount'];
                    }
                }
                unset($key, $val);

                if (!in_array($data['o_worker'], $tmp['workers']) || !isset($tmp['original_amount']))
                {
                    $this->exportData('数据异常');
                }
                if (count($tmp['confirm']) > 0 && $data['worker_num'] < count($tmp['confirm']) + 1)
                {
                    $this->exportData('已有工人开工，不能减少工人数量');
                }

                //2：将取出来的信息 与 要改的信息做比对 得出差额
                //改价前后差价 = 原价 - 改后价
                $tmp['agio'] = $tmp['original_amount'] - $data['tew_price'];
                if ($tmp['agio'] < 0)
                {
                    $user_funds_dao = new \WDAO\Users_ext_funds(array('table' => 'Users_ext_funds'));
                    $user_funds_data = $user_funds_dao->infoData($data['t_author']);
                    if (empty($user_funds_data) || (isset($user_funds_data['overage']) && $user_funds_data['overage'] < (-1 * $tmp['agio'])))
                    {
                        $this->exportData('余额不足，请充值');
                    }
                }

                $this->db->start();
                //3：改数据库中的数据
                //任务更新改价次数及总任务改后价
                $tmp['edit_amount'] = ($data['tew_price'] * $data['worker_num'] * ceil(($data['end_time'] - $data['start_time']) / 3600 / 24));
                $times_update = $task_dao->queryData('update tasks set t_amount_edit_times=t_amount_edit_times+1, t_amount=t_amount + ' . ($tmp['edit_amount'] * -1) . ', t_last_edit_time = ' . time() . ', t_last_editor = ' . $data['t_author'] . ' where t_id = "' . $tmp['t_id'] . '"');

                //更改该工人的订单价格
                $order_info = $this->orders_dao->listData(array(
                    't_id' => $tmp['t_id'],
                    'tew_id' => $data['tew_id'],
                    'o_worker' => $data['o_worker'],
                    's_id' => $task_data['tew_skills'],
                    'o_status' => 0,
                    'pager' => 0,
                ));

                $orders_update = false;
                if (!empty($order_info['data'][0]))
                {
                    $orders_update = $this->orders_dao->updateData(array(
                        'o_amount' => $data['tew_price'],
                        'o_confirm' => 0,
                        'o_last_edit_time' => time(),
                        'o_start_time' => $data['start_time'],
                        'o_end_time' => $data['end_time'],
                    ),array(
                        'o_id' => $order_info['data'][0]['o_id'],
                    ));
                }

                //4：多退少补 操作平台与用户资金
                $user_funds_result = $this->userFunds($data['t_author'], $tmp['edit_amount'], $type = 'changeprice'); //用户资金
                $platform_funds_result = $this->platformFundsLog($tmp['t_id'], (-1 * $tmp['edit_amount']), 3, 'changeprice');     //平台资金日志

                if ($times_update && $orders_update && $user_funds_result && $platform_funds_result)
                {
                    $this->db->commit();
                    $this->exportData('success');
                }
                else
                {
                    $this->db->rollback();
                }
            }
        }
        $this->exportData('failure');
    }

    /**
     * 解雇工人或工人辞职
     */
    private function unbind()
    {
        $data = array();
        //任务工人关系id
        if (isset($_REQUEST['tew_id']) && intval($_REQUEST['tew_id']) > 0) $data['tew_id'] = intval($_REQUEST['tew_id']);
        //任务id
        if (isset($_REQUEST['t_id']) && intval($_REQUEST['t_id']) > 0) $data['t_id'] = intval($_REQUEST['t_id']);
        //方式 0解雇工人 1工人辞职
        $tmp['type'] = (isset($_REQUEST['type']) && in_array(trim($_REQUEST['type']), array('fire', 'resign'))) ? $_REQUEST['type'] : '';
        if ('' == $tmp['type']) $this->exportData('参数错误');
        //工人id
        if (isset($_REQUEST['o_worker']) && intval($_REQUEST['o_worker']) > 0) $data['o_worker'] = intval($_REQUEST['o_worker']);
        //雇主id
        if (isset($_REQUEST['u_id']) && intval($_REQUEST['u_id']) > 0) $data['u_id'] = intval($_REQUEST['u_id']);
        //技能id
        if (isset($_REQUEST['s_id']) && intval($_REQUEST['s_id']) > 0) $data['s_id'] = intval($_REQUEST['s_id']);
        //订单id
        if (isset($_REQUEST['o_id']) && intval($_REQUEST['o_id']) > 0) $data['o_id'] = intval($_REQUEST['o_id']);
        //星级
        if (isset($_REQUEST['start']) && intval($_REQUEST['start']) >= 0) $tmp['start'] = intval($_REQUEST['start']);
        //评价内容
        if (isset($_REQUEST['appraisal']) && trim($_REQUEST['appraisal']) != '') $tmp['appraisal'] = trim($_REQUEST['appraisal']);

        if (!empty($data) && isset($data['tew_id']) && isset($data['t_id']) && isset($data['o_worker']) && isset($data['s_id']) && isset($data['u_id']))
        {
            //根据参数获取订单信息
            $order_param = $data;
            $order_param['pager'] = 0;
            $order_data = $this->orders_dao->listData($order_param);
            unset($order_param);
            //比对订单数量
            if (count($order_data['data']) < 1)
            {
                $this->exportData('订单不存在');
            }
            $data['o_id'] = isset($order_data['data'][0]['o_id']) ? $order_data['data'][0]['o_id'] : 0; //赋值订单id
            if ($data['o_id'] < 1)
            {
                $this->exportData('数据异常');
            }

            //更新订单状态
            $result = $this->orders_dao->updateData(array('o_status' => ($tmp['type'] == 'fire') ? -2 : -1, 'unbind_time' => time()), $data);
            if (!$result)
            {
                $this->exportData('failure');
            }

            //释放工人
            $user_dao = new \WDAO\Users(array('table' => 'users'));
            $user_dao->updateData(array('u_task_status' => 0), array('u_id' => $data['o_worker']));

            //变更任务状态
            $tasks_dao = new \WDAO\Tasks();
            $tasks_dao->resetTaskToWait($data['t_id']);

            if (!empty($tmp)) //添加评价
            {
                $comment_dao = new \WDAO\Task_comment();
                $comment_dao->addComment(array(
                    't_id' => $data['t_id'],
                    'u_id' => ($tmp['type'] == 'fire') ? $data['u_id'] : $data['o_worker'],
                    'tc_u_id' => ($tmp['type'] == 'fire') ? $data['o_worker'] : $data['u_id'],
                    'start' => $tmp['start'],
                    'desc' => $tmp['appraisal']
                ));
            }

            $this->exportData('success');
        }
        $this->exportData('数据异常');
    }

    /**
     * 取消订单
     */
    private function cancel()
    {
        $data = $task_param = array();
        if (isset($_REQUEST['o_id']) && intval($_REQUEST['o_id']) > 0) $data['o_id'] = $task_param['o_id'] = intval($_REQUEST['o_id']);
        if (isset($_REQUEST['tew_id']) && intval($_REQUEST['tew_id']) > 0) $data['tew_id'] = $task_param['tew_id'] = intval($_REQUEST['tew_id']);
        if (isset($_REQUEST['t_id']) && intval($_REQUEST['t_id']) > 0) $data['t_id'] = $task_param['t_id'] = intval($_REQUEST['t_id']);
        if (isset($_REQUEST['u_id']) && intval($_REQUEST['u_id']) > 0) $data['u_id'] = $task_param['t_author'] = intval($_REQUEST['u_id']);
        if (isset($_REQUEST['o_worker']) && intval($_REQUEST['o_worker']) > 0) $data['o_worker'] = $task_param['tewo_worker'] = intval($_REQUEST['o_worker']);
        if (isset($_REQUEST['s_id']) && intval($_REQUEST['s_id']) > 0) $data['s_id'] = $task_param['s_id'] = intval($_REQUEST['s_id']);
        if (isset($_REQUEST['o_confirm']) && is_numeric($_REQUEST['o_confirm'])) $data['o_confirm'] = intval($_REQUEST['o_confirm']);
        if (isset($_REQUEST['o_status']) && is_numeric($_REQUEST['o_status'])) $data['o_status'] = intval($_REQUEST['o_status']);

        if (!empty($data) && (isset($data['o_id']) || (isset($data['tew_id']) && isset($data['t_id']) && isset($data['u_id']) && isset($data['o_worker']) && isset($data['s_id']))))
        {
            if (!isset($data['o_id']) || $data['o_id'] < 0)
            {
                $data['pager'] = 0;
                $data['limit'] = 1;
                $data['order'] = 'o_id desc';
                $orders = $this->orders_dao->listData($data);
                if (!empty($orders['data'][0]))
                {
                    $data['o_id'] = $orders['data'][0]['o_id'];
                }
            }

            $result = $this->orders_dao->updateData(array('o_status' => -4), $data);
            if ($result)
            {
                //任务状态变更
                $tasks_dao = new \WDAO\Tasks();
                $tid = isset($data['t_id']) ? $data['t_id'] : $orders['data'][0]['t_id'];
                $tasks_dao->resetTaskToWait($tid);

                $this->exportData('success');
            }
        }
        $this->exportData('failure');
    }

    /**
     * 订单支付
     */
    private function payout()
    {
        $data = array();
        //任务工人关系id 即单个工种的id
        if (isset($_REQUEST['tew_id']) && intval($_REQUEST['tew_id']) > 0) $data['tew_id'] = intval($_REQUEST['tew_id']);
        //任务id
        if (isset($_REQUEST['t_id']) && intval($_REQUEST['t_id']) > 0) $data['t_id'] = intval($_REQUEST['t_id']);
        //雇主id
        if (isset($_REQUEST['t_author']) && intval($_REQUEST['t_author']) > 0) $data['t_author'] = intval($_REQUEST['t_author']);

        if (!empty($data) && isset($data['t_author']) && isset($data['t_id']) && isset($data['tew_id']))
        {
            //先获取任务信息
            $task_dao = new \WDAO\Tasks();
            $data['pager'] = 0;
            $task_data = $task_dao->listData($data);
            if (!empty($task_data['data'][0]))
            {
                $order_param = array();
                //获取该任务所属的全部订单信息
                $order_param['where'] = 'orders.o_confirm = 1';
                $order_param['join'] = array('task_ext_worker', 'task_ext_worker.tew_id = orders.tew_id and task_ext_worker.tew_skills = orders.s_id and task_ext_worker.t_id = orders.t_id');
                $order_param['fields'] = 'task_ext_worker.tew_id, task_ext_worker.tew_skills, task_ext_worker.tew_worker_num, task_ext_worker.tew_price, task_ext_worker.tew_start_time, task_ext_worker.tew_end_time,
                orders.o_id, orders.t_id, orders.u_id, orders.o_worker, orders.o_amount, orders.o_in_time, orders.o_status, orders.o_pay, orders.unbind_time';
                $order_param['where'] .= ' and orders.t_id = "' . intval($data['t_id']) . '" and orders.u_id = "' . $data['t_author'] . '"';
                if (isset($data['tew_id']))
                {
                    $order_param['where'] .= ' and orders.tew_id = "' . $data['tew_id'] . '"';
                }
                $order_param['pager'] = 0;
                $orders_data = $this->orders_dao->listData($order_param);
                if (!empty($orders_data['data']))
                {
                    $pay_status = 1;
                    $this->db->start();
                    foreach ($orders_data['data'] as $key => $val)
                    {
                        if (!empty($val) && isset($val['o_id']) && $val['o_id'] > 0 &&
                            isset($val['o_amount']) && $val['o_amount'] > 0 &&
                            isset($val['o_worker']) && $val['o_worker'] > 0 &&
                            isset($val['o_status']) && in_array($val['o_status'], array(0, -1, -2)) &&
                            isset($val['o_confirm']) && $val['o_confirm'] == 1 &&
                            isset($val['o_pay']) && $val['o_pay'] == 0)
                        {
                            //解决辞职或解雇的工人价格
                            if ($val['o_status'] == -1) //辞职
                            {
                                //计算真实单价 o_amount / (ceil($val['tew_end_time'] - $val['tew_start_time']) / 3600 / 24 + 1)
                                $real_unit = $val['o_amount'] / (ceil($val['tew_end_time'] - $val['tew_start_time']) / 3600 / 24 + 1);
                                //实际工作天数
                                $real_days = (ceil($val['unbind_time'] - $val['tew_start_time']) / 3600 / 24);
                                $real_total = $real_unit * $real_days;
                                if ($val['o_amount'] > $real_total)
                                {
                                    $platform_result = $this->platformFundsLog($val['o_id'], (($val['o_amount'] - $real_total) * -1), 0, 'payorder'); //平台资金支出
                                    $user_funds_result = $this->userFunds($val['o_worker'], ($val['o_amount'] - $real_total), 'overage'); //雇主用户资金收入
                                    if (!$platform_result || !$user_funds_result)
                                    {
                                        $pay_status = 0;
                                    }
                                    $val['o_amount'] = $real_total;
                                }
                            }
                            if ($val['o_status'] == -2) //解雇
                            {
                                //计算真实单价 o_amount / (ceil($val['tew_end_time'] - $val['tew_start_time']) / 3600 / 24 + 1)
                                $real_unit = $val['o_amount'] / (ceil($val['tew_end_time'] - $val['tew_start_time']) / 3600 / 24 + 1);
                                //实际工作天数
                                $real_days = (ceil($val['unbind_time'] - $val['tew_start_time']) / 3600 / 24 + 1);
                                $real_total = $real_unit * $real_days;
                                if ($val['o_amount'] > $real_total)
                                {
                                    $platform_result = $this->platformFundsLog($val['o_id'], (($val['o_amount'] - $real_total) * -1), 0, 'payorder'); //平台资金支出
                                    $user_funds_result = $this->userFunds($val['o_worker'], ($val['o_amount'] - $real_total), 'overage'); //雇主用户资金收入
                                    if (!$platform_result || !$user_funds_result)
                                    {
                                        $pay_status = 0;
                                    }
                                    $val['o_amount'] = $real_total;
                                }
                            }

                            if ($pay_status != 0)
                            {
                                //给每个工人单独发钱并单独扣除平台款项
                                $platform_result = $user_result = 0;
                                $platform_result = $this->platformFundsLog($val['o_id'], ($val['o_amount'] * -1), 0, 'payorder'); //平台资金支出
                                $user_funds_result = $this->userFunds($val['o_worker'], $val['o_amount'], 'overage'); //工人用户资金收入
                                $user_dao = new \WDAO\Users(array('table' => 'users'));
                                $user_result = $user_dao->taskStatus($val['o_worker'], '0'); //释放工人状态
                                $pay = $this->orders_dao->payStatus($val['o_id'], '1'); //更新订单支付状态
                                if (!$platform_result || !$user_funds_result || !$user_result || !$pay) {
                                    $pay_status = 0;
                                }
                            }
                        }
                    }
                    unset($key, $val);

                    if ($pay_status == 1)
                    {
                        $this->db->commit();
                        $this->exportData('success');
                    }
                    else
                    {
                        $this->db->rollback();
                    }
                }
            }
        }
        $this->exportData('failure');
    }

}