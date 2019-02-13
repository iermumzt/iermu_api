<?php

!defined('IN_API') && exit('Access Denied');

class storecontrol extends base {

    function __construct() {
        $this->storecontrol();
    }

    function storecontrol() {
        parent::__construct();
        $this->load('app');
        $this->load('oauth2');
        $this->load('user');
        $this->load('device');
        $this->load('store');
    }

    function onindex() {
        $this->init_input();
        $model = $this->input('model');
        $method = $this->input('method');
        $action = 'on'.$model.'_'.$method;
        if($model && $method && method_exists($this, $action)) {
            unset($this->input['model']);
            unset($this->input['method']);
            return $this->$action();
        }
        $this->error(API_HTTP_BAD_REQUEST, API_ERROR_INVALID_REQUEST);
    }

    function onplan_listcvrplan() {
        $this->init_input();
        $connect_type = $this->input('connect_type');
        if (!$connect_type)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $result = $_ENV['store']->list_cvr_plan($connect_type);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_LIST_CVR_PLAN_FAILED);

        return $result;
    }

    function onorder_create() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $item = stripcslashes(rawurldecode($this->input('item')));
        if($item) {
            $item = json_decode($item, true);
        }

        if (!$item || !is_array($item))
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $list = array();
        foreach ($item as $value) {
            if ($value) {
                $deviceid = $value['deviceid'];
                $planid = $value['planid'];
                $num = $value['num'];
                if (!$deviceid || !$planid || !$num)
                    $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

                $device = $_ENV['device']->get_device_by_did($deviceid);
                if (!$device)
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

                if ($device['uid'] != $uid)
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

                $cvr_plan = $_ENV['store']->get_cvr_plan_by_planid($planid);
                if (!$cvr_plan)
                    $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_CVR_PLAN_NOT_EXIST);

                if ($cvr_plan['status'] != 1)
                    $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_CVR_PLAN_INVALID);

                if ($device['connect_type'] != $cvr_plan['connect_type'])
                    $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_CVR_PLAN_NOT_MATCH);
                
                // 检查设备是否已存在永久免费状态
                if($_ENV['device']->check_cvr_free($deviceid, $cvr_plan['connect_type'], $cvr_plan['cvr_type'], $cvr_plan['cvr_day']))
                    $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_CVR_ALREADY_FREE);

                // 检查是否有未支付的订单(避免订单云服务日期累加问题)
                if (!$_ENV['store']->check_order_access($uid, $deviceid))
                    $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_CREATE_ORDER_NOT_ALLOWED);

                $order_item = $_ENV['store']->create_order_item($device, $cvr_plan, $num);
                if (!$order_item)
                    $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_CREATE_ORDER_ITEM_FAILED);

                $list[] = $order_item;
            }
        }

        $order = $_ENV['store']->create_order($uid, $list);
        if (!$order)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_CREATE_ORDER_FAILED);

        $order['item'] = $list;

        $_ENV['store']->order_action('CREATE_ORDER', $order);

        return $order;
    }

    function onorder_usecoupon() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $deviceid = $this->input('deviceid');
        $couponno = $this->input('couponno');
        if (!$deviceid || !$couponno)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $device = $_ENV['device']->get_device_by_did($deviceid);
        if (!$device)
            $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

        if ($device['uid'] != $uid)
            $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

        $coupon = $_ENV['store']->get_coupon_by_couponno($couponno);
        if (!$coupon)
            $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_COUPON_NOT_EXIST);

        if ($coupon['coupontype'] != 1)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_COUPON_INVALID);

        if ($coupon['uid'] != $uid)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_COUPON_NO_AUTH);

        if ($coupon['status'] != 1)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_COUPON_DISABLED);

        if (!$coupon['type'] || $coupon['type']['expiretime'] < $this->time)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_COUPON_EXPIRED);

        if (!$coupon['type']['item'])
            $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_CVR_PLAN_NOT_EXIST);

        if ($coupon['type']['item']['status'] != 1)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_CVR_PLAN_INVALID);

        if ($coupon['type']['item']['connect_type'] != $device['connect_type'])
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_CVR_PLAN_NOT_MATCH);

        $result = $_ENV['store']->use_coupon($device, $coupon);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_USE_COUPON_FAILED);

        return $result;
    }

    function onorder_confirm() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $ordersn = $this->input('ordersn');
        $rid = $this->input('rid');
        if (!$ordersn || !$rid)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $order = $_ENV['store']->get_order_by_ordersn($ordersn);
        if (!$order)
            $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_ORDER_NOT_EXIST);

        if ($order['uid'] != $uid)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_NO_AUTH);

        if ($order['orderstatus'] != 0)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_INVALID);

        $rid = explode(',', $rid);
        $list = array();
        foreach ($rid as $value) {
            if($value) {
                $order_item = $_ENV['store']->get_order_item_by_rid($value);
                if (!$order_item) 
                    $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_ORDER_ITEM_NOT_EXIST);

                if ($order_item['orderid'] != $order['orderid'])
                    $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_ITEM_INVALID);

                if (!$order_item['item'])
                    $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_CVR_PLAN_NOT_EXIST);

                if ($order_item['item']['status'] != 1)
                    $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_CVR_PLAN_INVALID);

                $device = $_ENV['device']->get_device_by_did($order_item['deviceid']);
                if (!$device)
                    $this->error(API_HTTP_NOT_FOUND, DEVICE_ERROR_NOT_EXIST);

                if ($device['uid'] != $uid)
                    $this->error(API_HTTP_FORBIDDEN, DEVICE_ERROR_NO_AUTH);

                if ($order_item['item']['connect_type'] != $device['connect_type'])
                    $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_CVR_PLAN_NOT_MATCH);

                $list[] = $order_item;
            }
        }

        $couponno = $this->input('couponno');
        if ($couponno === NULL || $couponno === '') {
            $coupon = NULL;
        } else {
            $coupon = $_ENV['store']->get_coupon_by_couponno($couponno);
            if (!$coupon)
                $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_COUPON_NOT_EXIST);

            if ($coupon['coupontype'] != 0)
                $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_COUPON_INVALID);

            if ($coupon['uid'] != $uid)
                $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_COUPON_NO_AUTH);

            if ($coupon['status'] != 1)
                $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_COUPON_DISABLED);

            if (!$coupon['type'] || $coupon['type']['expiretime'] < $this->time)
                $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_COUPON_EXPIRED);
        }

        $order = $_ENV['store']->confirm_order($order, $list, $coupon);
        if (!$order)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_CONFIRM_ORDER_FAILED);

        $order['item'] = $list;

        $_ENV['store']->order_action('CONFIRM_ORDER', $order);

        return $order;
    }

    function onorder_list() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();
        
        // 支付状态
        $paystatus = $this->input('paystatus');
        $paystatus = ($paystatus === NULL || $paystatus === '') ? -1 : (intval($paystatus) > 0 ? 1 : 0);
        
        // 能否开发票
        $need_invoice = $this->input('need_invoice');
        $need_invoice = ($need_invoice === NULL || $need_invoice === '') ? -1 : (intval($need_invoice) > 0 ? 1 : 0);
        
        // 发票状态
        $invoicestatus = $this->input('invoicestatus');
        $invoicestatus = ($invoicestatus === NULL || $invoicestatus === '') ? -1 : (intval($invoicestatus) > 0 ? 1 : 0);
        
        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        if(!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['store']->list_order($uid, $paystatus, $need_invoice, $invoicestatus, $list_type, $page, $count);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_LIST_ORDER_FAILED);

        return $result;
    }

    function onorder_info() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $ordersn = $this->input['ordersn'];
        if (!$ordersn) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $order = $_ENV['store']->get_order_by_ordersn($ordersn);
        if (!$order)
            $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_ORDER_NOT_EXIST);

        if ($order['uid'] != $uid)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_NO_AUTH);

        $order_item = $_ENV['store']->get_order_item_by_orderid($order['orderid']);
        if (!$order_item)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_ITEM_NOT_EXIST);

         $order['item'] = $order_item;

        return $order;
    }

    function onorder_drop() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $ordersn = $this->input('ordersn');
        if (!$ordersn)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $order = $_ENV['store']->get_order_by_ordersn($ordersn);
        if (!$order)
            $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_ORDER_NOT_EXIST);

        if ($order['uid'] != $uid)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_NO_AUTH);

        if ($order['orderstatus'] != 1)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_INVALID);

        if (!$_ENV['store']->drop_order_by_orderid($order['orderid']))
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_DROP_ORDER_FAILED);

        $result = array('ordersn' => $ordersn);

        $order['orderstatus'] = '-1';
        $_ENV['store']->order_action('DROP_ORDER', $order);

        return $result;
    }

    function oninvoice_create() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $ordersn = $this->input('ordersn');
        $company = $this->input('company');
        $country = $this->input('country');
        $province = $this->input('province');
        $city = $this->input('city');
        $district = $this->input('district');
        $street = $this->input('street');
        $address = $this->input('address');
        $zipcode = $this->input('zipcode');
        $consignee = $this->input('consignee');
        $mobile = $this->input('mobile');
        $usci = $this->input('usci');
        if (!$ordersn || !$country || !$province || !$city || !$district || !$street || !$address || !$consignee || !$mobile)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $order = $_ENV['store']->get_order_by_ordersn($ordersn);
        if (!$order)
            $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_ORDER_NOT_EXIST);

        if ($order['uid'] != $uid)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_NO_AUTH);

        if ($order['orderstatus'] != 1)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_INVALID);

        if ($order['paystatus'] != 1)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_NOT_PAID);

        if ($order['invoicestatus'] != 0)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_INVOICE_ALREADY_EXIST);

        $result = $_ENV['store']->create_invoice($order['orderid'], $company, $country, $province, $city, $district, $street, $address, $zipcode, $consignee, $mobile, $usci);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_CREATE_INVOICE_FAILED);

        $order['invoicestatus'] = '1';
        $_ENV['store']->order_action('CREATE_INVOICE', $order);

        return $result;
    }

    function oncoupon_list() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        if(!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['store']->list_coupon($uid, $list_type, $page, $count);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_LIST_COUPON_FAILED);

        return $result;
    }

    function oncoupon_bind() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $couponno = $this->input('couponno');
        if (!$couponno)
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $coupon = $_ENV['store']->get_coupon_by_couponno($couponno);
        if (!$coupon)
            $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_COUPON_NOT_EXIST);

        if ($coupon['status'] != 0)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_COUPON_DISABLED);

        if (!$coupon['type'] || $coupon['type']['expiretime'] < $this->time)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_COUPON_EXPIRED);

        $result = $_ENV['store']->bind_coupon($uid, $coupon['couponid']);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_ADD_COUPON_FAILED);

        return $result;
    }

    function oncoupon_record() {
        $this->init_user();
        $uid = $this->uid;
        if (!$uid)
            $this->user_error();

        $this->init_input();
        $list_type = $this->input('list_type');
        $page = intval($this->input('page'));
        $count = intval($this->input('count'));

        if(!$list_type || !in_array($list_type, array('all', 'page'))) {
            $list_type = 'all';
        }

        $page = $page > 0 ? $page : 1;
        $count = $count > 0 ? $count : 10;

        $result = $_ENV['store']->coupon_record($uid, $list_type, $page, $count);
        if (!$result)
            $this->error(API_HTTP_SERVICE_UNAVAILABLE, STORE_ERROR_COUPON_RECORD_FAILED);

        return $result;
    }

    function onpayment_pay() {
        $this->init_user();
        $uid = $this->uid;
        if(!$uid)
            $this->user_error();

        $this->init_input();
        $ordersn = $this->input['ordersn'];
        if (!$ordersn) 
            $this->error(API_HTTP_BAD_REQUEST, API_ERROR_PARAM);

        $redirect = $this->input('redirect');
        if (!$redirect)
            $redirect = $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : API_DEFAULT_REDIRECT;

        $order = $_ENV['store']->get_order_by_ordersn($ordersn);
        if (!$order)
            $this->error(API_HTTP_NOT_FOUND, STORE_ERROR_ORDER_NOT_EXIST);

        if ($order['uid'] != $uid)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_NO_AUTH);

        if ($order['orderstatus'] != 1)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_INVALID);

        if ($order['paystatus'] != 0)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_ALREADY_PAID);

        $result = $_ENV['store']->payment($order, 1, $redirect);
        if (!$result)
            $this->error(API_HTTP_FORBIDDEN, STORE_ERROR_ORDER_PAYMENT_FAILED);

        $_ENV['store']->order_action('PAYMENT_WAIT', $order);

        return $result;
    }
}