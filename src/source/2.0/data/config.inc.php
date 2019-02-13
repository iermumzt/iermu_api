<?php

/**
 * API配置
 */

// API是否开放
define('API_OPEN', 1);

// 用户接入设备数量限制
define('API_USER_DEVICE_MAX_NUM', 10);

// 用户绑定设备数量限制
define('API_USER_DEVICE_BIND_NUM', 2);

// API返回默认数据格式
define('API_RESPONSE_DEFAULT_TYPE', 'json');

// 错误输出默认数据格式
define('API_ERROR_DEFAULT_TYPE', 'json');

// 错误输入是否输出详细信息
define('API_ERROR_DISPLAY_ERROR', 0);

// 数据库
define('API_DBHOST', '127.0.0.1');
define('API_DBUSER', 'root');
define('API_DBPW', 'root');
define('API_DBNAME', 'iermu');
define('API_DBCHARSET', 'utf8');
define('API_DBTABLEPRE', 'base_');
define('API_DBCONNECT', 0);

// redis
define('API_RDHOST', 'localhost');
define('API_RDPORT', '6379');
define('API_RDPW', '');
define('API_RDKEYPRE', 'iermu_base.base_');

// 应用
define('API_COOKIEPATH', '/');
define('API_COOKIEDOMAIN', 'iermu.com');
define('API_CHARSET', 'utf-8');
define('API_FOUNDERPW', 'e7dcd04b8e12cb9055fe29e94ff6c8f9');
define('API_FOUNDERSALT', '101061');
define('API_KEY', 'aP8vfLfM1t5i36cQ3u974j120J462o7Oajcvff4i278jbeaP6c735X51ei2zeb7o');
define('API_SITEID', 'aJ83fKfC145F38ck3B984K150X4k247Wa9cIfB4x2k8DbOan6M7G5g5ZeR2sex7M');
define('API_MYKEY', 'aj8ofjfL1H5J39cY399W4j1E0e422x75aZc8fO4I2C8NbBay6K7M5i5Te82deT7Y');
define('API_DEBUG', false);
define('API_PPP', 20);

define('API_COOKIE_SID', 'IERMUSID');
define('API_COOKIE_RID', 'IERMURID');
define('API_COOKIE_CID', 'IERMUCID');
define('API_COOKIE_UNAME', 'IERMUUNAME');
define('API_COOKIE_UID', 'IERMUUID');
define('API_COOKIE_WX_NICKNAME', 'wx_nickname');

// connect
define('API_IERMU_CONNECT_TYPE', '0');
define('API_BAIDU_CONNECT_TYPE', '1');
define('API_BAIDU_REDIRECT_URI', 'https://api.iermu.com/oauth2/connect/success');
define('API_BAIDU_PCS', 0);
define('API_LINGYANG_CONNECT_TYPE', '2');
define('API_CHINACACHE_CONNECT_TYPE', '3');
define('API_QDLT_CONNECT_TYPE', '4');
define('API_WEIXIN_CONNECT_TYPE', '5');
define('API_GOME_CONNECT_TYPE', '8');

// 授权类型：token, cookie
define('API_AUTH_TYPE', 'token');
define('API_AUTH_APPID', '1');
define('API_AUTH_CLIENTID', '1111111111');
define('API_AUTH_CLIENTSECRET', '1111111111');
define('API_AUTH_LOGIN', 0);

define('API_DEFAULT_REDIRECT', 'http://www.iermu.com');

// passport
define('PASSPORT_API', 'https://passport.iermu.com');

// user
define('API_USER_AVATAR_API', 'https://img.iermu.com/');
    
// APP STORE
define('APP_STORE_VERIFY_RECEIPT_API', 'https://buy.itunes.apple.com/verifyReceipt');
define('APP_STORE_SANDBOX_VERIFY_RECEIPT_API', 'https://sandbox.itunes.apple.com/verifyReceipt');
define('APP_STORE_VERIFY_RECEIPT_PASSWORD', '86281937e6f4447b9d201bc248a44d25');
define('APP_STORE_VERIFY_RECEIPT_TIMEOUT', 300);

// HTTP状态码
define("API_HTTP_OK", "200 OK");
define("API_HTTP_FOUND", "302 Found");
define("API_HTTP_BAD_REQUEST", "400 Bad Request");
define("API_HTTP_UNAUTHORIZED", "401 Unauthorized");
define("API_HTTP_FORBIDDEN", "403 Forbidden");
define("API_HTTP_NOT_FOUND", "404 Not Found");
define("API_HTTP_INTERNAL_SERVER_ERROR", "500 Internal Server Error");
define("API_HTTP_BAD_GATEWAY", "502 Bad Gateway");
define("API_HTTP_SERVICE_UNAVAILABLE", "503 Service unavailable");

/**
 * OAuth2.0相关设置
 */
 
// 定义OAuth Access Token参数名
define('OAUTH2_TOKEN_PARAM_NAME', 'access_token');

// Access Token 默认有效期（秒）
define('OAUTH2_DEFAULT_ACCESS_TOKEN_LIFETIME', 2592000);

// Authorization Code 默认有效期（秒）
define('OAUTH2_DEFAULT_AUTH_CODE_LIFETIME', 30);

// Refresh Token 默认有效期（秒）
define('OAUTH2_DEFAULT_REFRESH_TOKEN_LIFETIME', 315360000);

// QR Code 默认有效期（秒）
define('OAUTH2_DEFAULT_QRCODE_LIFETIME', 180);

// Client ID 正则规则
define("OAUTH2_CLIENT_ID_REGEXP", "/^[a-z0-9-_]{3,32}$/i");

// 认证返回类型
define("OAUTH2_AUTH_RESPONSE_TYPE_ACCESS_TOKEN", "token");
define("OAUTH2_AUTH_RESPONSE_TYPE_AUTH_CODE", "code");
define("OAUTH2_AUTH_RESPONSE_TYPE_QRCODE", "qrcode");
define("OAUTH2_AUTH_RESPONSE_TYPE_CODE_AND_TOKEN", "code_and_token");
define("OAUTH2_AUTH_RESPONSE_TYPE_NONE", "none");
define("OAUTH2_AUTH_RESPONSE_TYPE_REGEXP", "/^(token|code|qrcode|code_and_token|none)$/");

// 授权方式类型
define("OAUTH2_GRANT_TYPE_AUTH_CODE", "authorization_code");
define("OAUTH2_GRANT_TYPE_QRCODE", "qrcode");
define("OAUTH2_GRANT_TYPE_USER_CREDENTIALS", "password");
define("OAUTH2_GRANT_TYPE_USER_MOBILE", "mobile");
define("OAUTH2_GRANT_TYPE_CLIENT_CREDENTIALS", "client_credentials");
define("OAUTH2_GRANT_TYPE_DOMAIN", "domain");
define("OAUTH2_GRANT_TYPE_ASSERTION", "assertion");
define("OAUTH2_GRANT_TYPE_REFRESH_TOKEN", "refresh_token");
define("OAUTH2_GRANT_TYPE_NONE", "none");
define("OAUTH2_GRANT_TYPE_REGEXP", "/^(authorization_code|qrcode|password|mobile|client_credentials|domain|assertion|refresh_token|none)$/");

// HTTP 状态码
define("OAUTH2_HTTP_FOUND", "302 Found");
define("OAUTH2_HTTP_BAD_REQUEST", "400 Bad Request");
define("OAUTH2_HTTP_UNAUTHORIZED", "401 Unauthorized");
define("OAUTH2_HTTP_FORBIDDEN", "403 Forbidden");

// storage
define('STORAGE_TEMP_URL_DEFAULT_VALIDTIME', 3600);
define('STORAGE_DEFAULT_ALARM_STORAGEID', 11);

// cvr
define('CVR_FILE_TABLE_MAXCOUNT', 200);

// log
define('LOG_DEBUG_PATH', 'api/online');
define('LOG_ACCESS_PATH', 'api/access_log');

// activity environment
define('API_QINIU_IMG_BASEURL', 'http://7xoa8w.com2.z0.glb.qiniucdn.com/');
define('API_TEST_IMG_BASEURL', 'http://123.57.4.235:8082/');

// bitlevel
define('BAIDU_720P_DEVICE_BITLEVEL_0', 200);
define('BAIDU_720P_DEVICE_BITLEVEL_1', 400);
define('BAIDU_720P_DEVICE_BITLEVEL_2', 600);
define('BAIDU_1080P_DEVICE_BITLEVEL_0', 200);
define('BAIDU_1080P_DEVICE_BITLEVEL_1', 800);
define('BAIDU_1080P_DEVICE_BITLEVEL_2', 1500);

define('LINGYANG_720P_DEVICE_BITLEVEL_0', 200);
define('LINGYANG_720P_DEVICE_BITLEVEL_1', 500);
define('LINGYANG_720P_DEVICE_BITLEVEL_2', 1000);
define('LINGYANG_1080P_DEVICE_BITLEVEL_0', 300);
define('LINGYANG_1080P_DEVICE_BITLEVEL_1', 1000);
define('LINGYANG_1080P_DEVICE_BITLEVEL_2', 2000);

//433 action mask
define('KERUI_ACTION_LOW_POWER', 0xf);// 1111
define('KERUI_ACTION_BROKEN', 0xb);// 1011
define('KERUI_ACTION_DOOR_OPEN', 0xe);// 1110
define('KERUI_ACTION_DOOR_CLOSE', 0x7);// 0111
define('KERUI_ACTION_PIR', 0xa);// 1010
define('KERUI_ACTION_SOS', 0x2);// 0010
define('KERUI_ACTION_SMOKE', 0x9);// 0010

//433 action type
define('SENSOR_ACTION_LOW_POWER_TYPE', 1);
define('SENSOR_ACTION_BROKEN_TYPE', 4);
define('SENSOR_ACTION_DOOR_OPEN_TYPE', 2);
define('SENSOR_ACTION_DOOR_CLOSE_TYPE', 3);
define('SENSOR_ACTION_PIR_TYPE', 5);
define('SENSOR_ACTION_SOS_TYPE', 6);
define('SENSOR_ACTION_SMOKE_TYPE', 7);

// qrcode
define('OAUTH2_QRCODE_BASEURL', 'https://passport.iermu.com/qrcode/');
define('QR_TMP_PATH', API_ROOT.'./data/tmp/');

// weixin
define('WX_MP_URL', '');

// dvr keeplive interval
define('DVR_KEEPLIVE_INTERVAL', 45);

// upload
define('API_UPLOAD_DIR', '/usr/data/upload/');

// qiniu
define('QINIU_CALLBACK_URL', 'https://api.iermu.com/v2/connect/qiniu/notify');
