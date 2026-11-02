<?php
if(PHP_SAPI!=='cli')exit;ob_start();$_SERVER['HTTP_HOST']='127.0.0.1:8095';$_SERVER['REQUEST_URI']='/';require __DIR__.'/.runtime/wordpress/wp-load.php';
$checks=0;function pp($value,$label){global $checks;if(!$value)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
$repo=new FR_Repository();$uid=0;$token=bin2hex(random_bytes(32));$otherToken=bin2hex(random_bytes(32));$oldCookie=$_COOKIE[FR_Auth::cookie_name()]??null;
try{
 $uid=$repo->save_user(0,['username'=>'fr-pass-'.bin2hex(random_bytes(4)),'display_name'=>'Password QA','password'=>'Old-test-2026!','enabled'=>true]);if(is_wp_error($uid))throw new RuntimeException($uid->get_error_message());
 $repo->create_session($uid,$token,time()+3600);$repo->create_session($uid,$otherToken,time()+3600);$_COOKIE[FR_Auth::cookie_name()]=$token;$auth=new FR_Auth($repo);
 $request=function($current,$new,$confirm)use($token){$r=new WP_REST_Request('POST','/'.FR_Api::NS.'/auth/password');$r->set_header('Content-Type','application/json');$r->set_header('Origin',home_url());$r->set_header('X-FR-Request','1');$r->set_header('X-FR-CSRF',hash_hmac('sha256','fitet-ricevute-csrf:'.$token,wp_salt('auth')));$r->set_body(wp_json_encode(['current_password'=>$current,'new_password'=>$new,'confirm_password'=>$confirm]));return $r;};
 $r=$request('Old-test-2026!','New-test-2026!','New-test-2026!');pp($auth->permission($r)===true,'authenticated request with CSRF accepted');$r->set_header('X-FR-CSRF','');pp(is_wp_error($auth->permission($r)),'missing CSRF rejected');
 pp(is_wp_error($auth->change_password($request('Old-test-2026!','New-test-2026!','mismatch'))),'mismatched confirmation rejected');
 pp(is_wp_error($auth->change_password($request('incorrect','New-test-2026!','New-test-2026!'))),'incorrect current password rejected');pp($repo->session($token)!==null&&wp_check_password('Old-test-2026!',$repo->user($uid)['password_hash']),'failed attempts preserve session and password');
 pp(is_wp_error($auth->change_password($request('Old-test-2026!','short','short'))),'short password rejected');
 pp(is_wp_error($auth->change_password($request('Old-test-2026!','Old-test-2026!','Old-test-2026!'))),'unchanged password rejected');
 $before=$repo->user_by_username('fr-tester')['password_hash'];$result=$auth->change_password($request('Old-test-2026!','New-test-2026!','New-test-2026!'));pp(!is_wp_error($result)&&$result['success'],'password updated');
 pp(wp_check_password('New-test-2026!',$repo->user($uid)['password_hash'])&&!wp_check_password('Old-test-2026!',$repo->user($uid)['password_hash']),'new password works and old password no longer works');pp($repo->session($token)===null&&$repo->session($otherToken)===null,'all account sessions revoked');pp($before===$repo->user_by_username('fr-tester')['password_hash'],'other account unchanged');pp(is_wp_error($auth->change_password($request('New-test-2026!','Another-new-2026!','Another-new-2026!'))),'signed-out change rejected');
 echo "$checks password checks passed.\n";
}finally{global $wpdb;if(is_int($uid)&&$uid){$wpdb->delete(FR_Repository::sessions_table(),['user_id'=>$uid]);$wpdb->delete(FR_Repository::users_table(),['id'=>$uid]);delete_transient('fr_password_attempts_'.$uid);}if($oldCookie!==null)$_COOKIE[FR_Auth::cookie_name()]=$oldCookie;else unset($_COOKIE[FR_Auth::cookie_name()]);ob_end_flush();}
