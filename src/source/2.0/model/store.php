<?php

!defined('IN_API') && exit('Access Denied');

class storemodel {

    var $db;
    var $base;

    function __construct(&$base) {
        $this->storemodel($base);
    }

    function storemodel(&$base) {
        $this->base = $base;
        $this->db = $base->db;
        $this->base->load('device');
    }

    function _ordersn() {
        list($usec, $sec) = explode(" ", microtime());
        $usec = substr(str_replace('0.', '', $usec), 0 ,4);
        $str  = rand(10,99);
        return date("YmdHis").$usec.$str;
    }

    function get_order_by_ordersn($ordersn) {
        $order = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'store_order WHERE ordersn="'.$ordersn.'"');
        if ($order['couponid']) {
            $order['coupon'] = $this->get_coupon_by_couponid($order['couponid']);
        }
        if ($order['invoiceid']) {
            $order['invoice'] = $this->get_invoice_by_invoiceid($order['invoiceid']);
        }

        return $order;
    }

    function get_order_by_orderid($orderid) {
        $order = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'store_order WHERE orderid="'.$orderid.'"');
        if ($order['couponid']) {
            $order['coupon'] = $this->get_coupon_by_couponid($order['couponid']);
        }
        if ($order['invoiceid']) {
            $order['invoice'] = $this->get_invoice_by_invoiceid($order['invoiceid']);
        }

        return $order;
    }

    function get_cvr_plan_by_planid($planid) {
        return $this->db->fetch_first('SELECT planid,connect_type,name,cvr_day,cvr_type,plan_month,orig_price,price,discount,status FROM '.API_DBTABLEPRE.'store_cvr_plan WHERE planid="'.$planid.'"');
    }

    function get_order_item_by_rid($rid) {
        $order_item = $this->db->fetch_first('SELECT a.*,IFNULL(b.`desc`,"") AS description FROM '.API_DBTABLEPRE.'store_order_item a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid WHERE a.rid="'.$rid.'"');
        if ($order_item['deviceid']) {
            $device = $_ENV['device']->get_device_by_did($order_item['deviceid']);
            $order_item['thumbnail'] = $_ENV['device']->get_device_thumbnail($device);
        }
        if ($order_item['itemid']) {
            $order_item['item'] = $this->get_cvr_plan_by_planid($order_item['itemid']);
        }

        return $order_item;
    }

    function get_order_item_by_orderid($orderid) {
        $list = $this->db->fetch_all('SELECT a.*,IFNULL(b.`desc`,"") AS description FROM '.API_DBTABLEPRE.'store_order_item a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid WHERE a.orderid="'.$orderid.'"');
        for ($i = 0, $n = count($list); $i < $n; $i++) {
            if ($list[$i]['deviceid']) {
                $device = $_ENV['device']->get_device_by_did($list[$i]['deviceid']);
                $list[$i]['thumbnail'] = $_ENV['device']->get_device_thumbnail($device);
            }
            if ($list[$i]['itemid']) {
                if ($list[$i]['itemtype'] == 0) {
                    $list[$i]['item'] = $this->get_cvr_plan_by_planid($list[$i]['itemid']);
                } else {
                    $list[$i]['item'] = array();
                }
            }
        }

        return $list;
    }

    function get_coupon_type_by_typeid($typeid) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'store_coupon_type WHERE typeid="'.$typeid.'"');
    }

    function get_coupon_by_couponno($couponno) {
        $coupon = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'store_coupon WHERE couponno="'.$couponno.'"');
        if ($coupon['typeid']) {
            $coupon['type'] = $this->get_coupon_type_by_typeid($coupon['typeid']);
            if ($coupon['type']['itemid']) {
                $coupon['type']['item'] = $this->get_cvr_plan_by_planid($coupon['type']['itemid']);
            }
        }

        return $coupon;
    }

    function get_coupon_by_couponid($couponid) {
        $coupon = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'store_coupon WHERE couponid="'.$couponid.'"');
        if ($coupon['typeid']) {
            $coupon['type'] = $this->get_coupon_type_by_typeid($coupon['typeid']);
            if ($coupon['type']['itemid']) {
                $coupon['type']['item'] = $this->get_cvr_plan_by_planid($coupon['type']['itemid']);
            }
        }

        return $coupon;
    }

    function get_invoice_by_invoiceid($invoiceid) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'store_invoice WHERE invoiceid="'.$invoiceid.'"');
    }

    function get_pay_log_by_logid($logid) {
        return $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'pay_log WHERE logid="'.$logid.'"');
    }

    function list_cvr_plan($connect_type) {
        $list = $this->db->fetch_all('SELECT planid,connect_type,name,cvr_day,cvr_type,plan_month,orig_price,price,discount,status FROM '.API_DBTABLEPRE.'store_cvr_plan WHERE connect_type="'.$connect_type.'" AND status=1 ORDER BY displayorder');
        $result = array(
            'count' => count($list),
            'list' => $list
        );
        return $result;
    }

    function create_order_item($device, $cvr_plan, $num) {
        $record = $_ENV['device']->get_last_cvr_record($device);
        $starttime = $record ? $record['cvr_end_time'] : $this->base->time;
        
        // 开始收费事件：2016-04-05 00:00:00
        if($starttime < 1491321600) $starttime = 1491321600;

        $endtime = strtotime('+'.($cvr_plan['plan_month'] * $num).' month', $starttime);

        $orig_price = $cvr_plan['orig_price'];
        $price = $cvr_plan['price'];
        $discount = $cvr_plan['discount'];
        $orig_total = $orig_price * $num;
        $total_discount = $discount * $num;
        $total = $price * $num;

        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'store_order_item SET itemid="'.$cvr_plan['planid'].'",deviceid="'.$device['deviceid'].'",connect_type="'.$device['connect_type'].'",starttime="'.$starttime.'",endtime="'.$endtime.'",name="'.$cvr_plan['name'].'",orig_price="'.$orig_price.'",price="'.$price.'",discount="'.$discount.'",num="'.$num.'",orig_total="'.$orig_total.'",total_discount="'.$total_discount.'",total="'.$total.'"');

        $rid = $this->db->insert_id();

        return $this->get_order_item_by_rid($rid);
    }

    function create_order($uid, $list) {
        $ordersn = $this->_ordersn();

        $orig_total_fee = 0;
        $total_fee = 0;
        $item_discount = 0;
        foreach ($list as $item) {
            $orig_total_fee += $item['orig_total'];
            $total_fee += $item['total'];
            $item_discount += $item['total_discount'];
        }

        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'store_order SET ordersn="'.$ordersn.'",uid="'.$uid.'",createtime="'.$this->base->time.'",orig_total_fee="'.$orig_total_fee.'",total_fee="'.$total_fee.'",item_discount="'.$item_discount.'",total_discount="'.$item_discount.'",need_fee="'.$total_fee.'"');

        $orderid = $this->db->insert_id();
        foreach ($list as $item) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'store_order_item SET orderid="'.$orderid.'" WHERE rid="'.$item['rid'].'"');
        }

        return $this->get_order_by_orderid($orderid);
    }

    function use_coupon($device, $coupon) {
        $cvr_plan = $coupon['type']['item'];
        $num = floor($coupon['type']['money'] / $coupon['type']['item']['price']);
        $order_item = $this->create_order_item($device, $cvr_plan, $num);
        $list = array($order_item);
        $order = $this->create_order($device['uid'], $list);

        return $this->confirm_order($order, $list, $coupon);
    }

    function confirm_order($order, $list, $coupon) {
        $orderid = $order['orderid'];
        $this->db->query('UPDATE '.API_DBTABLEPRE.'store_order_item SET orderid="0" WHERE orderid="'.$orderid.'"');

        $orig_total_fee = 0;
        $total_fee = 0;
        $item_discount = 0;
        foreach ($list as $item) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'store_order_item SET orderid="'.$orderid.'" WHERE rid="'.$item['rid'].'"');

            $orig_total_fee += $item['orig_total'];
            $total_fee += $item['total'];
            $item_discount += $item['total_discount'];
        }

        $total_discount = $item_discount;
        $need_fee = $total_fee;
        $paid_fee = 0;
        $paystatus = 0;
        $sql = '';
        if ($coupon && $coupon['type'] && ($coupon['type']['coupontype'] == 0 && $coupon['type']['min_fee'] <= $need_fee || $coupon['type']['coupontype'] == 1)) {
            $this->db->query('UPDATE '.API_DBTABLEPRE.'store_coupon SET usetime="'.$this->base->time.'",orderid="'.$orderid.'",status="2" WHERE couponid="'.$coupon['couponid'].'"');
            
            // 优惠券金额
            $coupon_money = $coupon['type']['money'];
            if($coupon['type']['coupontype'] == 1) $coupon_money = $total_fee;
            
            $total_discount += $coupon_money;
            $need_fee -= $coupon_money;
            if($need_fee < 0) $need_fee = 0;
            $paid_fee += $coupon_money;
            if($need_fee == 0) $paystatus = 1;
            $sql = ',use_coupon="1",couponid="'.$coupon['couponid'].'",coupontype="'.$coupon['type']['coupontype'].'",coupon_money="'.$coupon_money.'",coupon_discount="'.$coupon_money.'"';
        }

        $this->db->query('UPDATE '.API_DBTABLEPRE.'store_order SET orderstatus="1",confirmtime="'.$this->base->time.'",orig_total_fee="'.$orig_total_fee.'",total_fee="'.$total_fee.'",item_discount="'.$item_discount.'"'.$sql.',total_discount="'.$total_discount.'",need_fee="'.$need_fee.'",paid_fee="'.$paid_fee.'",paystatus="'.$paystatus.'" WHERE orderid="'.$orderid.'"');
        
        if($paystatus == 1) {
            // 支付成功处理
            $ret = $this->pay_success($order);
            if(!$ret)
                return false;
        }

        return $this->get_order_by_orderid($orderid);
    }

    function list_order($uid, $paystatus, $need_invoice, $invoicestatus, $list_type, $page, $count) {
        $result = array();
        //三天未支付订单删除
        $this->drop_invalid_order($uid);
        $sql = API_DBTABLEPRE.'store_order WHERE uid="'.$uid.'" AND orderstatus="1"';

        if ($paystatus != -1) {
            $sql .= ' AND paystatus="'.$paystatus.'"';
        }
        
        if ($need_invoice != -1) {
            if($need_invoice) {
                $sql .= ' AND total_fee>total_discount';
            } else {
                $sql .= ' AND total_fee=total_discount';
            }
        }

        if ($invoicestatus != -1) {
            $sql .= ' AND invoicestatus="'.$invoicestatus.'"';
        }

        if ($list_type == 'page') {
            $total = $this->db->result_first('SELECT COUNT(*) AS total FROM '.$sql);
            $pages = $this->base->page_get_page($page, $count, $total);

            $sql .= ' ORDER BY orderid DESC LIMIT '.$pages['start'].','.$count;
            $result['page'] = $pages['page'];
        } else {
            $sql .= ' ORDER BY orderid DESC';
        }

        $order = $this->db->fetch_all('SELECT * FROM '.$sql);
        $n = count($order);
        for ($i = 0; $i < $n; $i++) { 
            $order[$i]['item'] = $this->get_order_item_by_orderid($order[$i]['orderid']);
            if ($order[$i]['couponid']) {
                $order[$i]['coupon'] = $this->get_coupon_by_couponid($order[$i]['couponid']);
            }
            if ($order[$i]['invoiceid']) {
                $order[$i]['invoice'] = $this->get_invoice_by_invoiceid($order[$i]['invoiceid']);
            }
        }

        $result['count'] = $n;
        $result['list'] = $order;

        return $result;
    }

    function drop_order_by_orderid($orderid) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'store_order SET orderstatus="-1" WHERE orderid="'.$orderid.'"');

        return true;
    }
    function drop_invalid_order($uid){
        $time = $this->base->time;
        $invalidtime = $time - 3*24*3600;
        $this->db->query('UPDATE '.API_DBTABLEPRE.'store_order SET orderstatus="-1" WHERE uid="'.$uid.'" AND paystatus="0" AND ordertype = "0" AND createtime< "'.$invalidtime.'"');

        return true;
    }

    function create_invoice($orderid, $company, $country, $province, $city, $district, $street, $address, $zipcode, $consignee, $mobile, $usci='') {
        $iscompany = $company ? 1 : 0;
        $title = $iscompany ? $company : $consignee;
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'store_invoice SET iscompany="'.$iscompany.'",title="'.$title.'",country="'.$country.'",province="'.$province.'",city="'.$city.'",district="'.$district.'",street="'.$street.'",address="'.$address.'",zipcode="'.$zipcode.'",consignee="'.$consignee.'",mobile="'.$mobile.'",createtime="'.$this->base->time.'",orderid="'.$orderid.'",usci="'.$usci.'"');

        $invoiceid = $this->db->insert_id();

        $this->db->query('UPDATE '.API_DBTABLEPRE.'store_order SET invoicestatus="1",invoiceid="'.$invoiceid.'",invoicetime="'.$this->base->time.'" WHERE orderid="'.$orderid.'"');

        return $this->get_invoice_by_invoiceid($invoiceid);
    }

    function list_coupon($uid, $list_type, $page, $count) {
        $result = array();
        $sql = API_DBTABLEPRE.'store_coupon WHERE uid="'.$uid.'"';

        if ($list_type == 'page') {
            $total = $this->db->result_first('SELECT COUNT(*) AS total FROM '.$sql);
            $pages = $this->base->page_get_page($page, $count, $total);

            $sql .= ' ORDER BY bindtime DESC LIMIT '.$pages['start'].','.$count;
            $result['page'] = $pages['page'];
        } else {
            $sql .= ' ORDER BY bindtime DESC';
        }

        $coupon = $this->db->fetch_all('SELECT * FROM '.$sql);
        $n = count($coupon);
        for ($i = 0; $i < $n; $i++) { 
            if ($coupon[$i]['typeid']) {
                $coupon[$i]['type'] = $this->get_coupon_type_by_typeid($coupon[$i]['typeid']);
                if ($coupon['type']['itemid']) {
                    $coupon['type']['item'] = $this->get_cvr_plan_by_planid($coupon['type']['itemid']);
                }
            }
        }

        $result['count'] = $n;
        $result['list'] = $coupon;

        return $result;
    }

    function bind_coupon($uid, $couponid) {
        $this->db->query('UPDATE '.API_DBTABLEPRE.'store_coupon SET uid="'.$uid.'",bindtime="'.$this->base->time.'",status="1" WHERE couponid="'.$couponid.'"');

        return $this->get_coupon_by_couponid($couponid);
    }

    function coupon_record($uid, $list_type, $page, $count) {
        $result = array();
        $sql = API_DBTABLEPRE.'store_coupon WHERE uid="'.$uid.'" AND orderid!="0"';

        if ($list_type == 'page') {
            $total = $this->db->result_first('SELECT COUNT(*) AS total FROM '.$sql);
            $pages = $this->base->page_get_page($page, $count, $total);

            $sql .= ' ORDER BY usetime DESC LIMIT '.$pages['start'].','.$count;
            $result['page'] = $pages['page'];
        } else {
            $sql .= ' ORDER BY usetime DESC';
        }

        $coupon = $this->db->fetch_all('SELECT * FROM '.$sql);
        $n = count($coupon);
        for ($i = 0; $i < $n; $i++) {
            $coupon[$i]['order'] = $this->get_order_by_orderid($coupon[$i]['orderid']);
            if ($coupon[$i]['typeid']) {
                $coupon[$i]['type'] = $this->get_coupon_type_by_typeid($coupon[$i]['typeid']);
                if ($coupon['type']['itemid']) {
                    $coupon['type']['item'] = $this->get_cvr_plan_by_planid($coupon['type']['itemid']);
                }
            }
        }

        $result['count'] = $n;
        $result['list'] = $coupon;

        return $result;
    }

    function payment($order, $payid, $redirect) {
        if (!$order || !$payid || !$redirect)
            return false;

        $pay = $this->base->load_pay($payid);
        if (!$pay)
            return false;

        $need_fee = sprintf('%.2f', $order['need_fee']);
        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'pay_log SET orderid="'.$order['orderid'].'",payid="'.$payid.'",order_fee="'.$need_fee.'",redirect="'.$redirect.'",dateline="'.$this->base->time.'",lastupdate="'.$this->base->time.'"');

        $logid = $this->db->insert_id();

        $ret = $pay->payment($order['ordersn'], '云录制服务', $need_fee, $logid);

        return $ret;
    }

    function payment_notify($order, $order_fee, $payid, $payno, $logid, $ispaid) {
        if (!$order || !$order_fee || !$payid || !$payno || !$logid)
            return false;
        
        $orderid = $order['orderid'];

        $pay = $this->base->load_pay($payid);
        if (!$pay)
            return false;

        if (!$pay->payment_notify())
            return false;

        $arr = $this->db->fetch_first('SELECT * FROM '.API_DBTABLEPRE.'pay_log WHERE logid="'.$logid.'"');
        if (!$arr || $arr['orderid'] != $order['orderid'] || $arr['payid'] != $payid)
            return false;

        $this->db->query('UPDATE '.API_DBTABLEPRE.'pay_log SET ispaid="'.$ispaid.'",order_fee="'.$order_fee.'",payno="'.$payno.'",lastupdate="'.$this->base->time.'" WHERE logid="'.$logid.'"');
        
        if ($ispaid) {
            $paid_fee = sprintf('%.2f', $order['paid_fee']) + $order_fee;
            $need_fee = sprintf('%.2f', $order['need_fee']) - $order_fee;
            if($need_fee < 0) $need_fee = 0;
            
            $paystatus = 0;
            if($need_fee == 0) $paystatus = 1;
            
            if($paystatus == 1) {
                // 支付成功处理
                if(!$this->pay_success($order) || !$this->update_cvr_info_by_orderid($order['orderid']))
                    return false;
            }
            
            $this->db->query('UPDATE '.API_DBTABLEPRE.'store_order SET paystatus="'.$paystatus.'",paytime="'.$this->base->time.'",paid_fee="'.$paid_fee.'",need_fee="'.$need_fee.'",payid="'.$payid.'",pay_type="'.$pay->pay_type.'",pay_name="'.$pay->pay_type.'",payno="'.$payno.'" WHERE orderid="'.$order['orderid'].'"');
        }
        
        return true;
    }

    function update_cvr_info_by_orderid($orderid) {
        $this->base->load('device');

        $list = $this->db->fetch_all('SELECT b.* FROM '.API_DBTABLEPRE.'store_order_item a LEFT JOIN '.API_DBTABLEPRE.'device b ON a.deviceid=b.deviceid WHERE a.orderid="'.$orderid.'" AND a.status="1"');
        foreach ($list as $value) {
            $_ENV['device']->update_cvr_info($value, true);
        }

        return true;
    }
    
    function pay_success($order) {
        if(!$order || !$order['orderid'])
            return false;
        
        $orderid = $order['orderid'];
        $cvr_type = $order['cvr_type'];
    
        // 云录制服务订单处理
        if($cvr_type == 0) {
            $items = $this->get_order_item_by_orderid($orderid);
            foreach($items as $item) {
                if($item['itemtype'] == 0) {
                    $deviceid = $item['deviceid'];
                    $plan = $item['item'];
                    $num = $item['num'];
                    $status = $item['status'];
                    if(!$status && $deviceid && $plan && $num) {
                        // 设备云录制时间处理
                        $ret = $_ENV['device']->add_cvr_plan($deviceid, $plan, $num);
                        if(!$ret || !$ret['cvr_end_time'])
                            return false;
                        
                        $this->db->query("UPDATE ".API_DBTABLEPRE."store_order_item SET starttime='".$ret['cvr_start_time']."', endtime='".$ret['cvr_end_time']."', status='1' WHERE rid='".$item['rid']."'");
                    }
                }
            }
        }

        return true;
    }

    function order_action($type, $order) {
        if (!$type || !$order)
            return false;

        $this->base->load('user');
        $user = $_ENV['user']->get_user_by_uid($order['uid']);

        switch ($type) {
            case 'CREATE_ORDER': $note = 'ordersn为'.$order['ordersn'].'的订单已生成'; break;
            case 'CONFIRM_ORDER': $note = 'ordersn为'.$order['ordersn'].'的订单已确认'; break;
            case 'DROP_ORDER': $note = 'ordersn为'.$order['ordersn'].'的订单已删除'; break;
            case 'PAYMENT_WAIT': $note = 'ordersn为'.$order['ordersn'].'的订单准备支付'; break;
            case 'PAYMENT_SUCCESS': $note = 'ordersn为'.$order['ordersn'].'的订单支付成功'; break;
            case 'CREATE_INVOICE': $note = 'ordersn为'.$order['ordersn'].'的订单已开发票'; break;
            default: $note = '操作类型未定义'; break;
        }

        $this->db->query('INSERT INTO '.API_DBTABLEPRE.'store_order_action SET orderid="'.$order['orderid'].'",uid="'.$user['uid'].'",username="'.$user['username'].'",orderstatus="'.$order['orderstatus'].'",paystatus="'.$order['paystatus'].'",shipstatus="'.$order['shipstatus'].'",invoicestatus="'.$order['invoicestatus'].'",type="'.$type.'",note="'.$note.'",dateline="'.$this->base->time.'"');

        return true;
    }

    function check_order_access($uid, $deviceid) {
        $total = $this->db->result_first('SELECT COUNT(*) AS total FROM '.API_DBTABLEPRE.'store_order_item a LEFT JOIN '.API_DBTABLEPRE.'store_order b ON a.orderid=b.orderid WHERE a.orderid!=0 AND a.deviceid="'.$deviceid.'" AND b.uid="'.$uid.'" AND b.orderstatus=1 AND b.paystatus=0');

        return !$total;
    }
}