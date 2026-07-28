<?php
/**
 * 复读机插件 - 让机器人复读你说的话
 * 
 * 指令: echo <消息>
 * 示例: echo 你好世界
 * 
 * @version 1.0.0
 * @author 复读机开发组
 */

function echo_repeat($params, $event) {
    $msg = isset($params['message']) ? trim($params['message']) : '';
    if (empty($msg)) {
        reply_message('用法: echo 要说的话');
        return;
    }
    if (mb_strlen($msg) > 500) {
        reply_message('消息太长了，请控制在500字以内');
        return;
    }
    $safe_msg = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    reply_message($safe_msg);
}

plugin_register('echo', 'echo_repeat');
