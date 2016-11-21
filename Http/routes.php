<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::group(['prefix'=>'lam'], function(){

    Route::get('/', function(){
        return view('welcome');
    });

    Route::get('/swagger/doc', 'SwaggerController@doc');

    // Object - user
    Route::post('user/login', 'User\UserController@login');
    Route::get('user/logout', 'User\UserController@logout');
    Route::get('user/{user_id}/resetpwd', 'User\UserController@resetpwd');
    Route::post('user/changepwd', 'User\UserController@changePassword');
    Route::any('user/register', 'User\UserController@register');
    Route::post('user/edit', 'User\UserController@editProfile');

    // Object - role
    Route::post('role/del', 'User\RoleController@del');
    Route::post('role', 'User\RoleController@create');
    Route::get('role', 'User\RoleController@query');
    Route::post('role/{id}', 'User\RoleController@edit');


    // Object - customer
    Route::post('customer', 'User\CustomerController@create');
    Route::post('customer/{id}', 'User\CustomerController@edit');

    // 查询某个客户信息
    Route::get('customer/del/{id}', 'User\CustomerController@del');
    Route::get('customer/{id}', 'User\CustomerController@info');
    Route::get('customer', 'User\CustomerController@query');
    Route::options('customer/test/{id}/del/{gid}', 'User\CustomerController@test');


    // Object - lamp
    Route::post('group/{groupId}/lamp', 'Lamp\LampController@create');
    Route::get('group/{groupId}/lamp/{id}/del', 'Lamp\LampController@del');
    Route::post('group/{groupId}/lamp/{id}', 'Lamp\LampController@edit');
    Route::get('group/{groupId}/lamp/{id}', 'Lamp\LampController@query');
    Route::get('group/{groupId}/lamp', 'Lamp\LampController@search'); // 查询灯具
    Route::post('image', 'Lamp\LampController@img');
    Route::get('lamp/bind', 'Lamp\LampController@queryBind');


    // Object - group
    Route::post('group', 'Lamp\GroupController@create');
    Route::get('group', 'Lamp\GroupController@glist');
    Route::post('group/{id}/del', 'Lamp\GroupController@del');
    Route::post('group/edit', 'Lamp\GroupController@edit');
    Route::get('group/{group_id}', 'Lamp\GroupController@glight');


    // Object - shopping cart
    Route::post("cart", 'Order\CartController@create');
    Route::get("cart/query", 'Order\CartController@query');
    Route::get("cart/{cartId}", 'Order\CartController@info');
    Route::post("cart/{cartId}", 'Order\CartController@update');
    Route::post("cart/{id}/shopping", 'Order\CartController@shop');


    // Object - order
    Route::post('order', 'Order\OrderController@create');
    Route::get('order/salesman', 'Order\OrderController@myorderbytoken');
    Route::post('order/settle', 'Order\OrderController@settleAccount');
    Route::get('order/{id}/del', 'Order\OrderController@deleteOrder');
    Route::get('order/{id}', 'Order\OrderController@info');


    // Object - mesh
    Route::get('mesh/{num}/group/{groupId}', 'Lamp\MeshController@get');
    Route::post('mesh/group/{groupId}/report', 'Lamp\MeshController@report');
    Route::any("mesh/reset", 'Lamp\MeshController@reset');
    Route::any("mesh/device/del", 'Lamp\MeshController@delDevice');

    // Object - comment
    Route::get('comment/customerId/{customerId}', 'User\CommentController@index');
    Route::post('comment/update/{commentId}', 'User\CommentController@updateComment');
    Route::get('comment/del/{commentId}', 'User\CommentController@delComment');
    Route::post('comment/customerId/{customerId}', 'User\CommentController@store');


    // Object - Admin
    Route::get('admin', 'Admin\IndexController@index');
    Route::get('admin/order', 'Admin\IndexController@order');
    Route::get('admin/logout', 'Admin\IndexController@logout');
    Route::get('admin/printf/{id}', 'Admin\IndexController@printf');

});
