<?php
/**
 * Created by PhpStorm.
 * User: HSF
 * Date: 2016/5/31
 * Time: 17:51
 */

namespace App\Http\Middleware;

use Closure;

class BeforeMiddleware
{
    public function handle($request, Closure $next)
    {
        /*前置中间件
         *判断用户登陆是否过期（session）
         */
        if(!session('sign')){
            return redirect()->route('login');
        }

        return $next($request);
    }
}