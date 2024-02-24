<?php

namespace app\controller;

use app\BaseController;
use app\Request;
use think\facade\Db;

class Auth extends BaseController
{
    /**
     * 检查密码
     */
    public function status()
    {
        // 检查是否有卡密表，若没有则创建
        if (!Db::query("SHOW TABLES LIKE 'cards'")) {
            Db::execute("
                CREATE TABLE cards (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    card_value VARCHAR(255) NOT NULL,
                    card_type ENUM('A', 'B', 'C', 'D') NOT NULL,
                    valid_days INT NOT NULL,
                    valid_times INT NOT NULL,
                    used_times INT NOT NULL DEFAULT 0,
                    start_time TIMESTAMP NULL DEFAULT NULL,
                    expire_time TIMESTAMP NULL DEFAULT NULL,
                    is_invalid ENUM('yes', 'no') NOT NULL DEFAULT 'no'
                )
            ");
            // 创建一个默认卡密
            $defaultCard = [
                'card_value' => 'D|X9BZMr6zF0ZZwh9aarAnHbrY8YHW47Pb',
                'card_type' => 'D',
                'valid_days' => 20,
                'valid_times' => 100
            ];
            Db::name('cards')->insert($defaultCard);
        }

        //查看配置是否有密码
        $ispassword = config('baiduwp.password');
        if (empty($ispassword)) {
            return json([
                'status' => 0,
                'msg' => '无需密码',
            ]);
        }
        // 获取当前用户的卡密
        $currentCard = session('Password');

        if (!$currentCard) {
            // 用户未登录
            return json([
                'status' => 1,
                'msg' => '未登录'
            ]);
        }

        // 查询当前卡密是否有效
        $card = Db::name('cards')->where('card_value', $currentCard)->find();

        if (!$card || $card['is_invalid'] === 'yes') {
            // 卡密不存在或已失效
            session('Password', null); // 清除会话中的卡密信息
            return json([
                'status' => 1,
                'msg' => '未登录'
            ]);
        }

        // 返回已登录状态
        if($card['is_invalid'] === 'no'){
            return json([
                'status' => 2,
                'msg' => '已登录',
            ]);
        }
    }

    public static function checkPassword(Request $request)
    {
        // 获取用户输入的密码
        $pwd = session('Password');
        if (!$pwd) {
            $pwd = $request->post('password');
        }
        // 查询卡密表是否存在用户输入的卡密
        $card = Db::name('cards')->where('card_value', $pwd)->find();

        if (!$card) {
            // 卡密不存在
            return false;
        }

        // 检查卡密是否有效
        if ($card['is_invalid'] === 'yes') {
            // 卡密已失效
            return false;
        }

        // 更新卡密的使用信息
        if (is_null($card['start_time'])) {
            // 第一次使用卡密，更新开始时间和失效时间
            $expire_time = date('Y-m-d H:i:s', strtotime("+" . $card['valid_days'] . " days"));
            Db::name('cards')->where('id', $card['id'])->update([
                'start_time' => date('Y-m-d H:i:s'),
                'expire_time' => $expire_time
            ]);
        } else {
            // 非第一次使用卡密，检查是否超过有效次数或有效期
            $now = time();
            $expire_time = strtotime($card['expire_time']);
            if ($now > $expire_time || $card['used_times'] >= $card['valid_times']) {
                // 卡密已过期或使用次数已满
                Db::name('cards')->where('id', $card['id'])->update(['is_invalid' => 'yes']);
                return false;
            }
        }

        // 更新已使用次数
        Db::name('cards')->where('id', $card['id'])->inc('used_times')->update();
        
        // 如果卡密有效，将卡密写入会话，标记用户已登录
        if ($card['is_invalid'] === 'no') {
            session('Password', $pwd);
            return true;
        }
    }
}
