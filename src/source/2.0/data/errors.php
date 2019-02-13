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
    define("API_ERROR_REVOKE_TOKEN", "111:not login");
} else {
    define("API_ERROR_TOKEN", "110:access token invalid or no longer valid");
    define("API_ERROR_REVOKE_TOKEN", "111:access token invalid");
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

define("OAUTH2_ERROR_QRCODE_CREATE_FAILED", "40200:qrcode_create_failed");
define("OAUTH2_ERROR_QRCODE_INVALID", "40201:qrcode_invalid");
define("OAUTH2_ERROR_QRCODE_SCAN_FAILED", "40202:qrcode_scan_failed");
define("OAUTH2_ERROR_QRCODE_AUTH_FAILED", "40203:qrcode_auth_failed");

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
define("API_ERROR_USER_PASSWORD_FORMAT_ILLEGAL", "40045:user password format illegal");
define("API_ERROR_USER_PASSWORD_SECURITY_CHECK_FAILED", "40046:user password security check failed");

define('API_ERROR_USER_COMPLETED', "40050:user profile already completed");
define('API_ERROR_USER_COMPLETE_FAILED', "40051:user profile complete failed");

define('API_ERROR_USER_GET_INFO_FAILED', "40060:user get info failed");
define('API_ERROR_USER_GET_CONNECT_FAILED', "40061:user get connect failed");

define('API_ERROR_USER_COUNTRYCODE_NOT_SUPPORT', "40070:countrycode not support");
define('API_ERROR_USER_MOBILE_FORMAT_ILLEGAL', "40071:mobile format illegal");
define('API_ERROR_USER_MOBILE_ACCESS_ILLEGAL', "40072:mobile access illegal");
define('API_ERROR_USER_MOBILE_EXISTS', "40073:mobile exists");
define('API_ERROR_USER_MOBILE_NOT_EXISTS', "40074:mobile not exist");

define('API_ERROR_USER_SENDVERIFY_TOO_QUICK', "40080:send verifycode too quick");
define('API_ERROR_USER_SENDVERIFY_FAILED', "40081:send verify failed");
define('API_ERROR_USER_VERIFYCODE_INVALID', "40082:verifycode invalid");
define('API_ERROR_USER_AUTHCODE_INVALID', "40083:authcode invalid");
define('API_ERROR_USER_UPDATE_MOBILE_FAILED', "40084:update mobile failed");
define('API_ERROR_USER_UPDATE_EMAIL_FAILED', "40085:update email failed");
define('API_ERROR_USER_MOBILE_DISMATCH', "40086:user mobile dismatch");
define('API_ERROR_USER_EMAIL_DISMATCH', "40087:user email dismatch");
define('API_ERROR_USER_MOBILE_UNVERIFY', "40088:user mobile unverify");
define('API_ERROR_USER_EMAIL_UNVERIFY', "40089:user email unverify");
define('API_ERROR_USER_CHECKAUTH_FAILED', "40090:user check auth failed");

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
define("DEVICE_ERROR_INIT_FAILED", "400004:device init failed");

define("DEVICE_ERROR_THUMBNAIL_VERIFY_FAILED", "400100:thumbnail verify failed");

define("DEVICE_ERROR_CRON_DELETE_CONFIG_EMPTY", "400200:cron delete config empty");

define("CONNECT_ERROR_API_FAILED", "400300:connect api request failed");
define("CONNECT_ERROR_MSG_NOTIFY_FAILED", "400301:connect msg notify failed");
define("CONNECT_ERROR_USER_UPDATE_FAILED", "400302:connect user update failed");
define("CONNECT_ERROR_USER_TOKEN_INVALID", "400303:connect user token invalid");
define("CONNECT_ERROR_DEVICE_TOKEN_INVALID", "400304:connect device token invalid");
define("CONNECT_ERROR_USER_EXIST", "400305:connect user exist");
define("CONNECT_ERROR_USER_NOT_EXIST", "400306:connect user not exist");

define("DEVICE_ERROR_CLIP_FAILED", "31372:clip request failed");
define("DEVICE_ERROR_CLIP_EXIST", "31373:clip task exist");
define("DEVICE_ERROR_CLIP_NODATA", "31374:clip request no data");

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
define('API_ERROR_USER_SAVE_REMARKNAME_FAILED', "400417:save user remarkname failed");

// Device
define("DEVICE_ERROR_DEL_CATEGORY_FAILED", "400500:del device category failed");
define("DEVICE_ERROR_GET_COMMENT_FAILED", "400501:get device comment failed");
define("DEVICE_ERROR_ADD_COMMENT_FAILED", "400502:add device comment failed");

define("DEVICE_ERROR_LIST_VIEW_FAILED", "400503:device list view failed");
define("DEVICE_ERROR_ADD_VIEW_FAILED", "400504:add device view failed");
define("DEVICE_ERROR_DROP_VIEW_FAILED", "400505:drop device view failed");
define("DEVICE_ERROR_ADD_APPROVE_FAILED", "400506:add device approve failed");

define("DEVICE_ERROR_ADD_REPORT_FAILED", "400507:add device report failed");

define("DEVICE_ERROR_SEARCH_LIST_FAILED", "400508:search device list failed");
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

define("DEVICE_ERROR_REPORT", "400523:device report");
define("DEVICE_ERROR_BLACKLIST", "400524:device in blacklist");

define("DEVICE_ERROR_PARTNER_ADD_FAILED", "400600:add device failed");
define("DEVICE_ERROR_PARTNER_DROP_FAILED", "400601:drop device failed");
define("DEVICE_ERROR_LIST_ALARM_FAILED", "400602:list alarm failed");
define("DEVICE_ERROR_DROP_ALARM_FAILED", "400603:drop alarm failed");
define("DEVICE_ERROR_GET_ALARM_FAILED", "400604:get alarm failed");
define("DEVICE_ERROR_GET_ALARM_SPACE_FAILED", "400605:get alarm space failed");
define("DEVICE_ERROR_GET_PLAY_STATUS_FAILED", "400606:get play status failed");
define("DEVICE_ERROR_PLAY_FAILED", "400607:device play failed");

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
define("DEVICE_ERROR_PLAT_MOVE_DIRECTION_FAILED", "400712:plat move direction failed");
define("DEVICE_ERROR_PLAT_SET_DELAY_FAILED", "400713:plat set delay failed");
define("DEVICE_ERROR_PLAT_UPDATE_PRESET_FAILED", "400714:plat update preset failed");

define("DEVICE_ERROR_GET_SUM_FAILED", "400721:get device sum failed");
define("DEVICE_ERROR_LIST_ALARMDEVICE_FAILED", "400722:list alarm device failed");

define("DEVICE_ERROR_NOT_SUPPORT_PARTNER_DEVICE", "400750:not support partner device");

define("DEVICE_ERROR_LIST_CVR_RECORD_FAILED", "400780:list cvr record failed");
define("DEVICE_ERROR_UPDATE_CVR_FAILED", "400800:update cvr failed");

//watermark
define("DEVICE_ERROR_GET_WATERMARK_FAILED", "400801:get watermark image failed");
define("DEVICE_ERROR_UPLOAD_WATERMARK_FAILED", "400802:upload watermark image failed");
define("DEVICE_ERROR_DELETE_WATERMARK_FAILED", "400802:delete watermark image failed");

// push
define("PUSH_ERROR_CLIENT_REGISTER_FAILED", "400801:push client register failed");
define("PUSH_ERROR_DEVICE_ALARM_FAILED", "400802:push device alarm failed");
define("PUSH_ERROR_SERVICE_UNAVAILABLE", "400803:push service unavailable");
define("PUSH_ERROR_CLIENT_CONFIG", "400804:push client config incorrect");
define("PUSH_ERROR_DEVICE_ALARM_FILE_EXIST", "400805:push device alarm file exist");
define("PUSH_ERROR_CLIENT_UNREGISTER", "400806:push client unregister");
define("PUSH_ERROR_DEVICE_ALARM_NO_STORAGE", "400807:push device alarm no storage");

// web
define("CLIENT_ERROR_POSTER_NOT_EXIST", "400900:poster not exist");
define("CLIENT_ERROR_FEEDBACK_FAILED", "400901:client feedback failed");
define("CLIENT_ERROR_GET_ARTICLE_INFO_FAILED", "400902:get article info failed");

// device
define("DEVICE_ERROR_GET_UPLOADTOKEN_FAILED", "400950:device get uploadtoken failed");
define("DEVICE_ERROR_UPDATE_STATUS_FAILED", "400951:device update status failed");
define("DEVICE_ERROR_NEED_CONFIG", "400952:device need config");
define("DEVICE_ERROR_ADD_SENSOR", "400953:device add sensor failed");
define("DEVICE_ERROR_LIST_SENSOR", "400954:list device sensor failed");
define("DEVICE_ERROR_SENSOR_NAME", "400955:check sensor name failed");
define("DEVICE_ERROR_DROP_SENSOR", "400956:device drop sensor failed");
define("DEVICE_ERROR_SENSOR_ALREADY_BINDED", "400957:sensor already binded");
define("DEVICE_ERROR_SENSOR_NAME_CONFLICT", "400958:sensor name conflict");
define("DEVICE_ERROR_SENSOR_NAME_ILLEGAL", "400959:sensor name illegal");
define("DEVICE_ERROR_GENERATE_SENSORCODE_FAILED", "400960:generate sensorcode failed");
define("DEVICE_ERROR_DEVICESENSOR_UPDATE_FAILED", "400961:update devicesensor failed");
define("DEVICE_ERROR_SENSOR_UPDATE_FAILED", "400962:update sensor failed");
define("DEVICE_ERROR_SENSOR_INFO_FAILED", "400963:get sensor info failed");

define("DEVICE_ERROR_LIST_RELAY_SERVER_FAILED", "400970:list relay server failed");
define("DEVICE_ERROR_LIST_LOG_SERVER_FAILED", "400971:list log server failed");

// log
define("LOG_ERROR_UPLOAD_FILE_NOT_EXIST", "401000:upload file not exist");
define("LOG_ERROR_UPLOAD_FILE_TOO_LARGE", "401001:upload file too large");
define("LOG_ERROR_UPLOAD_FILE_INTERNAL", "401002:internal error");
define("LOG_ERROR_SYSTEM_BUSY", "401003:system is busy, stop upload");

// connect
define("LOG_ERROR_CURL_REQUEST_FAILED", "402000:internal curl request failed");
define("LOG_ERROR_EXTERNAL_PARAM_ERROR", "402001:external param error");
define("LOG_ERROR_EXTERNAL_TOKEN_ERROR", "402002:external token error");
define("LOG_ERROR_EXTERNAL_CONTENT_NOT_FOUND", "402003:external content not found");
define("LOG_ERROR_EXTERNAL_SERVICE_UNAVAILABLE", "402004:external service unavailabel");
define("LOG_ERROR_EXTERNAL_LIMIT", "402005:external exceed the upper limit");
define("LOG_ERROR_EXTERNAL_HAS_CLIPED", "402006:external has been cliped");
define("LOG_ERROR_EXTERNAL_UNKNOWN", "402007:external unknown");

// partner
define("PARTNER_ERROR_UPDATE_DEVICELIST_FAILED", "402100:update device list failed");
define("PARTNER_ERROR_LISTDEVICE_FAILED", "402101:list device failed");

// store
define("STORE_ERROR_LIST_CVR_PLAN_FAILED", "402200:list cvr plan failed");
define("STORE_ERROR_CVR_PLAN_NOT_EXIST", "402201:cvr plan not exist");
define("STORE_ERROR_CVR_PLAN_INVALID", "402202:cvr plan invalid");
define("STORE_ERROR_CVR_PLAN_NOT_MATCH", "402203:cvr plan not match");
define("STORE_ERROR_CREATE_ORDER_ITEM_FAILED", "402204:create order item failed");
define("STORE_ERROR_CREATE_ORDER_FAILED", "402205:create order failed");
define("STORE_ERROR_COUPON_NOT_EXIST", "402206:coupon not exist");
define("STORE_ERROR_COUPON_INVALID", "402207:coupon invalid");
define("STORE_ERROR_COUPON_NO_AUTH", "402208:no permission to use coupon");
define("STORE_ERROR_COUPON_DISABLED", "402209:coupon disabled");
define("STORE_ERROR_COUPON_EXPIRED", "402210:coupon expired");
define("STORE_ERROR_USE_COUPON_FAILED", "402211:use coupon failed");
define("STORE_ERROR_ORDER_NOT_EXIST", "402212:order not exist");
define("STORE_ERROR_ORDER_NO_AUTH", "402213:no permission to order");
define("STORE_ERROR_ORDER_ITEM_NOT_EXIST", "402214:order item not exist");
define("STORE_ERROR_ORDER_INVALID", "402215:order invalid");
define("STORE_ERROR_ORDER_ITEM_INVALID", "402216:order item invalid");
define("STORE_ERROR_CONFIRM_ORDER_FAILED", "402217:confirm order failed");
define("STORE_ERROR_LIST_ORDER_FAILED", "402218:list order failed");
define("STORE_ERROR_DROP_ORDER_FAILED", "402219:drop order failed");
define("STORE_ERROR_INVOICE_ALREADY_EXIST", "402220:invoice already exist");
define("STORE_ERROR_CREATE_INVOICE_FAILED", "402221:create invoice failed");
define("STORE_ERROR_LIST_COUPON_FAILED", "402222:list coupon failed");
define("STORE_ERROR_ADD_COUPON_FAILED", "402223:add coupon failed");
define("STORE_ERROR_LIST_PAYCARD_FAILED", "402224:list paycard failed");
define("STORE_ERROR_ADD_PAYCARD_FAILED", "402225:add paycard failed");
define("STORE_ERROR_COUPON_RECORD_FAILED", "402226:get coupon record failed");
define("STORE_ERROR_PAYCARD_RECORD_FAILED", "402227:get paycard record failed");
define("STORE_ERROR_ORDER_ALREADY_PAID", "402228:order already paid");
define("STORE_ERROR_ORDER_PAYMENT_FAILED", "402229:order payment failed");
define("STORE_ERROR_ORDER_NOT_PAID", "402230:order not paid");
define("STORE_ERROR_ORDER_REFUND_FAILED", "402231:order refund failed");
define("STORE_ERROR_CREATE_ORDER_NOT_ALLOWED", "402232:create order not allowed");
define("STORE_ERROR_CVR_ALREADY_FREE", "402233:cvr already free");

//snapshot
define("DEVICE_ERROR_ADD_SNAPSHOT_FAILED", "402300:add device snapshot failed");
define("DEVICE_ERROR_SNAPSHOT_NOT_EXIST", "402301:device snapshot not exist");
define("DEVICE_ERROR_HANDLE_SNAPSHOT_REFUSED", "402302:handle device snapshot refused");
define("DEVICE_ERROR_DROP_SNAPSHOT_FAILED", "402303:drop device snapshot failed");
define("DEVICE_ERROR_GET_SNAPSHOT_INFO_FAILED", "402304:get device snapshot info failed");
define("DEVICE_ERROR_LIST_SNAPSHOT_FAILED", "402305:list device snapshot failed");
define("DEVICE_ERROR_GET_SNAPSHOT_TOKEN_FAILED", "402306:get device snapshot token failed");
define("DEVICE_ERROR_UPLOAD_SNAPSHOT_REFUSED", "402307:upload device snapshot refused");
define("DEVICE_ERROR_UPLOAD_SNAPSHOT_FAILED", "402308:upload device snapshot failed");
define("API_ERROR_NOTIFY_FORMAT_ILLEGAL", "402309:notify fromat failed");
define("API_ERROR_NOTIFY_SERIALIZE_ILLEGAL", "402310:notify serialize failed");

//weixin
define("WEIXIN_ERROR_NOT_BIND", "402400:weixin not bind");

//service
define("SERVICE_ERROR_LIST_MESSAGE_CONTACT_FAILED", "402500:list message contact failed");
define("SERVICE_ERROR_LIST_MESSAGE_HISTORY_FAILED", "402501:list message history failed");
define("SERVICE_ERROR_LIST_PUSH_SERVER_FAILED", "402503:list push server failed");

define("DEVICE_ERROR_UPDATE_LOCATION_FAILED", "402600:update device location failed");
define("DEVICE_ERROR_LOCATION_NOT_EXIST", "402601:delete location not exist");
define("DEVICE_ERROR_DROP_LOCATION_FAILED", "402602:drop device location failed");
define("API_ERROR_LOCATION_FORMAT_ILLEGAL", "402603:device location format illegal");
define("DEVICE_ERROR_BAD_SHARE_PASSWORD", "402604:bad share password");
define("DEVICE_ERROR_INVAILD_SHARE_PASSWORD", "402605:invalid share password");

define("DEVICE_ERROR_CONTACT_LIMIT", "402610:device contact exceed limit");
define("DEVICE_ERROR_CONTACT_NOT_EXIST", "402611:device contact not exist");
define("DEVICE_ERROR_ADD_CONTACT_FAILED", "402612:add device contact failed");
define("DEVICE_ERROR_UPDATE_CONTACT_FAILED", "402613:update device contact failed");
define("DEVICE_ERROR_DROP_CONTACT_FAILED", "402614:drop device contact failed");

define("API_ERROR_TIMEZONE_FORMAT_ILLEGAL", "402620:timezone format illegal");
define("DEVICE_ERROR_UPDATE_TIMEZONE_FAILED", "402621:update timezone failed");

define("DEVICE_ERROR_GET_AUTHCODE_FAILED", "402622:get authcode failed");
define("DEVICE_ERROR_UPDATE_AUTHCODE_FAILED", "402623:update authcode failed");
define("DEVICE_ERROR_GRANT_AUTHCODE_FAILED", "402624:grant authcode failed");

define("DEVICE_ERROR_OFFLINE", "402630:device offline");
define("DEVICE_ERROR_NOT_SUPPORT_MEDIA", "402631:device not support media");

//dvrplay
define("DEVICE_ERROR_DVRPLAY_IN_PLAY", "402660:dvr in play");
define("DEVICE_ERROR_NOTIFY_FAILED", "402661:device notify failed");
define("DEVICE_ERROR_DVRLIST_FAILED", "402663:get device dvrlist failed");
define("DEVICE_ERROR_DVRPLAY_FAILED", "402664:device dvrplay control failed");
define("DEVICE_ERROR_UPDATE_DVRPLAY_STATUS_FAILED", "402665:update device dvrplay status failed");
define("DEVICE_ERROR_DVRPLAY_IN_USE", "402666:dvr in use");

define("AUDIO_ERROR_LIST_ALBUM_FAILED", "402700:list album failed");
define("AUDIO_ERROR_GET_ALBUM_FAILED", "402701:get album info failed");
define("AUDIO_ERROR_GET_TRACK_FAILED", "402702:get track info failed");
define("AUDIO_ERROR_ALBUM_NOT_EXSIT", "402703:album not exsit");
define("AUDIO_ERROR_TRACK_NOT_EXSIT", "402704:track not exsit");
define("DEVICE_ERROR_UPDATE_PLAY_STATUS_FAILED", "402705:update play status failed");

// map
define("MAP_ERROR_LOCATION_FORMAT_ILLEGAL", "402750:map location format illegal");
define("MAP_ERROR_ADD_BUILDING_FAILED", "402751:add map building failed");
define("MAP_ERROR_BUILDING_PREVIEW_FORMAT_ILLEGAL", "402752:map building preview format illegal");
define("MAP_ERROR_ADD_BUILDING_DEVICE_FAILED", "402753:add map building device failed");
define("MAP_ERROR_ADD_BUILDING_PREVIEW_FAILED", "402754:add map building preview failed");
define("MAP_ERROR_UPDATE_BUILDING_PREVIEW_FAILED", "402755:update map building preview failed");
define("MAP_ERROR_ADD_MARKER_FAILED", "402756:add map marker failed");
define("MAP_ERROR_DROP_MARKER_FAILED", "402757:drop map marker failed");
define("MAP_ERROR_NO_UPLOAD_FILE", "402758:no upload map file");
define("MAP_ERROR_EXCEED_FILESIZE_LIMIT", "402759:esceed map filesize limit");
define("MAP_ERROR_UPLOAD_FILE_FAILED", "402760:upload map file failed");
define("MAP_ERROR_INVALID_FILE_FORMAT", "402761:invalid map file format");
define("MAP_ERROR_MOVE_FILE_FAILED", "402762:move map file failed");
define("MAP_ERROR_ADD_BUILDING_PREVIEW_FAILED", "402763:add map building preview failed");

// multiscreen
define("MULTISCREEN_ERROR_LIST_LAYOUT_FAILED", "402800:list multiscreen layout failed");
define("MULTISCREEN_ERROR_LAYOUT_FORMAT_ILLEGAL", "402801:multiscreen layout format illegal");
define("MULTISCREEN_ERROR_ADD_LAYOUT_FAILED", "402802:add multiscreen layout failed");
define("MULTISCREEN_ERROR_LAYOUT_NOT_EXIST", "402803:multiscreen layout not exist");
define("MULTISCREEN_ERROR_LAYOUT_NO_AUTH", "402804:no permission to multiscreen layout");
define("MULTISCREEN_ERROR_DROP_LAYOUT_FAILED", "402805:drop multiscreen layout failed");
define("MULTISCREEN_ERROR_LIST_DISPLAY_FAILED", "402806:list multiscreen display failed");
define("MULTISCREEN_ERROR_ADD_DISPLAY_FAILED", "402807:add multiscreen display failed");
define("MULTISCREEN_ERROR_DISPLAY_NOT_EXIST", "402808:multiscreen display not exist");
define("MULTISCREEN_ERROR_DISPLAY_NO_AUTH", "402809:no permission to multiscreen display");
define("MULTISCREEN_ERROR_ACTIVE_DISPLAY_FAILED", "402810:make multiscreen display avtive failed");
define("MULTISCREEN_ERROR_DROP_DISPLAY_FAILED", "402811:drop multiscreen display failed");

//face
define('USER_ERROR_FACE_UPLOAD_FAILED', "400291:the face has registered");
define('USER_ERROR_FACE_REGISTER_FAILED', "400292:face register failed");
define('USER_ERROR_FACE_LIST_FAILED', "400293:get facelist failed");
define('USER_ERROR_FACE_UPDATE_FAILED', "400294:face UPDATE failed");
define('USER_ERROR_FACE_DEL_FAILED', "400295:face drop failed");
define('USER_ERROR_FACE_NO_EXIST', "400296:there is no face exist");
define('USER_ERROR_FACE_TOO_MANY', "400297:Please upload a picture with only one face");
define('USER_ERROR_EVENT_UPDATE_FAILED', "400298:Event update filed");
define('USER_ERROR_FACE_TOO_BLUR', "400299:The face blur");
define('USER_ERROR_FACE_INFO_FAILED', "400290:get face info failed");

define('USER_ERROR_OBJECT_NOT_EXIST', "401416:The operation object does not exist");
define('USER_ERROR_BJECT_UPPER_LIMIT', "401417:operation count has exceeded the upper limit");


define('USER_ERROR_GET_PUSH_LIST_FAILED', "400301:get face push list faild");
define('USER_ERROR_GET_EVENT_LIST_FAILED', "400302:get event list faild");
define('USER_ERROR_EVENT_PUSH_FAILED', "400303:set event push faild");
define('USER_ERROR_FACE_COUNT_FAILED', "400304:get face count faild");
define('USER_ERROR_MERGE_LIST_FAILED', "400305:get merge list faild");
define('USER_ERROR_MERGE_UPDATE_FAILED', "400306:merge update faild");
define('USER_ERROR_MERGE_EVENT_FAILED', "400307:event merge faild");
define('USER_ERROR_MERGE_CANCLE_FAILED', "400308:merge cancle faild");


define('UPLOAD_ACTIVITY_ERROR', "400301:upload activity file fail");

//签到墙error
define('SIGNIN_MEMBER_ERROR', "401302:signin meeting member fail");
define('ADD_MEMBER_ERROR', "401303:add meeting member fail");
define('MEETING_LIST_ERROR', "401304:get meeting list fail");
define('ADD_MEETING_ERROR', "401305:add meeting fail");
define('DROP_MEETING_ERROR', "401306:drop meeting fail");

// 签到机
define('SIM_ERROR_ALREADY_REG', "401400:sim already registerd");
define('SIM_ERROR_ADD_FAILED', "401401:sim add failed");
define('SIM_ERROR_NOT_EXIST', "401402:sim not exist");
define('SIM_ERROR_NO_AUTH', "401403:no auth");
define('SIM_ERROR_DROP_FAILED', "401404:sim drop failed");
define('SIM_ERROR_DEVICE_ALREADY_BIND', "401405:device already bind");

//民政通
define('GET_MZT_DATA_ERROR', "401600:get mzt data failed");
