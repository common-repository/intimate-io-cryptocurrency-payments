<?php
function intimate_get_base_url(){
    $testmode = false;
    if ($testmode) {
        return 'https://dev-api.intimate.partners';
    }
    else {
        return 'https://api.intimate.io';
    }
}