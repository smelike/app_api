<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 8/28/16
 * Time: 6:07 PM
 */

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Validator;

class CustomerController extends Controller
{

    private $_table = 'lamp_customer';
    private $_fields = array(
        'id' => '',
        'salesmanId' => '',
        'name' => '',
        'tel' => '',
        'addrs' => '',
        'createTime' => '',
        'updateTime' => ''
    );

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    private function userCommValidate($request, $arr_field)
    {
        $messages = [
            //'id.required' => '客户 ID 不能为空',
            //'id.integer' => '客户 ID 必须为整数',
            'tel.required' => '账号不能为空',
            'tel.regex' => '电话号码格式不正确',
            'name.required' => '客户名字不能为空',
            'name.between' => '客户名字长度必须为 2~20',
            'addrs.required' => '客户收货地址不能为空',
            'addrs.array' => '客户收货地址格式不正确'
        ];

        $arr_rules = [
            //'id' => 'required|integer',
            'tel' => array('required','regex:/^1\d{10}$/i'),
            'name' => 'required|between:2,20',
            'addrs' => 'required|array'
        ];

        $arr_validate_rule = [];
        foreach($arr_field as $key) {
            if (array_key_exists($key, $arr_rules)) {
                $arr_validate_rule[$key] = $arr_rules[$key];
            }
        }

        if ($arr_validate_rule) {
            $validator = Validator::make($request->all(), $arr_validate_rule, $messages, array_values($arr_rules));
            return $this->messageBag($validator->errors());
        }

        return false;
    }
    private function initData($request)
    {
        $arr_data = [];
        foreach ($this->_fields as $field => $default) {

            $value = $request->input($field);
            if (is_array($value)) {
                $value = json_encode($value, JSON_FORCE_OBJECT);
            }
            $arr_data[$field] = empty($value) ? $default : $value;
        }

        return $arr_data;
    }

    /*  @添加客户
     *  @param  $request
     *  @return array
     * */
    public function  create(Request $request)
    {
        $this->roleAuth();
        $arr_field = array_keys($this->_fields);
        array_shift($arr_field);
        $arr_error  = $this->userCommValidate($request, $arr_field);

        if (empty($arr_error))
        {
            $arr_return = $this->insertCustomer($request);
        } else {
            $arr_return = ['errmsg' => array_shift($arr_error)];
        }

        return $this->responseJson($arr_return);
    }

    private function insertCustomer($request)
    {
        $arr_data = $this->initData($request);
        $arr_data['createTime'] = date('Y-m-d H:i:s');
        $arr_data['salesmanId'] = $this->getUserId();
        array_shift($arr_data);

        $customer = $this->customerIsExit($arr_data['tel']);
        if ($customer)
        {
            $customer = $this->customerInfo($customer->id);
            $arr_return = ['msg' => $customer];
        } else {
            $arr_insert = ['table' => 'customer', 'pkey' => 'id', 'data' => $arr_data];
            $customer = $this->insertAndReturnRow($arr_insert);
            $customer = $this->customerInfo($customer->id);
            empty($customer) ? $arr_return = ['errmsg' => '添加客户信息失败'] : $arr_return = ['msg' => $customer];
        }

        return $arr_return;
    }
    private function customerIsExit($mobile)
    {
       return DB::table('customer')->where("tel", $mobile)->first();
    }
    /*  @编辑客户
     *  @param $request
     *  @param $id
     *  @return array
     * */
    public function edit(Request $request, $id)
    {
        $this->roleAuth();
        $arr_field = array_keys($this->_fields);
        $arr_error  = $this->userCommValidate($request, $arr_field);

        $customerId = (int) $id;
        if (empty($arr_error) AND $customerId)
        {
            if ($this->updateCustomer($request, $customerId)) {
                $arr_return = ['msg' => $this->customerInfo($customerId)];
            } else {
                $arr_return = ['errmsg' => '编辑客户信息失败'];
            }
        } else {
            $arr_return = ['errmsg' => array_shift($arr_error)];
        }

        return $this->responseJson($arr_return);
    }
    private function customerInfo($customerId)
    {
        $where = [
            ['id', '=', $customerId],
            ['status', '=', 0]]
        ;
        $customer = DB::table('customer')->where($where)->first();

        if ($customer)
        {
            $customer->addrs = json_decode($customer->addrs, true);

            $where = [
                ['id', '=', $customer->salesmanId],
                ['status', '=', 0]
            ];
            $salesman = DB::table('user')->where($where)->first();
            $customer->salesmanName = '';
            if ($salesman)
            {
                $customer->salesmanName = $salesman->name ? $salesman->name : $salesman->tel;
            }
            return $customer;
        }
        return $this->_msg;
    }
    private function updateCustomer($request, $customerId)
    {
        $arr_data['id'] = $customerId;
        $arr_data['tel'] = $request->input('tel');
        $arr_data['name'] = $request->input('name');
        $arr_data['addrs'] = json_encode($request->input('addrs'), JSON_FORCE_OBJECT);
        $arr_data['updateTime'] = date('Y-m-d H:i:s');

        if ($this->customerInfo($arr_data['id'])) {
            return DB::table('customer')->where('id', $arr_data['id'])->update($arr_data);
        }
        return false;
    }

    /*  @获取某个 ID 的客户信息
     *  @param $id
     *  @param $request
     *  @return array
     * */
    public function info(Request $request, $id = '')
    {
        $customerId = (int) $id;
        if ($customerId)
        {
            $customer = $this->customerInfo($customerId);
            if ($customer) {
                $orders = $this->customersHisOrder($customerId);
                $customer->orders = $orders;
            }
            $arr_return = ['msg' => $customer];
        } else {
            $arr_return = ['errmsg' => '参数格式不正确'];
        }
        return $this->responseJson($arr_return);
    }

    /*  @客户的历史订单
     *  @param $customerId
     *  @return array
     * */
    private function customersHisOrder($customerId)
    {
        //return app('App\Http\Controllers\Order\OrderController')->myorderbytoken($request, $customerId);
        //dd($customerId);
        $where = [
            ['customerId', '=', $customerId],
            ['status', '=', 0]
        ];
        $order_field = ['id', 'orderNo', 'discount', 'oddCut', 'totalPrice', 'payStatus', 'createTime', 'updateTime'];
        $orders = DB::table("order")->where($where)->get($order_field);

        foreach($orders as &$order) {
            $order->items = $this->orderDetailInfo($order->id);
        }
        return $orders;
    }

    /*  @查询客户信息 [该接口响应慢,需要找原因]
     *  @param $request
     *  @return array
     * */
    public function query(Request $request)
    {

        $query = $request->input('query');
        $pageNo = $request->input('pageNo');
        $pageNo = (int) $pageNo ? $pageNo : 1;
        if ($query) {
            $arr_customers = $this->querybyTelOrName($query, $pageNo);
        } else {
            $arr_customers = $this->queryAll($pageNo);
        }

        return $this->responseJson($arr_customers);
    }

    private function querybyTelOrName($query, $page_no = 1, $per = 13)
    {
        $arr_return = [];
        $query_format = (int) $query;
        if ($query_format) {
            $searchField = 'tel';
            $where = [
                [$searchField, "like", "{$query_format}%"],
                ['status', '=', 0]
            ];
            $likeField = $query_format;
        } else {
            $searchField = 'name';
            $where = [
                [$searchField, "like", "{$query}%"],
                ['status', '=', 0]
            ];
            $likeField = $query;
        }

        $count = DB::table("customer")->where($where)->count();
        $where = "{$searchField} like '{$likeField}%' and status = 0";
        $customers = $this->pagination($this->_table, $where, $count, $page_no);

        $rows = $page_no * $per;
        $arr_return['msg']['more'] = $count < $rows ? 0 : 1;

        if ($customers) {
            $arr_return['msg']['customers'] = $this->decode_serialize($this->getAssocDataByObject($customers));
        } else {
            $arr_return['msg']['customers'] = [];
        }

        return $arr_return;
    }

    private function queryAll($page_no = 1, $per = 13)
    {
        $page_no =  (int) $page_no  ? $page_no : 1;
        $count = DB::table("customer")->where('status', 0)->count();
        $where = 'status = 0';
        $arr_customers = $this->pagination($this->_table, $where, $count, $page_no);
        $rows = $page_no * $per;
        $arr_return['msg']['more'] = $count < $rows ? 0 : 1;

        if ($arr_customers) {
            $arr_return['msg']['customers'] = $this->decode_serialize($this->getAssocDataByObject($arr_customers));
        } else {
            $arr_return['msg']['customers'] = [];
        }

        return $arr_return;
    }
    private function decode_serialize($arr_customers)
    {
        if (array_key_exists(0, $arr_customers)) {
            $arr_valide_customers =[];
            foreach($arr_customers as $c) {
                if (empty($c['status'])) {
                    $c['addrs'] = empty($c['addrs']) ? [] : json_decode($c['addrs'], true);
                    $salesman = DB::table('user')->where('id', $c['salesmanId'])->first();
                    $c['salesmanName'] = is_null($salesman) ? '' : ($salesman->name ? $salesman->name : $salesman->tel);
                    $arr_valide_customers[] = $c;
                }
            }
            $arr_customers = $arr_valide_customers;
        } else if ($arr_customers){
            if ($arr_customers['status']) {
                unset($arr_customers);
            } else {
                $arr_customers['addrs'] = empty($arr_customers['addrs']) ? [] : json_decode($arr_customers['addrs'], true);
                $salesmen = DB::table('user')->where('id', $arr_customers['salesmanId'])->first();
                $arr_customers['salesmenName'] = $salesmen->name;
            }
        }

        if (!array_key_exists(0, $arr_customers)) {
            $arr_customers = [$arr_customers];
        }

        return empty($arr_customers) ? [] : $arr_customers;
    }
    /*  @删除客户
     *  @param $id
     *  @return array
     * */
    public function del($id = 0)
    {
        $this->roleAuth();
        $id = (int) $id;
        if ($id) {
            $del = DB::table('customer')->where('id', $id)->update(['status' => 1]);
            $arr_return = empty($del) ? ['errmsg' => '不存在该客户'] : [];
        } else {
            $arr_return = ['errmsg' => 'ID 是必须的,且只能是数字'];
        }
        return $this->responseJson($arr_return);
    }
}