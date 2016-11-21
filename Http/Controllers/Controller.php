<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;


class Controller extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests;

    public $_token;
    public $_msg;
    private $_noNeedToken = [
        'user/login',
        'admin',
        'admin/order',
        'admin/logout'
    ];

    public function __construct(Request $request)
    {
        date_default_timezone_set("Asia/Shanghai");
        $this->middleware('guest');

        $this->_msg = new \stdClass();

        $this->verifyToken($request);

        $request_body = array(
            'time' => date('Y-m-d H:i:s'),
            'class_fun' => __CLASS__ . __FUNCTION__,
            'request' => 'request__' . var_export($request->all(), true),
            'token' => "token__" . $request->header('token')
        );

        logger(join("\t", $request_body));
        $request_header = array(
            'time' => date('Y-m-d H:i:s'),
            'class_fun' => __CLASS__ . __FUNCTION__,
            'header' => 'header__' . var_export($request->header(), true),
            'token' => "token__" . $request->header('token')
        );
        logger(join("\t",$request_header));
    }

    /*  @接口返回响应
     *  @param $arr_data
     *  @return json
     * */
    public function responseJson($arr_data = array())
    {
        $arr_default = ['status' => 0, 'msg' => $this->_msg, 'errmsg' => ''];

        if (is_array($arr_data)) {

            if (array_intersect_key($arr_data, $arr_default)) {
                $arr_default = array_merge($arr_default, $arr_data);
            }
        } else {
            $arr_default['msg'] = $arr_data;
        }

        $arr_default['status'] = empty($arr_default['errmsg']) ?  0 : 1;
        logger(__CLASS__ . __FUNCTION__ .var_export($arr_default, true));
        return response()->json($arr_default);
    }

    /*  @判断用户的角色权限
     *  @param void
     *  @return json
     * */
    public function roleAuth()
    {
        $user = $this->getUserByToken($this->_token);
        if ($user AND $user->position > 5) {
            header('content-type:application/json');
            $arr_return = [
                'status'=> 1,
                'msg' => $this->_msg,
                'errmsg' => '你没有权限查看,请联系管理人员.'
            ];
            echo json_encode($arr_return);
            exit;
        }
    }

    protected function messageBag(\Illuminate\Support\MessageBag $messageBag)
    {
        $arr_return = [];

        foreach ($messageBag->keys() as $key)
        {
            $arr_return[$key] = $messageBag->first($key);
        }

        return  empty($arr_return) ? false : $arr_return;
    }

    /*  @将对象转化为关联数组
     *  @param $arr_object
     *  @return array
     * */
    public function getAssocDataByObject($arr_object)
    {
        $arr_return = [];
        if (is_array($arr_object) AND isset($arr_object[0])) {
            foreach($arr_object as $object)
            {
                $arr_return[] = get_object_vars($object);
            }
        } else {
            if (is_object($arr_object)) {
                $arr_return = get_object_vars($arr_object);
            } else {
                $arr_return = $arr_object;
            }
        }

        return $arr_return;
    }

    /*  @数据校验
     *  @param $request
     *  @param $arr_rules
     *  @param $messages
     *  @return boolean / string
     * */
    public function commonValidate($request, $arr_rules, $messages)
    {
        $validator = Validator::make($request->all(), $arr_rules, $messages, array_values($arr_rules));
        $arr_errors = $this->messageBag($validator->errors(), $arr_rules);

        $ret = false;
        if ($arr_errors) {
            $ret = ['errmsg' => array_shift($arr_errors)];
        }
        return $ret;
    }

    /*  @用户 token 校验
     *  @param $request
     *  @return json
     * */
    public function verifyToken($request)
    {
        $url = $request->all()['s'];
        $reg = '/^\/lam\/admin\/printf\/\d+$/';
        $return = preg_match($reg, $url, $matches);
        $in_array = in_array($url, $this->_noNeedToken);

        if (($in_array == false) OR $return)
        {
            header('content-type:application/json');
            $arr_error = ['status' => 1, 'msg' => $this->_msg, 'errmsg' => 'Token 不能为空'];

            if ($request->header('token'))
            {
                $arr_error['status'] = 0;
                $arr_error['errmsg'] = '';
                $this->_token = $request->header('token');

                if(!$this->getUserByToken($this->_token))
                {
                    $arr_error = ['status' => 1, 'msg' => $this->_msg, 'errmsg' => '当前用户不存在，请刷新重试'];
                }
            }

            if ($arr_error['status'] == 1) {
                $ret = json_encode($arr_error);
                exit($ret);
            }
        }
    }
    /**Created By River
     * @param void
     * @return array|bool
     */
    public function validateToken()
    {
        $msg = $this->_msg;
        $token = $this->_token;

        if($token == '') {
            return ['status' => 1, 'msg' => $msg, 'errmsg' => 'River - Token 不能为空'];
        }
        $user = DB::table('user')->where('token', $token)->first();
        if(!$user){
            return ['status' => 1, 'msg' => $msg, 'errmsg' => '当前用户不存在，请刷新重试'];
        }
        return $token;
    }
    /*  @获取 User ID
     *  @param void
     *  @return integer / boolean
     * */
    protected function getUserId()
    {
        $user = $this->getUserByToken($this->_token);
        return $user ? $user->id : false;
    }
    private function getUserByToken($token)
    {
        return DB::table('user')->where('token', $token)->first();
    }

    /*  @插入记录并返回
     *  @param $arr_data
     *  @return mixed
     * */
    public function insertAndReturnRow($arr_data)
    {
        $pkey = empty($arr_data['pkey']) ? "id" : $arr_data['pkey'];
        $id = DB::table($arr_data['table'])->insertGetId($arr_data['data']);

        if ($id) {
            $return = DB::table($arr_data['table'])->where($pkey, $id)->first();
        }
        return $id ? $return : false;
    }

    /*  @分页[参数太多了]
     *  @param $table [表名]
     *  @param $where [条件]
     *  @param $count [总记录数]
     *  @param $page_no [第几页]
     *  @param $per [每页记录数]
     *  @param $arr_field [数据字段]
     *  @param $order_by [default = `createTime` desc]
     *  @return array
     * */
    public function pagination($table, $where, $count, $page_no, $per = 13, $arr_field = array(), $order_by = "`createTime` desc")
    {
        $total = ceil($count / $per);

        if ($page_no > $total) return [];
        $start = ($page_no - 1) * $per;
        if ($arr_field) {
            $fieldString = join(",", $arr_field);
            $sql = "select {$fieldString} from {$table}";
        } else {
            $sql = "select * from {$table}";
        }

        if ($where) {
            $sql .= " where " . $where;
        }

        $sql .= " order by $order_by ";
        $sql .= " limit {$start}, $per ";
        return DB::select($sql);
    }

    /*  @查询有效的灯具
     *  @param $lightId
     *  @param $arr_field
     *  @param $order [是否返回给订单接口使用的,如果为真,即使是删除的灯具也返回灯具]
     *  @return mixed
     * */
    public function light_detail($lightId, $arr_field = [], $order = false)
    {

        $where = [
            ['id', '=', $lightId],
            ['status', '=', 0]
        ];

        if ($order) {
            array_pop($where);
        }

        if ($arr_field) {
            $info = DB::table('light')->where($where)->first($arr_field);
        } else {
            $info = DB::table('light')->where($where)->first();
        }

        if ($info) {
            isset($info->images) ? $info->images = json_decode($info->images, true) : "";
            isset($info->subSwitch) ? $info->subSwitch = json_decode($info->subSwitch, true) : "";
            isset($info->describe) ? $info->describe = json_decode($info->describe, true) : "";
        } else {
            $info = $this->_msg;
        }
        return $info;
    }

    /*  @查询有效的客户
     *  @param $customerId
     *  @param $arr_field
     *  @param $salesmanName
     *  @return mixed
     * */
    public function order_customer($customerId, $arr_field = [], $salesmanName = true)
    {
        $where = [
            ['id', '=', $customerId],
            ['status', '=', 0]
        ];

        if ($arr_field) {
            array_push($arr_field, 'salesmanId');
            $customer = DB::table('customer')->where($where)->first($arr_field);
        } else {
            $arr_default_field = ['id', 'salesmanId', 'name', 'tel', 'addrs', 'salesmanId'];
            $customer = DB::table('customer')->where($where)->first($arr_default_field);
        }

        if (isset($customer->addrs)) {
            $customer->addrs = json_decode($customer->addrs, true);
        }

        if ($salesmanName AND $customer)
        {
            $salesman = DB::table("user")->where('id', $customer->salesmanId)->first(['tel', 'name']);
            $customer->salesmanName = $salesman->name ? $salesman->name : $salesman->tel;
            //unset($customer->salesmanId);
        }
        return $customer ? $customer : $this->_msg;
    }

    /* @以订单 ID 查询订单
     * @param $order_id
     * @param $arr_field
     * @return mixed
     * */
    public function getOrderById($order_id, $arr_field = [])
    {
        $where = [
            ['status', '=', 0],
            ['id', '=', $order_id]
        ];
        if ($arr_field) {
            $order = DB::table('order')->where($where)->first($arr_field);
        } else {
            $order = DB::table('order')->where($where)->first();
        }
        return $order;
    }

    /*  @查询购物车详情
     *  @param $cartId
     *  @param $arr_field
     *  @return mixed
     * */
    public function queryCartDetail($cartId, $arr_field = [])
    {
        $where = [['cartId', '=', $cartId]];
        if ($arr_field) {
            $cart = DB::table('cart_detail')->where($where)->get($arr_field);
        } else {
            $cart = DB::table('cart_detail')->where($where)->get();
        }
        return $cart;
    }

    public function orderDetailInfo($orderId)
    {

        $queryField = ['lightId', 'qty', 'updateTime'];
        if (is_array($orderId) AND $orderId)
        {
            $order_detail = DB::table('order_detail')->whereIn('orderId', $orderId)->get($queryField);
        } else {
            $order_detail = DB::table('order_detail')->where('orderId', $orderId)->get($queryField);
        }

        if ($order_detail) {
            foreach ($order_detail as &$detail) {
                $arr_field = ['name', 'lightNo', 'price', 'images'];
                $light_detail = $this->light_detail($detail->lightId, $arr_field, $order = true);
                $light_detail->lightId = $detail->lightId;
                $detail->light = $light_detail;
                unset($detail->lightId);
            }
            return $order_detail;
        }
        return [];
    }

    public function queryCustomerComment($customerId)
    {
        $where = [
            ['salesmanId', '=', $this->getUserId()],
            ['customerId', '=', $customerId]
        ];
        $memo = DB::table('comment')->where($where)->get(['comment']);
        return $memo ? $memo : [];
    }

}