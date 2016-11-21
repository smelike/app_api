<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 8/24/16
 * Time: 6:51 PM
 */

namespace App\Http\Controllers\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Validator;

class RoleController extends Controller
{

    private $_table = 'lamp_user';

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->roleAuth();
    }
    private function dataRecord($arr_data)
    {
        $arr_return = array(
            'name'  => $arr_data['name'],
            'tel'   => $arr_data['tel'],
            'position' => $arr_data['position'],
            'status'   => '0',
            'updateTime'   => empty($arr_data['updateTime']) ? '' : $arr_data['updateTime']
        );
        empty($arr_data['password']) ? '' : $arr_return['password'] = $arr_data['password'];
        empty($arr_data['createTime']) ? '' : $arr_return['createTime'] = $arr_data['createTime'];
        empty($arr_data['updateTime']) ? '' : $arr_return['updateTime'] = $arr_data['updateTime'];

        return array('table' => 'user', 'pkey' => 'id', 'data' => $arr_return);
    }
    private function validateInput($request, $arr_fields)
    {
        $messages = [
            'tel.required' => '电话号码不能为空',
            'tel.regex'    => '电话号码格式不正确',
            'password.required' => '密码不能为空',
            'position.required' => '权限设置不能为空',
            'position.in' => '权限设置格式不正确',
            'staffIds.required' => '员工 ID不能为空',
            'staffIds.array' => '员工 ID 必须为数组格式',
            'name.required' => '员工姓名不能为空',
        ];

        $arr_rules = [
            'tel' => array('required','regex:/^1\d{10}$/i'),
            'password' => 'required',
            'position' => 'required|in:0,5,10',
            'staffIds' => 'required|array',
            'name' => 'required'
        ];

        $arr_validate_rule = [];
        foreach($arr_fields as $key) {
            $arr_validate_rule[$key] = $arr_rules[$key];
        }

        $validator = Validator::make($request->all(), $arr_validate_rule, $messages, array_values($arr_rules));
        return $this->messageBag($validator->errors());
    }

    /*  @创建角色
     *  @param $request
     *  @return array
     * */
    public function create(Request $request)
    {
        $this->roleAuth();
        $arr_validate_field = array('tel', 'password', 'position');
        $arr_return = $this->validateInput($request, $arr_validate_field);

        if (empty($arr_return))
        {
            $arr_return = $this->insertRole($request);
        } else {
            $arr_return = ['errmsg' => array_shift($arr_return)];
        }

        return $this->responseJson($arr_return);
    }
    /*  @添加角色
     *  @param $request
     *  @return array
     * */
    private function insertRole($request)
    {
        $arr_data = [
            'password' => $request->input('password'),
            'token' => uniqid('new_'),
            'name' => $request->input('tel'),
            'tel' => $request->input('tel'),
            'position' => $request->input('position'),
            'createTime' => date('Y-m-d H:i:s'),
            'updateTime' => date('Y-m-d H:i:s')
        ];
        $role = DB::table('user')->where('tel', $request->input('tel'))->first();

        if (empty($role))
        {
            $arr_role = $this->insertAndReturnRow($this->dataRecord($arr_data));
            unset($arr_role->token, $arr_role->password, $arr_role->status);
            $arr_return = ['msg' => $arr_role];
        } else {
            $arr_return = ['errmsg' => '该手机号码已存在,不能重复添加'];
        }

        return  $arr_return;
    }
    /*  @编辑用户角色
     *  @param $request
     *  @param $id
     *  @return array
     * */
    public function edit(Request $request, $id = '')
    {
        $arr_validate_field = array('tel', 'name', 'position');
        $arr_return = $this->validateInput($request, $arr_validate_field);

        if (empty($arr_return)) {

            $arr_return = $this->editRole($request, $id);
        } else {
            $arr_return = ['errmsg' => array_shift($arr_return)];
        }

        return $this->responseJson($arr_return);
    }
    /*  @编辑用户角色
     *  @param $request
     *  @param $id
     *  @return array
     * */
    private function editRole($request, $id)
    {
        $id = (int) $id;

        if ($id) {

            $arr_user = DB::table('user')->where('id', $id)->first();
            if ($arr_user) {
                $ret = $this->updateInfo($request, $id);
                $new_user = DB::table('user')->where('id', $id)->first();
                unset($new_user->password);
                unset($new_user->token);
                $arr_return =  $ret ? ['msg' => $new_user] : ['errmsg' => '销售人员资料更新失败'];
            } else {
                $arr_return = ['errmsg' => '该用户不存在'];
            }
        } else {
            $arr_return = ['errmsg' => '传送参数格式不正确'];
        }

        return $arr_return;
    }
    /*  @更新用户角色(员工,经理,管理员)/重置密码(123456)
     *  @param $request
     *  @param $id
     *  @return boolean
     * */
    private function updateInfo($request, $id)
    {
        $arr_data = [
            'tel' => $request->input('tel'),
            'name' => $request->input('name'),
            'token' => uniqid('update_'),
            'position' => $request->input('position'),
            'updateTime' => date('Y-m-d H:i:s')
        ];

        if ($request->input('resetpass')) {
            $arr_data['password'] = md5(12345678);
        }

        return DB::table("user")->where('id', $id)->update($arr_data);
    }
    /*  @员工离职
     *  @param $request
     *  @return array
     * */
    public function del(Request $request)
    {

        $arr_validate_field = ['staffIds'];
        $arr_return = $this->validateInput($request, $arr_validate_field);

        $staffIds = $request->input('staffIds');
        $maxUserId = DB::table("user")->max("id");

        if (empty($arr_return) AND count($staffIds))
        {
            foreach ($staffIds as &$id) {
                if ((int) $id AND ($id < $maxUserId)) {
                    $id = (int) $id;
                }
            }
            $return = DB::table("user")->whereIn('id', $staffIds)->update(['status' => '1']);
            $arr_return = $return ? ['msg' => $this->_msg] :  ['errmsg' => '人员离职设置失败'];
        } else {
            $arr_return = ['errmsg' => '参数格式错误'];
        }

        return $this->responseJson($arr_return);
    }

    /* @查询在职员工
     * @param $request
     * @return array
    */
    public function query(Request $request)
    {
        $per = 13;
        $pageNo = (int) $request->input('pageNo');
        $pageNo = $pageNo ? $pageNo : 1;

        $count = DB::table('user')->where('status', '0')->count();
        $where = 'status = 0';
        $arr_staff = $this->pagination($this->_table, $where, $count, $pageNo);
        foreach($arr_staff as &$staff)
        {
            unset($staff->token);
            unset($staff->password);
        }

        $rows = $pageNo * $per;
        $arr_return['msg']['more'] = $count < $rows ? 0 : 1;
        $arr_return['msg']['staffs'] = $arr_staff;
        return $this->responseJson($arr_return);
    }
}