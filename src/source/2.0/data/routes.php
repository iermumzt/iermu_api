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

// service
router::connect('/service/:model', array('controller' => 'service', 'action' => 'index'), array('model'=>'[a-z0-9]+'));

// oauth
router::connect('/oauth2/connect/success', array('controller' => 'oauth2', 'action' => 'connect_success'));
router::connect('/oauth2/:action', array('controller' => 'oauth2'), array('action'=>'[a-z0-9]+'));

router::connect('/oauth2/qrcode/:action', array('controller' => 'qrcode'), array('action'=>'[a-z0-9]+'));

// pcs  
router::connect('/pcs/:controller', array('action' => getgpc('method')), array('controller'=>'[a-z0-9]+'));
// ai
router::connect('/ai/:controller', array('action' => getgpc('method')), array('controller'=>'[a-z0-9]+'));

// media  
router::connect('/media/:controller', array('action' => getgpc('method')), array('controller'=>'[a-z0-9]+'));

// log
router::connect('/log/client', array('controller' => 'log', 'action' => getgpc('method')), array());

// bsm
router::connect('/bsm/:controller', array('action' => 'index'), array('controller'=>'[a-z0-9]+'));

// store
router::connect('/store/:model', array('controller' => 'store', 'action' => 'index'), array('model'=>'[a-z0-9]+'));

// map
router::connect('/map/:model', array('controller' => 'map', 'action' => 'index'), array('model'=>'[a-z0-9]+'));

// multiscreen
router::connect('/multiscreen/:model', array('controller' => 'multiscreen', 'action' => 'index'), array('model'=>'[a-z0-9]+'));

// connect
router::connect('/connect/lingyang/notify', array('controller' => 'connect', 'action' => 'lingyang_notify'), array());
router::connect('/connect/qiniu/notify', array('controller' => 'connect', 'action' => 'qiniu_notify'), array());
router::connect('/connect/zhifubao/notify/:logid', array('controller' => 'connect', 'action' => 'zhifubao_notify'), array('logid'=>'[a-z0-9]+'));
router::connect('/connect/chinacache/notify', array('controller' => 'connect', 'action' => 'chinacache_notify'), array());
router::connect('/connect/weixin/sync', array('controller' => 'connect', 'action' => 'weixin_sync'), array());
router::connect('/connect/:model', array('controller' => 'connect', 'action' => 'index'), array('model'=>'[a-z0-9]+'));
router::connect('/connect/:model/:method', array('controller' => 'connect', 'action' => 'index'), array('model'=>'[a-z0-9]+', 'method'=>'[a-z0-9_]+'));

// gome
router::connect('/connect/gome/user/token/get', array('controller' => 'gome', 'action' => 'user_token'));
router::connect('/connect/gome/device/online/get', array('controller' => 'gome', 'action' => 'device_online'));
router::connect('/connect/gome/device/status/get', array('controller' => 'gome', 'action' => 'device_status'));
router::connect('/connect/gome/device/video/live', array('controller' => 'gome', 'action' => 'device_live'));
router::connect('/connect/gome/device/video/playList', array('controller' => 'gome', 'action' => 'device_playlist'));
router::connect('/connect/gome/device/video/vod', array('controller' => 'gome', 'action' => 'device_vod'));
router::connect('/connect/gome/device/video/vodseek', array('controller' => 'gome', 'action' => 'device_vodseek'));
router::connect('/connect/gome/device/move', array('controller' => 'gome', 'action' => 'device_move'));
router::connect('/connect/gome/device/meta', array('controller' => 'gome', 'action' => 'device_meta'));
router::connect('/connect/gome/:model', array('controller' => 'gome', 'action' => 'index'), array('model'=>'[a-z0-9]+'));
router::connect('/connect/gome/:model/:method', array('controller' => 'gome', 'action' => 'index'), array('model'=>'[a-z0-9]+', 'method'=>'[a-z0-9_]+'));

// factory
router::connect('/factory/:model', array('controller' => 'factory', 'action' => getgpc('method')), array('model'=>'[a-z0-9]+'));

// partner
router::connect('/partner/:model', array('controller' => 'partner', 'action' => 'index'), array('model'=>'[a-z0-9]+'));


//ai_statistics
router::connect('/ai/:controller', array('action' => getgpc('method')), array('controller'=>'[a-z0-9]+'));

// rest 2.0
router::connect('/:controller/:action', array(), array('controller'=>'[a-z0-9]+','action'=>'[a-z0-9]+'));
router::connect('/:controller', array('action' => getgpc('method')), array('controller'=>'[a-z0-9]+'));

router::connect('/hackathon/:controller', array('action' => getgpc('method')), array('controller'=>'[a-z0-9]+'));

//minzhengtong
router::connect('/api/v1/facesnap', array('controller' => 'api', 'action' => 'facesnap'), array());

