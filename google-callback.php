<?php
require_once __DIR__ . '/includes/auth.php';
if (!hash_equals($_SESSION['cs_google_state'] ?? '', $_GET['state'] ?? '') || empty($_GET['code'])) exit('Invalid Google sign-in request.');
unset($_SESSION['cs_google_state']);
$body=http_build_query(['code'=>$_GET['code'],'client_id'=>CS_GOOGLE_CLIENT_ID,'client_secret'=>CS_GOOGLE_CLIENT_SECRET,'redirect_uri'=>cs_google_redirect_uri(),'grant_type'=>'authorization_code']);
$ctx=stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded','content'=>$body,'ignore_errors'=>true]]);
$token=json_decode(file_get_contents('https://oauth2.googleapis.com/token',false,$ctx),true);
if (empty($token['access_token'])) exit('Google sign-in could not be completed.');
$profile=json_decode(file_get_contents('https://openidconnect.googleapis.com/v1/userinfo',false,stream_context_create(['http'=>['header'=>'Authorization: Bearer '.$token['access_token']]])),true);
if (empty($profile['email']) || empty($profile['sub']) || empty($profile['email_verified'])) exit('A verified Google email is required.');
$u=cs_user_row_by_email($profile['email']); $db=cs_db();
if (!$u) { $q=$db->prepare("INSERT INTO users(name,email,google_sub,avatar_url,auth_provider) VALUES(?,?,?,?, 'google')"); $q->execute([$profile['name'] ?? $profile['email'],$profile['email'],$profile['sub'],$profile['picture'] ?? null]); $id=(int)$db->lastInsertId(); $db->prepare("INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE code='consumer'")->execute([$id]); }
else { $db->prepare("UPDATE users SET google_sub=?,avatar_url=?,auth_provider='google' WHERE id=?")->execute([$profile['sub'],$profile['picture'] ?? null,$u['id']]); }
cs_set_session(cs_user_row_by_email($profile['email'])); header('Location: '.cs_home_for_user()); exit;
