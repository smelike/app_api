<?php
    /**
     * Created by PhpStorm.
     * User: HSF
     * Date: 2016/6/1
     * Time: 9:56
     */

    namespace App\Http\Controllers\User;

    use App\Http\Controllers\Controller;
    use App\Http\Controllers\Wxpay\Jsapi;
    use App\Http\Controllers\Wxpay\JsApiPay;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Http\Request;
    use Validator;

    class UserController extends Controller
    {

        public  function register(Request $request)
        {

            exit;
            $data = $request->all();
            // root & 12345678
            //if (($data['name'] == 'root') AND (md5($data['pwd']) == '25d55ad283aa400af464c76d713c07ad'))
            //{
                $arr_data = [
                   'id' => '',
                   'userAccount' => rand(6666, 999999),
                   'password' => '12345678',
                   'token'  => '12345678',
                   'createTime' => date('Y-m-d H:i:s')
                ];
                $return = DB::table('user')->insertGetId($arr_data);
                if ($return) {
                    $arr_user_info = DB::table("user")->where('id', $return)->first();
                    return $this->responseJson(get_object_vars($arr_user_info));
                }
            //}
        }

        /*  @用户登录
         *  @param $request
         *  @return array
         * */
        public function login(Request $request)
        {

            $messages = [
                'tel.required' => '登陆号码不能为空',
                'tel.regex' => '请输入正确的电话号码',
                'password.required' => '密码不能为空',
                'password.alpha_dash' => '非法的密码'
            ];
            $arr_rules = [
                'tel' => array('required','regex:/^1\d{10}$/i'),
                'password' => 'required|alpha_dash'
            ];
            $return = $this->commonValidate($request, $arr_rules, $messages);

            if (empty($return)) {
                $return = $this->verifyLogInfo($request);
            }

            return $this->responseJson($return ? $return : []);
        }

        /*  @校验用户登录
         *  @param $request
         *  @return mixed(false/array)
         * */
        private function verifyLogInfo($request)
        {

            $tel = $request->tel;
            $password = $request->password;

            $user = DB::table('user')->where('tel', $tel)->first();
            if (empty($user))
            {
                $arr_error = ['errmsg' => '你输入的该用户不存在'];
                return $this->responseJson($arr_error);
            }

            if (($user->status == 1))
            {
                $arr_error = ['errmsg' => '该帐号已经被停用'];
            }

            if (empty($user->status))
            {
                if (($user->password != $password))
                {
                    $arr_error = ['errmsg' => '输入的密码有误'];
                } else {
                    $token = uniqid('login_');
                    $where = ['tel' => $tel, 'password' => $password];
                    $update = DB::table('user')->where($where)->update(['token' => $token]);

                    if ($update) {
                        $arr_error = [
                                'msg' => [
                                    'id' => $user->id,
                                    'token' => $token,
                                    'role' => $user->position,
                                    'name' => $user->name ? $user->name : $user->tel
                                ]
                        ];
                    }
                }
            }

            return empty($arr_error) ? false : $arr_error;
        }

        /*  @用户登出
         *  @param void
         *  @return array
         * */
        public function logout()
        {
            $arr_return = [];
            $updateColumn = ['token' => uniqid('logout_')];
            $return = DB::table("user")->where('token',$this->_token)->update($updateColumn);

            if(empty($return)) {
                $arr_return = ['errmsg' => '退出失败，刷新重试'];
            }

            return $this->responseJson($arr_return);
        }

        /*  @重置用户登录密码
         *  @param $user_id
         *  @return array
         * */
        public function resetpwd($user_id = 0)
        {
            $arr_return = [];
            $user_id = (int) $user_id;

            if ($user_id)
            {
                $where = [['id', '=', $user_id]];
                $user = DB::table("user")->where($where)->first();

                $updateColumn = ['password' => md5('123456')];
                if ($user) {
                    DB::table('user')->where($where)->update($updateColumn);
                }
            } else {
                $arr_return = ['errmsg' => '参数不正确'];
            }

            return $this->responseJson($arr_return);
        }

        /*  @修改密码
         *  @param $request
         *  @return mixed
         * */
        public function changePassword(Request $request)
        {

            $user = DB::table('user')->where('id', $this->getUserId())->first();

            if ($user AND $request->oldPassword AND $request->newPassword)
            {
                if ($request->oldPassword == $user->password)
                {
                    // 前端传送加密后的密码
                    $updateData = ['password' => $request->newPassword];
                    $update = DB::table('user')->where('id', $user->id)->update($updateData);
                    $arr_return = $update ? ['msg' => '修改密码成功'] :  ['errmsg' => '密码修改失败'];
                } else {
                    $arr_return = ['errmsg' => '原始密码不正确,请重新输入'];
                }
            } else {
               $arr_return = ['errmsg' => '接口参数不正确'];
            }
            return $this->responseJson($arr_return);
        }

        /* @修改个人姓名
         * @param $request
         * @return mixed
         * */
        public function editProfile(Request $request)
        {
            $arr_return = [];

            if ($request->name)
            {
                $update = ['name' => $request->name];
                try {
                    DB::table('user')->where('id', $this->getUserId())->update($update);
                } catch(\Exception $e) {
                    $arr_return = ['errmsg' => '编辑用户名失败'];
                }
            } else {
                $arr_return = ['errmsg' => '接口参数不正确'];
            }

            return $this->responseJson($arr_return);
        }
    }