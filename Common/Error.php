<?php

/**
 * Created by John.
 * User: John
 * Date: 2016/3/1
 * Time: 15:35
 */
namespace App\Common;

class ERR {

	public static $g_wkm = "t1";

	// 构造函数
	public static $errCode = array(

		//**START**
		//success
		['ok', '0000', 'SUCCESS', '操作成功'],
		// Database
		['ERR_DB', '1000', 'Database error.', '数据库错误'],
		// Application
		['ERR_APP', '2000', 'Application error.', '应用错误'],

		// User Module
		['ERR_USER', '20000', 'User module\'s error', '用户模块错误'],
		// Lamp Module
		['ERR_USER', '30000', 'Lamp module\'s error', '灯具模块错误'],
		// Order Module
		['ERR_USER', '40000', 'Order module\'s error', '订单模块错误'],



		// User module
		//register
		['ERR_TEL_FORMAT', '20001', 'tel format error.', '请输入合法手机号！'],
		['ERR_TEL_EXIST', '20002', 'tel exist.', '手机号已存在！'],
		['ERR_AUTH', '200003', 'you are not authorized.', '你没有权限查看,请联系管理人员.'],
		//login
		['ERR_TEL_PWD', '20004', 'login tel or pwd error.	', '手机号码或密码错误，请重新登陆！'],
		['ERR_TEL_NOT_EXIST', '20005', 'login tel not exist.	', '手机号码不存在，请前往注册！'],

		//logout
		['CUSTOMER_LOGIN_STATUS_EXCEPTION', '20006', 'User login exception', '用户登录状态异常'],
		['ERR_CUSTOMER_NOT_LOGIN', '20007', 'User not login.', '用户未登陆'],
		['ERR_CUSTOMER_LOGOUT_AGAIN', '20008', 'User logout again.', '用户重复提交退出'],
		['CUSTOMER_NEVER_LOGIN', '20009', 'User never login.', '用户从未登陆,请注册或登陆'],

		['ERR_PWD', '20010', 'modify pwd error.', '原密码错误，请重新操作！'],
		['ERR_PWD_MODIFIED_TIME', '20011', 'password modified error.', '密码已修改，请7天后重试'],

		//login
		['Please Login'				, '20012', 'not login.' 				, '请登陆'],
		['ERR_CAPTCHA'				, '20013', 'captcha error.' 			, '验证码错误'],
		['ERR_USER_NAME'			, '20014', 'user name error.' 		, '用户名错误'],
		['ERR_USER_PASSWORD'		, '20015', 'user password error.' 	, '用户密码错误'],

		//Upload
		['ERR_FILE_NOT_EXITS'		, '30001', 'upload file not exist.' 	, '请选择一张图片'],
		['ERR_FILE_MIME'			, '30002', 'upload file mime error.' , '上传文件类型不正确'],
		['ERR_FILE_UPLOAD'			, '30003', 'file upload error.' 		, '文件上传失败，请重试'],

		//SYSTEM
		['ERR_SYSTEM', '8888', 'system error.' , '系统错误'],
		//--END--
		['ERR_UNKNOWN', '9999', 'unknown error.', '未知的错误'],

	);

	public function __construct() {
		//parent::__construct();
	}
	// 错误信息语言
	//const ERR_MSG_LANGUAGE = 'ENG';

	const ERR_MSG_LANGUAGE = 'CHN';


	/**
	 * 根据错误串，返回错误代码和错误信息
	 *
	 * @param $var [in] 错误串
	 * @param string $lang [in] 错误信息的语言('ENG', 'CHN')
	 * @return array 将错误代码和错误信息以数组的方式返回
	 */
	public static function GetError($var, $lang = self::ERR_MSG_LANGUAGE) {
		//$value = array_get(self::$errCode, $var);
		//$rvalue = array_shift($value);

		$count = count(self::$errCode);
		for ($x = 0; $x < $count; $x++) {
			if (trim(self::$errCode[$x][0]) == trim($var)) {
				break;
			}

		}

		if ($x >= $count) {
			$x = $count - 1;
		}

		if ($lang == 'CHN') {
			$rvalue = array('err_code' => self::$errCode[$x][1], 'err_msg' => self::$errCode[$x][3]);
		} else {
			$rvalue = array('err_code' => self::$errCode[$x][1], 'err_msg' => self::$errCode[$x][2]);
		}

		return $rvalue;
	}
}
