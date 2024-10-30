<?php
function intimate_get_base_url(){
    $testmode = true;
    if ($testmode) {
        return 'https://dev-api.intimate.partners';
    }
    else {
        return 'https://api.intimate.io';
    }
}