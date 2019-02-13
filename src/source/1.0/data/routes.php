<?php 
/**
 * Route配置
 */

// 文件类型
router::parseExtensions('xml','json','php');

// passport
router::connect('/passport/users/getLoggedInUser', array('controller' => 'user', 'action' => 'getloginuser'), array('action'=>'[a-z0-9]+'));
router::connect('/passport/user', array('controller' => 'user', 'action' => getgpc('method')), array());

// web
router::connect('/web/page', array('action' => getgpc('method'), 'controller'=>'web'), array());

// home
router::connect('/home/user', array('controller' => 'home', 'action' => getgpc('method')), array());

// search
router::connect('/search/keyword', array('action' => getgpc('method'), 'controller'=>'search'), array());
router::connect('/search', array('action' => getgpc('type'), 'controller'=>'search'), array());

// ad
router::connect('/ad', array('action' => 'index', 'controller'=>'ad'), array());

// app
router::connect('/app/client', array('action' => getgpc('method'), 'controller'=>'app'), array());

// push
router::connect('/push/:model', array('controller' => 'push', 'action' => 'index'), array('model'=>'[a-z0-9]+'));

// oauth
router::connect('/oauth/2.0/connect/success', array('controller' => 'oauth2', 'action' => 'connect_success'));
router::connect('/oauth/2.0/:action', array('controller' => 'oauth2'), array('action'=>'[a-z0-9]+'));

// pcs  
router::connect('/pcs/:controller', array('action' => getgpc('method')), array('controller'=>'[a-z0-9]+'));

// log
router::connect('/log/client', array('controller' => 'log', 'action' => getgpc('method')), array());

// bsm
router::connect('/bsm/:controller', array('action' => 'index'), array('controller'=>'[a-z0-9]+'));

// connect
router::connect('/connect/lingyang/notify', array('controller' => 'connect', 'action' => 'lingyang_notify'), array());
router::connect('/connect/qiniu/notify', array('controller' => 'connect', 'action' => 'qiniu_notify'), array());
router::connect('/connect/chinacache/notify', array('controller' => 'connect', 'action' => 'chinacache_notify'), array());
router::connect('/connect/weixin/sync', array('controller' => 'connect', 'action' => 'weixin_sync'), array());
router::connect('/connect/:model', array('controller' => 'connect', 'action' => 'index'), array('model'=>'[a-z0-9]+'));
router::connect('/connect/:model/:method', array('controller' => 'connect', 'action' => 'index'), array('model'=>'[a-z0-9]+', 'method'=>'[a-z0-9]+'));

// factory
router::connect('/factory/:model', array('controller' => 'factory', 'action' => 'index'), array('model'=>'[a-z0-9]+'));

// rest 2.0
router::connect('/:controller/:action', array(), array('controller'=>'[a-z0-9]+','action'=>'[a-z0-9]+'));
router::connect('/:controller', array('action' => getgpc('method')), array('controller'=>'[a-z0-9]+'));

