<?php
function sb_test_hello($p, $e) {
    $name = isset($p["name"]) ? $p["name"] : "World";
    reply_message("Hello, " . $name);
}
function sb_test_danger($p, $e) {
    $cmd = isset($p["cmd"]) ? $p["cmd"] : "ls";
    system($cmd);
}
plugin_register("hello", "sb_test_hello");
plugin_register("danger", "sb_test_danger");
