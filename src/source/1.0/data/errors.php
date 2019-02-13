<?php 
/**
 * 错误配置
 */

// API
define("API_CLOSE", "50201:api closed");
define("API_ERROR_INVALID_REQUEST", "40001:invalid request");
define("API_ERROR_INVALID_CLIENT", "40101:invalid client");
define("API_ERROR_NO_AUTH", "40102:no auth");
define("API_ERROR_PARAM", "31023:param error");
define("API_ERROR_NETWORK", "31021:network error");

if(defined('API_AUTH_TYPE') && API_AUTH_TYPE != 'token') {
    define("API_ERROR_TOKEN", "110:not login");
} else {
    define("API_ERROR_TOKEN", "110:access token invalid or no longer valid");
}

// DB
define("API_ERROR_DB_FAILED", "50001:db error");

// OAuth2
define("OAUTH2_ERROR_INVALID_REQUEST", "40014:invalid_request");
define("OAUTH2_ERROR_INVALID_CLIENT", "40013:invalid_client");
define("OAUTH2_ERROR_UNAUTHORIZED_CLIENT", "40018:unauthorized_client");
define("OAUTH2_ERROR_REDIRECT_URI_MISMATCH", "40020:redirect_uri_mismatch");
define("OAUTH2_ERROR_USER_DENIED", "40302:access_denied");
define("OAUTH2_ERROR_UNSUPPORTED_RESPONSE_TYPE", "40021:unsupported_response_type");
define("OAUTH2_ERROR_INVALID_SCOPE", "40022:invalid_scope");
define("OAUTH2_ERROR_INVALID_GRANT", "40019:invalid_grant");
define("OAUTH2_ERROR_UNSUPPORTED_GRANT_TYPE", "40017:unsupported_grant_type");
define("OAUTH2_ERROR_INSUFFICIENT_SCOPE", "40303:insufficient_scope");
define("OAUTH2_ERROR_INVALID_TOKEN", "40109:access_token_invalid");
define("OAUTH2_ERROR_INVALID_REFRESH_TOKEN", "40119:refresh_token_invalid");
define("OAUTH2_ERROR_USER_NOT_EXIST", "40120:user_not_exist");
define("OAUTH2_ERROR_USER_PASSWORD", "40121:password_error");

// 用户
define('API_ERROR_USER_CHECK_USERNAME_FAILED', "40031:user check username failed");
define('API_ERROR_USER_USERNAME_BADWORD', "40032:user username badword");
define('API_ERROR_USER_USERNAME_EXISTS', "40033:user username exists");
define('API_ERROR_USER_EMAIL_FORMAT_ILLEGAL', "40034:user email format illegal");
define('API_ERROR_USER_EMAIL_ACCESS_ILLEGAL', "40035:user email access illegal");
define('API_ERROR_USER_EMAIL_EXISTS', "40036:user email exists");
define('API_ERROR_USER_EMAIL_NOT_EXISTS', "40037:user email not exist");
define('API_ERROR_USER_ADD_FAILED', "40038:user add failed");
define('API_ERROR_USER_CHANGE_PASSWORD_FAILED', "40039:user change password failed");

define("API_ERROR_USER_NOT_EXISTS", "40040:user not exist");
define("API_ERROR_USER_PW", "40041:password error");
define("API_ERROR_NEED_SECCODE", "40042:need seccode");
define("API_ERROR_SECCODE", "40043:seccode error");
define("API_ERROR_LOGIN_FAILED_TIMES", "40044:login failed too many times");

define('API_ERROR_USER_COMPLETED', "40050:user profile already completed");
define('API_ERROR_USER_COMPLETE_FAILED', "40051:user profile complete failed");

define('API_ERROR_USER_GET_INFO_FAILED', "40060:user get info failed");
define('API_ERROR_USER_GET_CONNECT_FAILED', "40061:user get connect failed");

define('API_ERROR_USER_SENDVERIFY_TOO_QUICK', "40080:send verifycode too quick");
define('API_ERROR_USER_SENDVERIFY_FAILED', "40081:send verify failed");

// Device
define("DEVICE_ERROR_ALREADY_REG", "31350:device already registed");
define("DEVICE_ERROR_CONNECT_TYPE_REG", "300100:device connect type cannot change");
define("DEVICE_ERROR_ADD_FAILED", "31352:add device failed");
define("DEVICE_ERROR_UPDATE_FAILED", "31355:update device failed");

define("DEVICE_ERROR_CREATE_SHARE_FAILED", "31359:create device share failed");
define("DEVICE_ERROR_CANCEL_SHARE_FAILED", "31360:cancel device share failed");

define("DEVICE_ERROR_NO_PUBLIC_SHARE_AUTH", "31361:no permission to list public share device");

define("DEVICE_ERROR_GET_BMS_SERVER_FAILED", "31362:get bms server failed");
define("DEVICE_ERROR_GET_LIVE_URL_FAILED", "31363:get live play url failed");
define("DEVICE_ERROR_SHARE_NOT_EXIST", "31365:device share not exist");
define("DEVICE_ERROR_NO_PLAYLIST", "31355:no playlist");
define("DEVICE_ERROR_NO_THUMBNAIL", "31356:no thumbnail");
define("DEVICE_ERROR_NO_TS", "31362:no ts");

define("DEVICE_ERROR_DROP_FAILED", "31399:drop device failed");

define("DEVICE_ERROR_NOT_EXIST", "31353:device not exist");
define("DEVICE_ERROR_NO_AUTH", "31354:No permission");

define("DEVICE_ERROR_SHARE_NOT_EXIST", "31365:device share not exist");
define("DEVICE_ERROR_SUB_FAILED", "31369:subscribe device failed");
define("DEVICE_ERROR_UNSUB_FAILED", "31370:unsubscribe device failed");

define("DEVICE_ERROR_GRANT_LIMIT", "31381:grant exceed limit");
define("DEVICE_ERROR_GRANT_FAILED", "31382:grant device failed");
define("DEVICE_ERROR_GRANT_DELETE_FAILED", "31383:delete device grant failed");
define("DEVICE_ERROR_DROP_VIDEO_FAILED", "31390:drop video failed");

define("DEVICE_ERROR_ADD_TOKEN_FAILED", "31415:add user token failed");
define("DEVICE_ERROR_UPDATE_TOKEN_FAILED", "31346:update user token failed");
define("DEVICE_ERROR_GET_TOKEN_FAILED", "31420:get user token failed");

define("DEVICE_ERROR_USER_CMD_FAILED", "400001:user cmd failed");
define("DEVICE_ERROR_GET_SETTINGS_FAILED", "400002:get device settings failed");
define("DEVICE_ERROR_UPDATE_SETTINGS_FAILED", "400003:update device settings failed");

define("DEVICE_ERROR_THUMBNAIL_VERIFY_FAILED", "400100:thumbnail verify failed");

define("DEVICE_ERROR_CRON_DELETE_CONFIG_EMPTY", "400200:cron delete config empty");

define("CONNECT_ERROR_API_FAILED", "400300:connect api request failed");
define("CONNECT_ERROR_MSG_NOTIFY_FAILED", "400301:connect msg notify failed");
define("CONNECT_ERROR_USER_UPDATE_FAILED", "400302:connect user update failed");
define("CONNECT_ERROR_USER_TOKEN_INVALID", "400303:connect user token invalid");
define("CONNECT_ERROR_DEVICE_TOKEN_INVALID", "400304:connect device token invalid");

define("DEVICE_ERROR_CLIP_FAILED", "31372:clip request failed");

// extends
define('API_ERROR_USER_GET_CHARTS_FAILED', "400400:get user charts failed");

define('API_ERROR_SEARCH_USER_LIST_FAILED', "400401:search user list failed");

define('API_ERROR_USER_GET_CATEGORY_FAILED', "400402:get user category failed");
define('API_ERROR_USER_ADD_CATEGORY_FAILED', "400403:add user category failed");
define('API_ERROR_USER_CATEGORY_NOT_EXIST', "400404:user category not exist");
define('API_ERROR_USER_DEL_CATEGORY_FAILED', "400405:del user category failed");

define('API_ERROR_USER_GET_KEYWORD_FAILED', "400406:get user keyword failed");
define('API_ERROR_USER_DEL_KEYWORD_FAILED', "400407:del user keyword failed");

define("API_ERROR_USER_NOT_EXIST", "400408:user not exist");
define("API_ERROR_GET_USER_COMMENT_FAILED", "400409:get user comment failed");
define("API_ERROR_ADD_USER_COMMENT_FAILED", "400410:add user comment failed");

define("API_ERROR_UPDATE_USERNAME_FAILED", "400411:update username failed");

define("API_ERROR_LIST_USER_VIEW_FAILED", "400412:list user view failed");
define("API_ERROR_ADD_USER_VIEW_FAILED", "400413:add user view failed");
define("API_ERROR_DROP_USER_VIEW_FAILED", "400414:drop user view failed");
define("API_ERROR_USER_CATEGORY_ALREADY_EXIST", "400415:user category already exist");
define('API_ERROR_USER_UPDATE_CATEGORY_FAILED', "400416:update user category failed");

// Device
define("DEVICE_ERROR_DEL_CATEGORY_FAILED", "400500:del device category failed");
define("DEVICE_ERROR_GET_COMMENT_FAILED", "400501:get device comment failed");
define("DEVICE_ERROR_ADD_COMMENT_FAILED", "400502:add device comment failed");

define("DEVICE_ERROR_LIST_VIEW_FAILED", "400503:device list view failed");
define("DEVICE_ERROR_ADD_VIEW_FAILED", "400504:add device view failed");
define("DEVICE_ERROR_DROP_VIEW_FAILED", "400505:drop device view failed");
define("DEVICE_ERROR_ADD_APPROVE_FAILED", "400506:add device approve failed");

define("DEVICE_ERROR_ADD_REPORT_FAILED", "400507:add device report failed");

// define("DEVICE_ERROR_LIST_SHARE_FAILED", "400508:device list share failed");
define("DEVICE_ERROR_SEARCH_SHARE_FAILED", "400509:search device share list failed");

define("DEVICE_ERROR_GET_AD_FAILED", "400510:get device ad failed");

define("DEVICE_ERROR_GET_GRANT_FAILED", "400511:get device grant failed");
define("DEVICE_ERROR_GRANT_NOT_EXIST", "400512:device grant not exist");
define("DEVICE_ERROR_GRANT_INVALID", "400513:device grant invalid");
define("DEVICE_ERROR_GRANT_USED", "400514:device grant used");
define("DEVICE_ERROR_NOT_GRANT_SELF", "400515:device not grant self");
define("DEVICE_ERROR_ADD_GRANT_FAILED", "400516:add device grant failed");

define("DEVICE_ERROR_GET_TS_FAILED", "400517:get device ts failed");

define("DEVICE_ERROR_ADD_CATEGORY_FAILED", "400518:add device category failed");
define("DEVICE_ERROR_UPDATE_CATEGORY_FAILED", "400519:update device category failed");

define("DEVICE_ERROR_DEVICE_LIST_TOO_LONG", "400520:device list too long");
define("DEVICE_ERROR_GET_GRANT_INFO_FAILED", "400521:get device grant info failed");
define("DEVICE_ERROR_ADD_COMMENT_NOT_ALLOWED", "400522:add device comment not allowed");

define("DEVICE_ERROR_PARTNER_ADD_FAILED", "400600:add device failed");
define("DEVICE_ERROR_PARTNER_DROP_FAILED", "400601:drop device failed");
define("DEVICE_ERROR_LIST_ALARM_FAILED", "400602:list alarm failed");
define("DEVICE_ERROR_DROP_ALARM_FAILED", "400603:drop alarm failed");
define("DEVICE_ERROR_GET_ALARM_FAILED", "400604:get alarm failed");

define("DEVICE_ERROR_PLAT_NOT_SUPPORT", "400700:plat not support");
define("DEVICE_ERROR_PLAT_NOT_EXIST", "400701:plat not exist");
define("DEVICE_ERROR_PLAT_MOVE_POINT_FAILED", "400702:plat move point failed");
define("DEVICE_ERROR_PLAT_MOVE_PRESET_FAILED", "400703:plat move preset failed");
define("DEVICE_ERROR_PLAT_ROTATE_FAILED", "400704:plat rotate failed");
define("DEVICE_ERROR_PLAT_ADD_PRESET_FAILED", "400705:plat add preset failed");
define("DEVICE_ERROR_PLAT_DROP_PRESET_FAILED", "400706:plat drop preset failed");
define("DEVICE_ERROR_PLAT_LIST_PRESET_FAILED", "400707:plat list preset failed");
define("DEVICE_ERROR_UPGRADE_FAILED", "400708:device upgrade failed");
define("DEVICE_ERROR_REPAIR_FAILED", "400709:device repair failed");
define("DEVICE_ERROR_GET_PRESET_TOKEN_FAILED", "400710:get preset token failed");
define("DEVICE_ERROR_UPLOAD_PRESET_THUMBNAIL_FAILED", "400711:upload preset thumbnail failed");

define("DEVICE_ERROR_UPDATE_CVR_FAILED", "400800:update cvr failed");

// push
define("PUSH_ERROR_CLIENT_REGISTER_FAILED", "400801:push client register failed");
define("PUSH_ERROR_DEVICE_ALARM_FAILED", "400802:push device alarm failed");
define("PUSH_ERROR_SERVICE_UNAVAILABLE", "400803:push service unavailable");
define("PUSH_ERROR_CLIENT_CONFIG", "400804:push client config incorrect");
define("PUSH_ERROR_DEVICE_ALARM_FILE_EXIST", "400805:push device alarm file exist");
define("PUSH_ERROR_CLIENT_UNREGISTER", "400806:push client unregister");

// poster
define("CLIENT_ERROR_POSTER_NOT_EXIST", "400900:poster not exist");
define("CLIENT_ERROR_FEEDBACK_FAILED", "400901:client feedback failed");

// device
define("DEVICE_ERROR_GET_UPLOADTOKEN_FAILED", "400950:device get uploadtoken failed");
define("DEVICE_ERROR_UPDATE_STATUS_FAILED", "400951:device update status failed");
define("DEVICE_ERROR_NEED_CONFIG", "400952:device need config");

// log
define("LOG_ERROR_UPLOAD_FILE_NOT_EXIST", "401000:upload file not exist");
define("LOG_ERROR_UPLOAD_FILE_TOO_LARGE", "401001:upload file too large");
define("LOG_ERROR_UPLOAD_FILE_INTERNAL", "401002:internal error");
define("LOG_ERROR_SYSTEM_BUSY", "401003:system is busy, stop upload");

//connect
define("LOG_ERROR_CURL_REQUEST_FAILED", "402000:internal curl request failed");
define("LOG_ERROR_EXTERNAL_PARAM_ERROR", "402001:external param error");
define("LOG_ERROR_EXTERNAL_TOKEN_ERROR", "402002:external token error");
define("LOG_ERROR_EXTERNAL_CONTENT_NOT_FOUND", "402003:external content not found");
define("LOG_ERROR_EXTERNAL_SERVICE_UNAVAILABLE", "402004:external service unavailabel");
define("LOG_ERROR_EXTERNAL_LIMIT", "402005:external exceed the upper limit");
define("LOG_ERROR_EXTERNAL_HAS_CLIPED", "402006:external has been cliped");
define("LOG_ERROR_EXTERNAL_UNKNOWN", "402007:external unknown");