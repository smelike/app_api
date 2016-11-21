<?php

/**
 * Created by PhpStorm.
 * User: james
 * Date: 9/5/16
 * Time: 3:11 PM
 */

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Validator;

class OrderController extends Controller
{

    private $_table = 'lamp_order';
    private $_orderFields = array(
        'id' => '',
        'cartId' => '',
        'orderNo' => '',
        'salesmanId' => '',
        'customerId' => '',
        'discount' => '',
        'oddCut' => '',
        'status' => '',
        'totalPrice' => '',
        'createTime' => '',
        'updateTime' => ''
    );

    public function formatOrderData($request)
    {
        $arr_data = [];
        $arr_fields = array_keys($this->_orderFields);
        foreach ($arr_fields as $field)
        {
            if ($request->has($field)){
                $arr_data[$field] = $request->input($field);
            } else {
                $arr_data[$field] = '';
            }
        }
        unset($arr_data['id']);
        $arr_data['createTime'] = date('Y-m-d H:i:s');
        $arr_data['updateTime'] = date('Y-m-d H:i:s');
        $arr_data['salesmanId'] = $this->getUserId();
        $arr_data['orderNo'] = date('YmdHis') . rand(10000, 99999);

        return $arr_data;
    }

    public function formatOrderdetailData($order_id, $arr_product)
    {
        $temp = [];
        foreach($arr_product as $k => $product)
        {
            if (isset($product['lightId']) AND (int)$product['lightId'])
            {
                $temp[$k]['orderId'] = $order_id;
                $temp[$k]['lightId'] = (int)$product['lightId'];
                $temp[$k]['qty'] = isset($product['qty']) ? $product['qty'] : 0;
                $temp[$k]['discount'] = isset($product['discount']) ? $product['discount'] : 0;
                $temp[$k]['updateTime'] = $temp[$k]['createTime'] = date('Y-m-d H:i:s');
            }
        }
        return $temp;
    }

    private function defineMessage()
    {
        return [
            'cartId.required'   => '购物车 ID 不能为空',
            'cartId.integer'    => '购物车 ID 必须为整数',
            'customerId.required' => '客户 ID 不能为空',
            'customerId.integer'    => '客户 ID 必须为整数',
            'items.required' => '产品不能为空',
            'items.array' => '产品格式不正确',
            'discount.required' => '总额折扣不能为空',
        ];
    }
    private function defineRule()
    {
        return [
            'cartId' => 'required|integer',
            'customerId' => 'required|integer',
            'items' => 'required|array',
            'discount' => 'required'
        ];
    }

    /*  @购物车结算
     *  @param $request
     *  @return mixed
     * */
    public function settleAccount(Request $request)
    {
        $rules = $this->defineRule();
        unset($rules['cartId']);
        unset($rules['customerId']);
        $validator = Validator::make(
            $request->all(),
            $rules,
            $this->defineMessage(),
            array_values($rules)
        );

        $arr_error = $this->messageBag($validator->errors(), $rules);

        if (empty($arr_error) AND $this->getUserId())
        {
            $oddCut = $request->oddCut ? $request->oddCut : 0;
            $amount = $this->preTotalPrice($request) - $oddCut;
            $arr_return = ['msg' => array('totalPrice' => $amount)];
        } else {
            $arr_return = ['errmsg' => array_shift($arr_error)];
        }

        return $this->responseJson($arr_return);
    }

    /*  @预先做 settle account
     *  @param $request
     *  @return mixed
     * */
    private function preTotalPrice($request)
    {
        $amount_discoumt = $request->discount;

        $products = $request->items;

        $lights = DB::table('light')->whereIn('id', array_column($products, 'lightId'))->get();
        $lights = $this->getAssocDataByObject($lights);
        $light_prices = array_column($lights, 'price', 'id');

        $arr_price = [];
        foreach ($products as $product)
        {
            if (isset($product['lightId'], $product['qty'], $product['discount']))
            {
                $arr_price[] = $light_prices[$product['lightId']] * $product['qty'] * $product['discount'];
            }
        }

        $amount = array_sum($arr_price) * $amount_discoumt;
        return sprintf("%.2f", $amount);
    }

    /*  @创建订单
     *  @param $request
     *  @return array
     * */
    public function create(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            $this->defineRule(),
            $this->defineMessage(),
            $this->defineRule()
        );

        $arr_error = $this->messageBag($validator->errors(), $this->defineRule());

        if (empty($arr_error) AND $this->getUserId())
        {
            $return = $this->orderTransaction($request);
            if ($return['orderId'] AND $return['orderDetail'])
            {

                $order_field = ['id', 'orderNo', 'salesmanId', 'totalPrice', 'discount', 'customerId', 'createTime', 'updateTime'];
                $order = $this->getOrderById($return['orderId'], $order_field);

                $order->items = $this->orderDetailInfo(array($return['orderId']));
                $order->customer = $this->order_customer($order->customerId);
                unset($order->customerId);
                $arr_return = ['msg' => $order];
            } else {
                $arr_return = ['msg' => $this->_msg];
            }
        } else {
            $arr_return = ['errmsg' => array_shift($arr_error)];
        }
        return $this->responseJson($arr_return);
    }

    /*  @计算订单总价
     *  @param $order_id
     *  @param $order_discount
     *  @param $arr_order_detail
     *  @param $oddCut
     *  @return void
     * */
    private function orderTotalPrice($order_id, $order_discount, $arr_order_detail, $oddCut = 0)
    {
        $arr_price = [];
        $light_price = DB::table('light')->whereIn('id', array_column($arr_order_detail, 'lightId'))->get(['id', 'price']);
        $light_price = $this->getAssocDataByObject($light_price);
        $light_price = array_column($light_price, 'price', 'id');

        foreach($arr_order_detail as $detail)
        {
            $arr_price[] = $light_price[$detail['lightId']] * $detail['qty'] * $detail['discount'];
        }

        $totalPrice = array_sum($arr_price) * $order_discount;
        $totalPrice = round($totalPrice, 2) - $oddCut;
        $update = ['totalPrice' => $totalPrice];
        DB::table('order')->where('id', $order_id)->update($update);
    }

    /*  @删除购物车中已经结算的商品
     *  @param $cartId
     *  @param $arr_product_id
     *  @return void
     * */
    private function deleteCartDetail($cartId, $arr_product_id)
    {
        foreach($arr_product_id as $id)
        {
            $where = [
                ['cartId', '=', $cartId],
                ['lightId', '=', $id]
            ];
            DB::table('cart_detail')->where($where)->delete();
        }
    }

    /*  @订单创建
     *  @param $request
     *  @return boolean
     * */
    private function orderTransaction($request)
    {

       return DB::transaction(function () use ($request) {

           $orderData = $this->formatOrderData($request);
           $items = $request->items;
           if ($items)
           {
               $orderId = DB::table('order')->insertGetId($orderData);
               if ($orderId)
               {
                   $arr_order_detail = $this->formatOrderdetailData($orderId, $items);
                   $return_detail = DB::table('order_detail')->insert($arr_order_detail);
                   if ($return_detail) {
                       $arr_light_id = array_column($items, 'lightId');
                       $this->deleteCartDetail($request->input('cartId'), $arr_light_id);
                   }
               }

               if ($orderId AND $return_detail)
               {
                   $this->orderTotalPrice($orderId, $orderData['discount'], $arr_order_detail, $request->oddCut);
               }
               return array('orderId' => $orderId, 'orderDetail' => $return_detail);
           }
        });
    }

    /*  @查询某个订单
     *  @param $id
     *  @return array
     * */
    public function info($id = '')
    {
        $id = (int) $id;
        if ($id)
        {
            $order_field = ['id', 'orderNo', 'salesmanId', 'totalPrice', 'discount', 'customerId', 'createTime', 'updateTime'];
            $order = $this->getOrderById($id, $order_field);

            if ($order)
            {
                if ($order->salesmanId == $this->getUserId())
                {
                    $order->items = $this->orderDetailInfo(array($id));
                    $order->customer = $this->order_customer($order->customerId);
                    unset($order->customerId);
                    $arr_return = ['msg' => $order];
                } else {
                    $arr_return = ['errmsg' => '你没有权限查看别人的订单'];
                }
            } else {
                $arr_return = ['msg' => [], 'errmsg' => '该订单已经被删除啦!'];
            }
        } else {
            $arr_return = ['errmsg' => '接口参数格式不正确'];
        }

        return $this->responseJson($arr_return);
    }

    /*  @查询某个销售的所有订单
     *  @param $request
     *  @param $customerId
     *  @return array
     * */
    public function myorderbytoken(Request $request, $customerId = 0)
    {
        $per = 13;
        $pageNo = (int) $request->input('pageNo');
        $pageNo = $pageNo ? $pageNo : 1;

        if (empty($this->getUserId()))
        {
            $arr_return = ['errmsg' => '非法的用户,不能接受的订单请求'];
            return $this->responseJson($arr_return);
        }

        if (empty((int) $pageNo))
        {
            $arr_return = ['errmsg' => '非法的参数'];
            return $this->responseJson($arr_return);
        }

        $count = 0;
        $orders = $this->orderPagination($customerId, $pageNo, $count);

        $arr_return = [];
        if ($orders)
        {
            $this->compactOrderDetailAndCustomerAndSalesman($orders);
            $rows = $pageNo * $per;
            $arr_return['msg']['more'] = $count < $rows ? 0 : 1;
            $arr_return['msg']['orders'] = $orders;
        }
        if ($customerId) {
            return $orders ? $orders : [];
        }
        return $this->responseJson($arr_return);
    }

    /*  @删除订单
     *  @param $orderId
     *  @return json
     * */
    public function deleteOrder($orderId)
    {
        $arr_return = [];
        if ((int) $orderId)
        {
            try {

                $update = ['status' => 1, 'updateTime' => date('Y-m-d H:i:s')];
                DB::table('order')->where('id', $orderId)->update($update);
            } catch(\Exception $e) {
                $arr_return = ['errmsg' => '订单删除失败,请稍后重试'];
            }
        } else {
            $arr_return = ['errmsg' => '接口参数格式不正确'];
        }
        return $this->responseJson($arr_return);
    }

    /* @对订单进行分页
     * @param $customerId
     * @param $pageNo
     * @param $count
     * @return mixed
     * */
    private function orderPagination($customerId, $pageNo, &$count)
    {
        $arr_condition = [
            ['status', '=', 0],
            ['salesmanId', '=', $this->getUserId()]
        ];
        $where = "status = 0 AND salesmanId =" . $this->getUserId();

        if ($customerId)
        {
            $arr_condition = [
                ['status', '=', 0],
                ['customerId', '=', $customerId]
            ];
            $where = "status = 0 AND customerId = {$customerId}";
        }
        $count = DB::table("order")->where($arr_condition)->count();
        $arr_field = ['id', 'totalPrice', 'discount', 'oddcut', 'salesmanId', 'customerId', 'orderNo', 'createTime', 'updateTime'];
        return $this->pagination($this->_table, $where, $count, $pageNo, $per = 13, $arr_field);
    }

    /* @对订单进行客户信息,订单详情,销售员姓名的补充
     * @param $orders
     * @return void
     * */
    private function compactOrderDetailAndCustomerAndSalesman(&$orders)
    {
        foreach($orders as $order)
        {
            $order_ids[] = $order->id;
        }

        foreach ($orders as &$order)
        {
            //$customerInfo = DB::table('customer')->where('id', $order->customerId)->first(['id', 'tel', 'name', 'addrs']);
            $customerInfo = $this->order_customer($order->customerId);
            if ($customerInfo)
            {
                //isset($customerInfo->addrs) ? $customerInfo->addrs = json_decode($customerInfo->addrs, true) : "";
                $salesmanInfo = DB::table('user')->where('id', $order->salesmanId)->first(['name', 'tel']);
                $customerInfo->salesmanName = $salesmanInfo->name ? $salesmanInfo->name : $salesmanInfo->tel;
            } else {
                $customerInfo = $this->_msg;
            }
            $order->customer = $customerInfo;
        }
    }
}