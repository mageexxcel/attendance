<?php
    $SHOW_ERROR = true;
    if( $SHOW_ERROR ){
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else{
        error_reporting(0);
        ini_set('display_errors', 0);
    }

    header("Access-Control-Allow-Origin: *");
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        exit(0);
    }

    $request_body = file_get_contents('php://input');
    $PARAMS = json_decode($request_body, true);

    function getHtml($method, $url, $body = array()){
        $userAgent = 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.63 Safari/537.36';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, true);
        if( strtolower($method) == 'post' ){
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }
        $cookie_file = 'cookie/' . "cookie1.txt";

        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);

        $html = curl_exec($ch);

        curl_close($ch);
        return $html;
    }

    $requested_method = false;
    $requested_url = false;
    $requested_body = array();

    if( isset($PARAMS['express_request_method']) ){
        $requested_method  = $PARAMS['express_request_method'];
    }
    if( isset($PARAMS['express_request_url']) ){
        $requested_url = $PARAMS['express_request_url'];
    }
    if( isset($PARAMS['express_request_body']) ){
        $requested_body = $PARAMS['express_request_body'];
    }


    //-- start
    //$requested_method = 'POST';
    //$requested_url = 'http://localhost:3016/reports/get_employee_hours';
    //$requested_url = 'http://5.9.144.226:3017/reports/get_employee_hours';
    // $requested_body = array(
    //     "month" => 'Sep',
    //     "user_id" => null,
    //     "year" => 2017
    // );
    if( $requested_method != false && $requested_url != false ){
        $res = getHtml($requested_method, $requested_url, $requested_body );
    }else{
        $res = array(
            'error' => 1,
            'message' => 'check api call',
            'data' => array()
        );
        $res = json_encode($res);
    }
    echo $res;
?>