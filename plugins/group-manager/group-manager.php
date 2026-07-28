<?php
/**
 * 群管助手 - QQ群管理辅助插件
 * 
 * 功能：
 * - 入群欢迎消息（可自定义模板）
 * - 定时消息推送（支持 cron 表达式）
 * - 关键词自动回复（支持精确/模糊匹配）
 * - 群投票/问卷
 * - 每日签到打卡
 * - 黑名单管理
 * - 群活跃度统计
 * - 违禁词过滤
 * 
 * @version 2.1.0
 * @author 群管开发组
 */

// ==================== 注册指令 ====================

plugin_register('gm_help', 'gm_show_help');
plugin_register('gm_welcome', 'gm_set_welcome');
plugin_register('gm_timer', 'gm_timer_msg');
plugin_register('gm_keyword', 'gm_keyword_reply');
plugin_register('gm_vote', 'gm_create_vote');
plugin_register('gm_sign', 'gm_daily_sign');
plugin_register('gm_ban', 'gm_blacklist');
plugin_register('gm_stats', 'gm_group_stats');
plugin_register('gm_filter', 'gm_set_filter');

// ==================== 配置管理 ====================

function gm_get_config($groupId, $key, $default = '') {
    $configs = kv_get('gm_config_' . $groupId);
    if (!is_array($configs)) $configs = array();
    return isset($configs[$key]) ? $configs[$key] : $default;
}

function gm_set_config($groupId, $key, $value) {
    $configs = kv_get('gm_config_' . $groupId);
    if (!is_array($configs)) $configs = array();
    $configs[$key] = $value;
    kv_set('gm_config_' . $groupId, $configs);
}

function gm_check_admin($event) {
    $role = isset($event['sender']['role']) ? $event['sender']['role'] : '';
    if ($role !== 'admin' && $role !== 'owner') {
        reply_message('权限不足，仅群主/管理员可执行此操作');
        return false;
    }
    return true;
}

// ==================== 功能1: 入群欢迎 ====================

function gm_set_welcome($params, $event) {
    if (!gm_check_admin($event)) return;
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    if (empty($groupId)) { reply_message('请在群聊中使用'); return; }
    
    $msg = isset($params['message']) ? trim($params['message']) : '';
    if (empty($msg)) {
        $current = gm_get_config($groupId, 'welcome_msg', '未设置');
        reply_message("当前欢迎消息:\n{$current}\n\n使用: gm_welcome 消息内容\n支持变量: {nickname} {group}");
        return;
    }
    
    gm_set_config($groupId, 'welcome_msg', $msg);
    gm_set_config($groupId, 'welcome_enabled', '1');
    reply_message('欢迎消息已设置');
}

function gm_handle_new_member($event) {
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    $enabled = gm_get_config($groupId, 'welcome_enabled', '0');
    if ($enabled !== '1') return;
    
    $template = gm_get_config($groupId, 'welcome_msg', '欢迎 {nickname} 加入本群~');
    $nickname = isset($event['sender']['nickname']) ? $event['sender']['nickname'] : '新成员';
    $groupName = isset($event['group_name']) ? $event['group_name'] : '本群';
    
    $message = str_replace(array('{nickname}', '{group}'), array($nickname, $groupName), $template);
    if (strlen($message) > 500) $message = substr($message, 0, 500);
    
    reply_group_message($groupId, $message);
}

// ==================== 功能2: 定时消息 ====================

function gm_timer_msg($params, $event) {
    if (!gm_check_admin($event)) return;
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    if (empty($groupId)) { reply_message('请在群聊中使用'); return; }
    
    $action = isset($params['action']) ? $params['action'] : 'list';
    
    switch ($action) {
        case 'add':
            $time = isset($params['time']) ? trim($params['time']) : '';
            $content = isset($params['content']) ? trim($params['content']) : '';
            if (empty($time) || empty($content)) {
                reply_message('格式: gm_timer add time="08:00" content="早安"');
                return;
            }
            $timers = json_decode(gm_get_config($groupId, 'timers', '[]'), true);
            if (!is_array($timers)) $timers = array();
            $id = count($timers) + 1;
            $timers[] = array('id' => $id, 'time' => $time, 'content' => $content, 'enabled' => true);
            gm_set_config($groupId, 'timers', json_encode($timers, JSON_UNESCAPED_UNICODE));
            reply_message("定时消息已添加 (#{$id})");
            break;
            
        case 'del':
            $id = intval(isset($params['id']) ? $params['id'] : 0);
            $timers = json_decode(gm_get_config($groupId, 'timers', '[]'), true);
            if (!is_array($timers)) $timers = array();
            $found = false;
            foreach ($timers as $k => $t) {
                if ($t['id'] === $id) { unset($timers[$k]); $found = true; break; }
            }
            if ($found) {
                gm_set_config($groupId, 'timers', json_encode(array_values($timers), JSON_UNESCAPED_UNICODE));
                reply_message("定时消息 #{$id} 已删除");
            } else {
                reply_message("未找到定时消息 #{$id}");
            }
            break;
            
        default:
            $timers = json_decode(gm_get_config($groupId, 'timers', '[]'), true);
            if (empty($timers)) {
                reply_message('暂无定时消息');
            } else {
                $text = "定时消息列表:\n";
                foreach ($timers as $t) {
                    $status = $t['enabled'] ? '启用' : '禁用';
                    $text .= "#{$t['id']} {$t['time']} [{$status}]\n";
                }
                $text .= "\n添加: gm_timer add time=\"HH:MM\" content=\"消息\"";
                reply_message($text);
            }
    }
}

// ==================== 功能3: 关键词回复 ====================

function gm_keyword_reply($params, $event) {
    if (!gm_check_admin($event)) return;
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    if (empty($groupId)) { reply_message('请在群聊中使用'); return; }
    
    $action = isset($params['action']) ? $params['action'] : 'list';
    
    switch ($action) {
        case 'add':
            $keyword = isset($params['keyword']) ? trim($params['keyword']) : '';
            $reply = isset($params['reply']) ? trim($params['reply']) : '';
            $mode = isset($params['mode']) ? $params['mode'] : 'exact';
            if (empty($keyword) || empty($reply)) {
                reply_message('格式: gm_keyword add keyword="关键词" reply="回复内容" mode=exact|fuzzy');
                return;
            }
            $keywords = json_decode(gm_get_config($groupId, 'keywords', '[]'), true);
            if (!is_array($keywords)) $keywords = array();
            $keywords[] = array('keyword' => $keyword, 'reply' => $reply, 'mode' => $mode);
            gm_set_config($groupId, 'keywords', json_encode($keywords, JSON_UNESCAPED_UNICODE));
            reply_message("关键词已添加: {$keyword}");
            break;
            
        case 'del':
            $keyword = isset($params['keyword']) ? trim($params['keyword']) : '';
            if (empty($keyword)) { reply_message('格式: gm_keyword del keyword="关键词"'); return; }
            $keywords = json_decode(gm_get_config($groupId, 'keywords', '[]'), true);
            if (!is_array($keywords)) $keywords = array();
            $newKeywords = array();
            $removed = false;
            foreach ($keywords as $k) {
                if ($k['keyword'] !== $keyword) $newKeywords[] = $k;
                else $removed = true;
            }
            if ($removed) {
                gm_set_config($groupId, 'keywords', json_encode($newKeywords, JSON_UNESCAPED_UNICODE));
                reply_message("关键词已删除: {$keyword}");
            } else {
                reply_message("未找到关键词: {$keyword}");
            }
            break;
            
        default:
            $keywords = json_decode(gm_get_config($groupId, 'keywords', '[]'), true);
            if (empty($keywords)) {
                reply_message('暂无关键词回复');
            } else {
                $text = "关键词回复列表:\n";
                foreach ($keywords as $k) {
                    $modeText = $k['mode'] === 'fuzzy' ? '模糊' : '精确';
                    $replyPreview = mb_substr($k['reply'], 0, 20);
                    $text .= "- {$k['keyword']} ({$modeText}) -> {$replyPreview}\n";
                }
                reply_message($text);
            }
    }
}

function gm_match_keyword($message, $event) {
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    if (empty($message) || empty($groupId)) return;
    
    $keywords = json_decode(gm_get_config($groupId, 'keywords', '[]'), true);
    if (!is_array($keywords)) return;
    
    foreach ($keywords as $k) {
        if ($k['mode'] === 'fuzzy') {
            if (mb_strpos($message, $k['keyword']) !== false) {
                reply_message($k['reply']);
                return;
            }
        } else {
            if ($message === $k['keyword']) {
                reply_message($k['reply']);
                return;
            }
        }
    }
}

// ==================== 功能4: 群投票 ====================

function gm_create_vote($params, $event) {
    if (!gm_check_admin($event)) return;
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    if (empty($groupId)) { reply_message('请在群聊中使用'); return; }
    
    $title = isset($params['title']) ? trim($params['title']) : '';
    $options = isset($params['options']) ? trim($params['options']) : '';
    
    if (empty($title) || empty($options)) {
        reply_message("格式: gm_vote title=\"投票标题\" options=\"选项1|选项2|选项3\"\n示例: gm_vote title=\"今晚吃什么\" options=\"火锅|烧烤|川菜\"");
        return;
    }
    
    $optList = explode('|', $options);
    if (count($optList) < 2) { reply_message('至少需要2个选项'); return; }
    if (count($optList) > 10) { reply_message('最多10个选项'); return; }
    
    $voteId = 'vote_' . $groupId . '_' . time();
    $voteData = array(
        'id' => $voteId,
        'group_id' => $groupId,
        'title' => $title,
        'options' => $optList,
        'votes' => array(),
        'created_at' => date('Y-m-d H:i:s'),
        'creator' => isset($event['sender']['user_id']) ? $event['sender']['user_id'] : ''
    );
    
    kv_set($voteId, $voteData);
    
    $optText = '';
    foreach ($optList as $i => $opt) {
        $optText .= ($i + 1) . ". {$opt}\n";
    }
    
    reply_message("📊 投票已创建\n\n标题: {$title}\n\n选项:\n{$optText}\n\n参与请回复: gm_vote {$voteId} 选项编号");
}

function gm_handle_vote($params, $event) {
    $voteId = isset($params['id']) ? trim($params['id']) : '';
    $choice = isset($params['choice']) ? intval($params['choice']) : 0;
    
    if (empty($voteId) || $choice < 1) return;
    
    $voteData = kv_get($voteId);
    if (!$voteData || !isset($voteData['options'])) {
        reply_message('投票不存在或已过期');
        return;
    }
    
    if ($choice > count($voteData['options'])) {
        reply_message('选项编号无效');
        return;
    }
    
    $userId = isset($event['sender']['user_id']) ? $event['sender']['user_id'] : '';
    if (isset($voteData['votes'][$userId])) {
        reply_message('你已经投过票了');
        return;
    }
    
    $voteData['votes'][$userId] = $choice;
    kv_set($voteId, $voteData);
    
    // 统计结果
    $results = array_fill(1, count($voteData['options']), 0);
    foreach ($voteData['votes'] as $uid => $c) {
        if (isset($results[$c])) $results[$c]++;
    }
    
    $resultText = "📊 投票结果\n\n{$voteData['title']}\n\n";
    foreach ($voteData['options'] as $i => $opt) {
        $count = $results[$i + 1];
        $bar = str_repeat('█', $count);
        $resultText .= ($i + 1) . ". {$opt}: {$count}票 {$bar}\n";
    }
    
    $total = count($voteData['votes']);
    $resultText .= "\n共 {$total} 人参与";
    
    reply_message($resultText);
}

// ==================== 功能5: 签到打卡 ====================

function gm_daily_sign($params, $event) {
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    $userId = isset($event['sender']['user_id']) ? $event['sender']['user_id'] : '';
    $nickname = isset($event['sender']['nickname']) ? $event['sender']['nickname'] : '匿名';
    
    if (empty($groupId) || empty($userId)) {
        reply_message('请在群聊中使用');
        return;
    }
    
    $today = date('Y-m-d');
    $signKey = 'gm_sign_' . $groupId . '_' . $userId;
    $signData = kv_get($signKey);
    
    if ($signData && $signData['date'] === $today) {
        $streak = $signData['streak'];
        reply_message("今天已经签到啦~ 连续签到: {$streak}天");
        return;
    }
    
    $streak = ($signData && strtotime($signData['date']) === strtotime('-1 day')) ? $signData['streak'] + 1 : 1;
    
    kv_set($signKey, array(
        'date' => $today,
        'time' => date('H:i:s'),
        'streak' => $streak,
        'nickname' => $nickname
    ));
    
    // 群签到统计
    $statKey = 'gm_sign_stats_' . $groupId . '_' . $today;
    $stats = kv_get($statKey);
    if (!is_array($stats)) $stats = array();
    $stats[$userId] = array('nickname' => $nickname, 'time' => date('H:i:s'));
    kv_set($statKey, $stats);
    
    $signCount = count($stats);
    $encouragements = array('继续保持~', '新的一天加油~', '你是最棒的~', '签到成功!');
    $encourage = $encouragements[array_rand($encouragements)];
    
    reply_message("✅ 签到成功!\n用户: {$nickname}\n时间: {$today} {$signData['time']}\n连续签到: {$streak}天\n本群今日签到: {$signCount}人\n\n{$encourage}");
}

// ==================== 功能6: 黑名单管理 ====================

function gm_blacklist($params, $event) {
    if (!gm_check_admin($event)) return;
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    if (empty($groupId)) { reply_message('请在群聊中使用'); return; }
    
    $action = isset($params['action']) ? $params['action'] : 'list';
    $targetId = isset($params['user_id']) ? trim($params['user_id']) : '';
    
    $blacklist = json_decode(gm_get_config($groupId, 'blacklist', '[]'), true);
    if (!is_array($blacklist)) $blacklist = array();
    
    switch ($action) {
        case 'add':
            if (empty($targetId)) { reply_message('格式: gm_ban add user_id=123456'); return; }
            $reason = isset($params['reason']) ? $params['reason'] : '未指定原因';
            if (!in_array($targetId, $blacklist)) {
                $blacklist[] = $targetId;
                gm_set_config($groupId, 'blacklist', json_encode($blacklist));
                reply_message("已添加黑名单: {$targetId}\n原因: {$reason}");
            } else {
                reply_message("{$targetId} 已在黑名单中");
            }
            break;
            
        case 'del':
            if (empty($targetId)) { reply_message('格式: gm_ban del user_id=123456'); return; }
            $newList = array();
            $removed = false;
            foreach ($blacklist as $id) {
                if ($id !== $targetId) $newList[] = $id;
                else $removed = true;
            }
            if ($removed) {
                gm_set_config($groupId, 'blacklist', json_encode($newList));
                reply_message("已从黑名单移除: {$targetId}");
            } else {
                reply_message("{$targetId} 不在黑名单中");
            }
            break;
            
        default:
            if (empty($blacklist)) {
                reply_message('黑名单为空');
            } else {
                $text = "黑名单列表 (" . count($blacklist) . "人):\n";
                foreach ($blacklist as $i => $id) {
                    $text .= ($i + 1) . ". {$id}\n";
                }
                reply_message($text);
            }
    }
}

// ==================== 功能7: 群统计 ====================

function gm_group_stats($params, $event) {
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    if (empty($groupId)) { reply_message('请在群聊中使用'); return; }
    
    $today = date('Y-m-d');
    $statKey = 'gm_sign_stats_' . $groupId . '_' . $today;
    $stats = kv_get($statKey);
    if (!is_array($stats)) $stats = array();
    
    $totalMembers = 100; // 实际应通过 API 获取
    
    $text = "📊 群统计\n";
    $text .= "群号: {$groupId}\n";
    $text .= "今日签到: " . count($stats) . "人\n";
    
    // 总签到天数
    $totalSignDays = 0;
    $signPrefix = 'gm_sign_' . $groupId . '_';
    // 简化统计，实际应遍历所有用户
    $text .= "\n使用 gm_stats detail 查看详情";
    
    reply_message($text);
}

// ==================== 功能8: 违禁词过滤 ====================

function gm_set_filter($params, $event) {
    if (!gm_check_admin($event)) return;
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    if (empty($groupId)) { reply_message('请在群聊中使用'); return; }
    
    $action = isset($params['action']) ? $params['action'] : 'list';
    $word = isset($params['word']) ? trim($params['word']) : '';
    
    $filters = json_decode(gm_get_config($groupId, 'filters', '[]'), true);
    if (!is_array($filters)) $filters = array();
    
    switch ($action) {
        case 'add':
            if (empty($word)) { reply_message('格式: gm_filter add word="违禁词"'); return; }
            $filters[] = array('word' => $word, 'created_at' => date('Y-m-d'));
            gm_set_config($groupId, 'filters', json_encode($filters, JSON_UNESCAPED_UNICODE));
            reply_message("违禁词已添加: {$word}");
            break;
            
        case 'del':
            if (empty($word)) { reply_message('格式: gm_filter del word="违禁词"'); return; }
            $newFilters = array();
            foreach ($filters as $f) {
                if ($f['word'] !== $word) $newFilters[] = $f;
            }
            gm_set_config($groupId, 'filters', json_encode($newFilters, JSON_UNESCAPED_UNICODE));
            reply_message("违禁词已删除: {$word}");
            break;
            
        default:
            if (empty($filters)) {
                reply_message('未设置违禁词');
            } else {
                $text = "违禁词列表:\n";
                foreach ($filters as $f) {
                    $text .= "- {$f['word']}\n";
                }
                reply_message($text);
            }
    }
}

function gm_check_filter($message, $event) {
    $groupId = isset($event['group_id']) ? $event['group_id'] : '';
    if (empty($message) || empty($groupId)) return false;
    
    $filters = json_decode(gm_get_config($groupId, 'filters', '[]'), true);
    if (!is_array($filters)) return false;
    
    foreach ($filters as $f) {
        if (mb_strpos($message, $f['word']) !== false) {
            return true;
        }
    }
    return false;
}

// ==================== 帮助 ====================

function gm_show_help($params, $event) {
    $help = "📋 群管助手 v2.1.0\n\n";
    $help .= "【入群欢迎】\n";
    $help .= "  gm_welcome 消息\n";
    $help .= "【定时消息】\n";
    $help .= "  gm_timer add time=\"08:00\" content=\"早安\"\n";
    $help .= "  gm_timer del id=1\n";
    $help .= "【关键词回复】\n";
    $help .= "  gm_keyword add keyword=\"hello\" reply=\"hi\"\n";
    $help .= "  gm_keyword del keyword=\"hello\"\n";
    $help .= "【群投票】\n";
    $help .= "  gm_vote title=\"投票\" options=\"A|B|C\"\n";
    $help .= "  gm_vote id=xxx choice=1\n";
    $help .= "【签到打卡】\n";
    $help .= "  gm_sign\n";
    $help .= "【黑名单管理】\n";
    $help .= "  gm_ban add user_id=123456\n";
    $help .= "  gm_ban del user_id=123456\n";
    $help .= "【群统计】\n";
    $help .= "  gm_stats\n";
    $help .= "【违禁词过滤】\n";
    $help .= "  gm_filter add word=\"违禁词\"\n";
    $help .= "  gm_filter del word=\"违禁词\"\n";
    
    reply_message($help);
}
