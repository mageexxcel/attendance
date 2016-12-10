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
//echo pageHeader("File Upload - Uploading a simple file");
/* * ***********************************************
 * Ensure you've downloaded your oauth credentials
 * ********************************************** */
if (!$oauth_credentials = getOAuthCredentialsFile()) {
    echo missingOAuth2CredentialsWarning();
    return;
}
/* * **********************************************
 * The redirect URI is to the current page, e.g:
 * http://localhost:8080/simple-file-upload.php
 * ********************************************** */
$redirect_uri = 'https://hr.excellencetechnologies.in/attendance/sal_info/google-api/drive_file/';
//$redirect_uri = 'http://localhost/hr/attendance_backup/attendance/sal_info/google-api/drive_file/';
$client = new Google_Client();
$client->setAuthConfig($oauth_credentials);
$client->setRedirectUri($redirect_uri);
$client->addScope("https://www.googleapis.com/auth/drive");
$service = new Google_Service_Drive($client);
// add "?logout" to the URL to remove a token from the session
if (isset($_REQUEST['logout'])) {
    unset($_SESSION['upload_token']);
}
/* * **********************************************
 * If we have a code back from the OAuth 2.0 flow,
 * we need to exchange that with the
 * Google_Client::fetchAccessTokenWithAuthCode()
 * function. We store the resultant access token
 * bundle in the session, and redirect to ourself.
 * ********************************************** */
$client->refreshToken($refresh_token);
$newtoken = $client->getAccessToken();
//echo "<pre>";
//print_r($newtoken);
//die;
$_SESSION['upload_token'] = $newtoken;
// set the access token as part of the client
if (!empty($_SESSION['upload_token'])) {
 
    $client->setAccessToken($_SESSION['upload_token']);
  
    if ($client->isAccessTokenExpired()) {
  $_SESSION['upload_token'] = $client->refreshToken($refresh_token);
        unset($_SESSION['upload_token']);
    }
  
} else {
    
    $authUrl = $client->createAuthUrl();
}
$pageToken = null;
$arr = array();
do {
    $response = $service->files->listFiles(array(
        'q' => "mimeType='application/vnd.google-apps.folder'",
        'spaces' => 'drive',
        'pageToken' => $pageToken,
        'fields' => 'nextPageToken, files(id, name)',
    ));
    foreach ($response->files as $file) {
        if (!array_key_exists($file->name, $arr)) {
            $arr[$file->name] = $file->id;
        }
    }
} while ($pageToken != null);
?>