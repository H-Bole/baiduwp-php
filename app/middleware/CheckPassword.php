<?php

namespace app\middleware;

use app\controller\Auth;
use app\Request;

class CheckPassword
{
    public function handle(Request $request, \Closure $next)
    {
        if (!Auth::checkPassword($request)) {
            // 密码错误或卡密无效
            return json([
                'error' => -1,
                'msg' => '网站访问密码错误或卡密无效',
            ]);
        }
        return $next($request);
    }
}
