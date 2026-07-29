<?php
/**
 * 每日一句 - 每日一条精选名言/语录
 * @version 1.0.0
 * @author wuxiang999
 */

function daily_quote($params, $event) {
    $quotes = [
        ["text" => "生活不止眼前的苟且，还有诗和远方的田野。", "author" => "高晓松"],
        ["text" => "世上只有一种英雄主义，就是在认清生活真相之后依然热爱生活。", "author" => "罗曼·罗兰"],
        ["text" => "读书破万卷，下笔如有神。", "author" => "杜甫"],
        ["text" => "人生如逆旅，我亦是行人。", "author" => "苏轼"],
        ["text" => "天行健，君子以自强不息。", "author" => "周易"],
        ["text" => "知行合一。", "author" => "王阳明"],
        ["text" => "路漫漫其修远兮，吾将上下而求索。", "author" => "屈原"],
        ["text" => "业精于勤，荒于嬉；行成于思，毁于随。", "author" => "韩愈"],
        ["text" => "静以修身，俭以养德。", "author" => "诸葛亮"],
        ["text" => "山重水复疑无路，柳暗花明又一村。", "author" => "陆游"],
        ["text" => "海内存知己，天涯若比邻。", "author" => "王勃"],
        ["text" => "采菊东篱下，悠然见南山。", "author" => "陶渊明"],
        ["text" => "不畏浮云遮望眼，自缘身在最高层。", "author" => "王安石"],
        ["text" => "纸上得来终觉浅，绝知此事要躬行。", "author" => "陆游"],
        ["text" => "学而不思则罔，思而不学则殆。", "author" => "孔子"],
        ["text" => "温故而知新，可以为师矣。", "author" => "孔子"],
        ["text" => "三人行，必有我师焉。", "author" => "孔子"],
        ["text" => "己所不欲，勿施于人。", "author" => "孔子"],
        ["text" => "有志者，事竟成。", "author" => "后汉书"],
        ["text" => "长风破浪会有时，直挂云帆济沧海。", "author" => "李白"],
        ["text" => "天生我材必有用，千金散尽还复来。", "author" => "李白"],
        ["text" => "但愿人长久，千里共婵娟。", "author" => "苏轼"],
    ];

    $idx = array_rand($quotes);
    $q = $quotes[$idx];
    $safe_text = htmlspecialchars($q["text"], ENT_QUOTES, "UTF-8");
    $safe_author = htmlspecialchars($q["author"], ENT_QUOTES, "UTF-8");

    $msg = "【每日一句】\n\n";
    $msg .= $safe_text . "\n\n—— " . $safe_author;

    reply_message($msg);
}

plugin_register("quote", "daily_quote");
plugin_register("每日一句", "daily_quote");
