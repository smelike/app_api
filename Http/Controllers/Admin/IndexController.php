<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 11/1/16
 * Time: 11:21 AM
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Order;

class IndexController extends Controller
{

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->middleware('guest');

    }

    public function index(Request $request)
    {
        return view('admin.index');
    }

    public function order(Request $request)
    {
        $orders = Order::where('status', 0)->get();

        return view('admin.order', compact('orders'));
    }

    public function printf($id = 0)
    {
        $order = Order::where('id', $id)
                    ->where('status', 0)->first();

        if (empty($order))
        {
            return redirect('/lam/admin/');
        }
        $order->payStatus = $order->payStatus ? '已支付' : '未支付';

        $customer = DB::table('customer')->where('id', $order->customerId)->first();
        if ($customer) {
            $addrs = json_decode($customer->addrs, true);
            $customer->addrs = $addrs ? array_pop($addrs) : '暂无';
        }
        $orderDetail =  DB::table('order_detail')->where('orderId', $id)->get();

        foreach($orderDetail as &$detail)
        {
            //$lightId[] = $detail->lightId;
            $light = DB::table('light')->where('id', $detail->lightId)->first();
            if ($light) {
                $detail->lightName = $light->name;
                $detail->lightNo = $light->lightNo;
                $detail->lightPrice = $light->price;
            }
        }

        return view('admin.detail', compact('order', 'customer', 'orderDetail'));
    }

}