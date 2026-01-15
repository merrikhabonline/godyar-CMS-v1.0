<?php
if (!headers_sent()) {
// header removed (no output in prepend)
}
if (function_exists('mb_internal_encoding')) {
    @mb_internal_encoding('UTF-8');
}
