<?php
/*
 * Copyright 2011 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
include_once __DIR__ . '/../vendor/autoload.php';
include_once "templates/base.php";
include_once __DIR__ . '/../../../../../connection.php';
/* * ***********************************************
 * Ensure you've downloaded your oauth credentials
 * ********************************************** */
if (isset($_GET['token']) || (isset($_GET['code']) && $_GET['code'] !="")) {
    if (isset($_GET['token']) && $_GET['token'] != "") {
        $token = $_GET['token'];
        $q = "select * from login_tokens where token='$token' ";
        $runquery = mysqli_query($link, $q) or die(mysqli_error($link));
        $row = array();
        while ($r = mysqli_fetch_assoc($runquery)) {
            $row = $r;
        }
        if (sizeof($row) <= 0) {
            echo "Login token expire.Please login again";
            die;
        }
        $qu = "select * from users where id=" . $row['userid'];
        $runqu = mysqli_query($link, $qu) or die(mysqli_error($link));
        while ($s = mysqli_fetch_assoc($runqu)) {
            if (strtolower($s['type']) != "admin") {
                echo "You are not authorize to visite this page.";
                die;
            }
        }
    }
    if (!$oauth_credentials = getOAuthCredentialsFile()) {
        echo missingOAuth2CredentialsWarning();
        return;
    }
    /*     * **********************************************
     * The redirect URI is to the current page, e.g:
     * http://localhost:8080/simple-file-upload.php
     * ********************************************** */
    $redirect_uri = 'https://hr.excellencetechnologies.in/attendance/sal_info/google-api/drive_file/';
//    $redirect_uri = 'http://localhost/hr/attendance_backup/attendance/sal_info/google-api/drive_file/';
    $client = new Google_Client();
    $client->setAuthConfig($oauth_credentials);
    $client->setRedirectUri($redirect_uri);
    $client->addScope("https://www.googleapis.com/auth/drive");
    $service = new Google_Service_Drive($client);
    if (isset($_REQUEST['logout'])) {
        unset($_SESSION['upload_token']);
    }
    /*     * **********************************************
     * If we have a code back from the OAuth 2.0 flow,
     * we need to exchange that with the
     * Google_Client::fetchAccessTokenWithAuthCode()
     * function. We store the resultant access token
     * bundle in the session, and redirect to ourself.
     * ********************************************** */
    if (isset($_GET['code'])) {
        $q = "SELECT * FROM config WHERE type = 'google_payslip_drive_token'";
        $runquery = mysqli_query($link, $q) or die(mysqli_error($link));
        $row = array();
        while ($r = mysqli_fetch_assoc($runquery)) {
            $row = $r;
        }
        if (sizeof($row) > 0) {
            $q2 = "DELETE FROM config WHERE type = 'google_payslip_drive_token'";
            $runquery2 = mysqli_query($link, $q2) or die(mysqli_error($link));
        }
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);
        $email = "";
//    if ($client->getAccessToken()) {
//        $token_data = $client->verifyIdToken();
//        $email = $token_data['email'];
//    }
//    echo "<pre>";
//    print_r($client);
        // store in the session also
        $_SESSION['upload_token'] = $token;
        $refresh_token = $token['refresh_token'];
        $query = "INSERT INTO config (type, value, email_id) VALUES ('google_payslip_drive_token', '$refresh_token', '$email' )";
        mysqli_query($link, $query) or die(mysqli_error($link));
        echo "Refresh token of $email saved to database. Please redirect to homepage";
    }
// set the access token as part of the client
    if (!empty($_SESSION['upload_token'])) {
        $client->setAccessToken($_SESSION['upload_token']);
        if ($client->isAccessTokenExpired()) {
            unset($_SESSION['upload_token']);
        }
    } else {
        $authUrl = $client->createAuthUrl();
    }
//print_r($_SESSION);
//print_r($userProfile);
    ?>

    <div class="box">
        <?php if (isset($authUrl)): ?>
            <div class="request">
                <a class='login' href='<?= $authUrl ?>' target="_blank">Connect Me!</a>
            </div>
        <?php endif ?>
    </div> 

    <?php
} else {
    echo "Token is not present. Please provide token in url";
}