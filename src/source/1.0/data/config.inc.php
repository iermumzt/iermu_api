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
define('API_DBHOST', 'localhost');
define('API_DBUSER', 'iermu_base');
define('API_DBPW', '68731188');
define('API_DBNAME', 'iermu_base');
define('API_DBCHARSET', 'utf8');
define('API_DBTABLEPRE', 'iermu_base.base_');
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
define('API_COOKIE_UNAME', 'IERMUUNAME');
define('API_COOKIE_UID', 'IERMUUID');

// connect
define('API_IERMU_CONNECT_TYPE', '0');
define('API_BAIDU_CONNECT_TYPE', '1');
define('API_BAIDU_REDIRECT_URI', 'https://api.iermu.com/oauth/2.0/connect/success');
define('API_LINGYANG_CONNECT_TYPE', '2');
define('API_CHINACACHE_CONNECT_TYPE', '3');
define('API_QDLT_CONNECT_TYPE', '4');

// 授权类型：token, cookie
define('API_AUTH_TYPE', 'token');
define('API_AUTH_APPID', '1');
define('API_AUTH_CLIENTID', '1111111111');
define('API_AUTH_CLIENTSECRET', '1111111111');

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

// Client ID 正则规则
define("OAUTH2_CLIENT_ID_REGEXP", "/^[a-z0-9-_]{3,32}$/i");

// 认证返回类型
define("OAUTH2_AUTH_RESPONSE_TYPE_ACCESS_TOKEN", "token");
define("OAUTH2_AUTH_RESPONSE_TYPE_AUTH_CODE", "code");
define("OAUTH2_AUTH_RESPONSE_TYPE_CODE_AND_TOKEN", "code_and_token");
define("OAUTH2_AUTH_RESPONSE_TYPE_NONE", "none");
define("OAUTH2_AUTH_RESPONSE_TYPE_REGEXP", "/^(token|code|code_and_token|none)$/");

// 授权方式类型
define("OAUTH2_GRANT_TYPE_AUTH_CODE", "authorization_code");
define("OAUTH2_GRANT_TYPE_USER_CREDENTIALS", "password");
define("OAUTH2_GRANT_TYPE_ASSERTION", "assertion");
define("OAUTH2_GRANT_TYPE_REFRESH_TOKEN", "refresh_token");
define("OAUTH2_GRANT_TYPE_NONE", "none");
define("OAUTH2_GRANT_TYPE_REGEXP", "/^(authorization_code|password|assertion|refresh_token|none)$/");

// HTTP 状态码
define("OAUTH2_HTTP_FOUND", "302 Found");
define("OAUTH2_HTTP_BAD_REQUEST", "400 Bad Request");
define("OAUTH2_HTTP_UNAUTHORIZED", "401 Unauthorized");
define("OAUTH2_HTTP_FORBIDDEN", "403 Forbidden");

// storage
define('STORAGE_TEMP_URL_DEFAULT_VALIDTIME', 3600);

// cvr
define('CVR_FILE_TABLE_MAXCOUNT', 200);

// log
define('LOG_DEBUG_PATH', 'api/online');
define('LOG_ACCESS_PATH', 'api/access_log');

// activity environment
define('API_QINIU_IMG_BASEURL', 'http://7xoa8w.com2.z0.glb.qiniucdn.com/');
define('API_TEST_IMG_BASEURL', 'http://123.57.4.235:8082/');
