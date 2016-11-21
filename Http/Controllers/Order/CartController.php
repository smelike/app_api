<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 9/30/16
 * Time: 5:38 PM
 */

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Validator;

class CartController extends Controller
{

    private $_table = 'lamp_cart';

    /*  @更新购物车
     *  @param $request
     *  @param $cartId
     *  @return array
     * */
    public function update(Request $request, $cartId = '')
    {
        $arr_return = [];
        $products = $request->input("items");
        $customerId = (int) $request->customerId;
        $id = (int) $cartId;
        $currentCart = $this->queryValidCart($id);

        if (empty($currentCart))
        {
            return $this->responseJson(['errmsg' => '该购物车已经不存在']);
        }
        if ($id AND is_array($products))
        {
            if ($customerId == $currentCart->customerId)
            {
                $this->insertOrUpdateCartDetail($id, $products);
            } else if ($customerId AND empty($currentCart->customerId)) {
                $this->updateCartCustomerId($request, $id);
                $this->insertOrUpdateCartDetail($id, $products, $update = true);
            } else {
                $this->outDateCart($request);
                $id = $this->copyCart($id, $request);
            }
            $arr_return['msg'] = $this->shoppingCartDetail($id);
        } else {
            $arr_return = ['errmsg' => '接口参数不正确'];
        }
        return $this->responseJson($arr_return);
    }

    /*  @复制购物车商品,并新建购物车
     *  @param $fromCartId
     *  @param $request
     * */
    private function copyCart($fromCartId, $request)
    {
        $fromCart = DB::table('cart')->where('id', $fromCartId)->first();
        $fromCartDetail = DB::table('cart_detail')->where('cartId', $fromCartId)->get();
        $fromCart = (array) $fromCart;

        if ($fromCart)
        {
            $fromCart['customerId'] = $request->input('customerId');
            $fromCart['salesmanId'] = $this->getUserId();
            unset($fromCart['id']);
            $new_id = DB::table('cart')->insertGetId($fromCart);

            if ($new_id)
            {
                $arr_insert = [];
                foreach($fromCartDetail as $detail) {
                    $detail->cartId = $new_id;
                    $detail->createTime = date('Y-m-d H:i:s');
                    $detail->updateTime = date('Y-m-d H:i:s');
                    unset($detail->id);
                    $arr_insert[] = get_object_vars($detail);
                }
                DB::table('cart_detail')->insert($arr_insert);
                return $new_id;
            }
        }
    }

    /*  @更改购物车的customerId
     *  @param $customerId
     *  @param $cartId
     *  @return boolean
     * */
    private function updateCartCustomerId($customerId, $cartId)
    {
        if ($cartId AND $customerId)
        {
            $where = [['id', '=', $cartId], ['status', '=', 0]];
            return DB::table('cart')->where($where)->update(['customerId' => $customerId]);
        }
    }

    /*  @购物车失效
     *  @param $request
     *  @return boolean
     * */
    private function outDateCart($request)
    {
        $customerId = (int) $request->input('customerId');

        if ($customerId)
        {
            $update = ['status' => 1, 'updateTime' => date('Y-m-d H:i:s')];
            DB::table('cart')->where('customerId', '=', $customerId)->update($update);
        }
    }

    /*  @购物商品
     *  @param $request
     *  @param $cartId
     *  @return array
     * */
    public function shop(Request $request, $cartId = '')
    {
        $arr_product = $request->input('items');
        $arr_return = [];
        $id = (int) $cartId;

        if ($arr_product AND is_array($arr_product) AND $id)
        {
            $return = $this->insertOrUpdateCartDetail($id, $arr_product);
            if ($return) {
                $arr_return['msg'] = $this->shoppingCartDetail($id);
            } else {
                $arr_return = ['errmsg' => '网络响应有问题,请刷新页面再次购物'];
            }
        } else {
            $arr_return = ['errmsg' => '接口参数不正确'];
        }
        return $this->responseJson($arr_return);
    }

    /* @查询某个购物车详情
     * @param $cartId
     * @return mixed
     * */
    private function shoppingCartDetail($cartId)
    {
        $cart = $this->queryValidCart($cartId, array('id', 'customerId'));
        $cart->customer = $this->order_customer($cart->customerId);
        $cart->items = $this->queryCartDetail($cartId, array('qty', 'discount', 'lightId'));
        $arr_light_field = array('id', 'lightNo', 'name', 'groupId', 'price', 'images', 'status');
        foreach($cart->items as &$item)
        {
            $item->light = $this->light_detail($item->lightId, $arr_light_field);
            unset($item->lightId);
        }
        unset($cart->customerId);
        return $cart ? $cart : $this->_msg;
    }

    /*  @查询有效的购物车
     *  @param $cartId
     *  @param $arr_field
     *  @return mixed
     * */
    private function queryValidCart($cartId, $arr_field = [])
    {
        $where = [['id', '=', $cartId], ['status', '=', 0]];
        if ($arr_field) {
            $cart = DB::table('cart')->where($where)->first($arr_field);
        } else {
            $cart = DB::table('cart')->where($where)->first();
        }
        return $cart;
    }

    /*  @插入或更新购物详情
     *  @param $cartId
     *  @param $arr_product
     *  @param $update [true -> 更新购物车, false -> 购物]
     *  @return boolean
     * */
    private function insertOrUpdateCartDetail($cartId, $arr_product, $update = false)
    {

        $cart = $this->queryValidCart($cartId);
        $return = false;
        if ($cart)
        {
            if ($update)
            {
                $arr_product_id = array_column($arr_product, 'lightId');
                $detele = $this->deleteProductFromCart($cartId, $arr_product_id);
            }
            $arr_cart_insert = [];
            $arr_cart_update = [];

            foreach ($arr_product as $key => $product) {
                if (isset($product['lightId'])) {
                    $where = [
                        ['cartId', '=', $cartId],
                        ['lightId', '=', $product['lightId']],
                    ];
                    $exit = DB::table('cart_detail')->where($where)->first();

                    if ($exit) {
                        $arr_cart_update[$exit->id]['discount'] = $product['discount'];
                        $arr_cart_update[$exit->id]['updateTime'] = date('Y-m-d H:i:s');

                        if ($update) {
                            $arr_cart_update[$exit->id]['qty'] = $product['qty'];
                        } else {
                            $arr_cart_update[$exit->id]['qty'] = $exit->qty + $product['qty'];
                        }

                    } else {
                        $arr_cart_insert[$key] = [
                            'cartId' => $cartId,
                            'lightId' => $product['lightId'],
                            'qty' => $product['qty'],
                            'discount' => $product['discount'],
                            'createTime' => date('Y-m-d H:i:s'),
                            'updateTime' => date('Y-m-d H:i:s'),
                        ];
                    }
                }
            }

            if ($arr_cart_insert) {
                $return = DB::table('cart_detail')->insert($arr_cart_insert);
            } else if ($arr_cart_update) {
                foreach ($arr_cart_update as $id => $update) {
                    $return = DB::table('cart_detail')->where('id', $id)->update($update);
                }
            }

            if ($return) {
                DB::table("cart")->where('id', $cartId)->update(['updateTime' => date('Y-m-d H:i:s')]);
            }
        }
        return $return && $cart;
    }
    /*  @删除购物车中的商品
     *  @param $cartId
     *  @param $arr_product_id
     *  @return void
     * */
    private function deleteProductFromCart($cartId, $arr_product_id)
    {
        $cart_detail = DB::table('cart_detail')->where('cartId', $cartId)->get();
        foreach($cart_detail as $detail)
        {
            if (!in_array($detail->lightId, $arr_product_id))
            {
                $where = [
                    ['cartId', '=', $cartId],
                    ['lightId', '=', $detail->lightId]
                ];
                DB::table('cart_detail')->where($where)->delete();
            }
        }
    }

    /*  @查询某个 ID 的购物车
     *  @param $id
     *  @return array
     * */
    public function info($id = '')
    {
        if ((int) $id)
        {
            try {
                $cart = $this->shoppingCartDetail($id);
                $arr_return = ['msg' => $cart];
            } catch (\Exception $e) {
                $arr_return = ['errmsg' => '购物车不存在'];
            }
        } else {
            $arr_return = ['errmsg' => '参数格式不正确'];
        }

        return $this->responseJson($arr_return);
    }
    /*  @查询该营业员所有购物车
     *  @param $request
     *  @return array
     * */
    public function query(Request $request)
    {

        $pageNo = (int) $request->input('pageNo');
        $pageNo = $pageNo ? $pageNo : 1;

        $count = DB::table('cart')->where('status', 0)->count();
        $where = 'status = 0';
        $arr_field = array('id', 'customerId', 'updateTime');
        $carts = $this->pagination($this->_table, $where, $count, $pageNo, $per = 13, $arr_field);
        $rows = $pageNo * $per;
        $arr_return['msg']['more'] = ($carts AND ($count < $rows)) ? 0 : 1;
        foreach($carts as &$cart)
        {
            $cart->customer = $this->order_customer($cart->customerId, array('id', 'tel', 'name', 'addrs'));
            unset($cart->customerId);
        }

        $arr_return['msg']['carts'] = isset($carts) ? $carts : [];

        return $this->responseJson($arr_return);
    }

    /*  @创建购物车
     *  @param $request
     *  @return array
     * */
    public function create(Request $request)
    {
        $customerId  = (int) $request->input('customerId');

        if ($customerId)
        {
            $cart = $this->initializeCart($customerId);
            $cart->customer = $this->_msg;

            $where = [
                ['id', '=', $cart->customerId],
                ['status', '=', 0]
            ];
            $customer = DB::table('customer')->where($where)->first();
            if ($customer) {
                $customer->addrs = json_decode($customer->addrs, true);
                $where = [['id', '=', $customer->salesmanId], ['status', '=', 0]];
                $salesmanInfo = DB::table('user')->where($where)->first(['name', 'tel']);
                $customer->salesmanName = $salesmanInfo->name ? $salesmanInfo->name : $salesmanInfo->tel;
                $cart->customer = $customer;
            }
            $arr_return = ['msg' => $cart];
        } else {
            $cart = $this->initializeCart($customerId);
            $cart->customer = $this->_msg;
            $arr_return = ['msg' => $cart];
        }
        return $this->responseJson($arr_return);
    }

    private function hasCart($customerId)
    {
        if ($customerId) {
            $where = [
                ['customerId', '=', $customerId],
                ['status', '=', 0],
            ];
            return DB::table('cart')->where($where)->first();
        }
        return false;
    }

    /* @初始化新建购物车
     * @param $customerId
     * @return mixed
     * */
    private function initializeCart($customerId)
    {
        $cart = $this->hasCart($customerId);
        if ($cart) {
            return $cart;
        }

        $formatCart = [
            'salesmanId' => $this->getUserId(),
            'customerId' => $customerId,
            'status'    => 0,
            'createTime'    => date('Y-m-d H:i:s'),
            'updateTime'    => date('Y-m-d H:i:s')
        ];
        $arr_data  = ['pkey' => 'id', 'data' => $formatCart, 'table' => 'cart'];
        return $this->insertAndReturnRow($arr_data);
    }
}