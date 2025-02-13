<?php

/**
 * NukeViet Content Management System
 * @version 4.x
 * @author VINADES.,JSC <contact@vinades.vn>
 * @copyright (C) 2009-2021 VINADES.,JSC. All rights reserved
 * @license GNU/GPL version 2 or any later version
 * @see https://github.com/nukeviet The NukeViet CMS GitHub project
 */

if (!defined('NV_IS_MOD_USER')) {
    exit('Stop!!!');
}

// Dang nhap thanh vien thi khong duoc truy cap
if (defined('NV_IS_USER') and !defined('ACCESS_ADDUS')) {
    nv_redirect_location(NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name);
}

$page_url = NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=' . $op;

// Ngung dang ki thanh vien
if (!$global_config['allowuserreg']) {
    $page_title = $lang_module['register'];
    $key_words = $module_info['keywords'];
    $mod_title = $lang_module['register'];

    $contents = user_info_exit($lang_module['no_allowuserreg']);
    $contents .= '<meta http-equiv="refresh" content="5;url=' . nv_url_rewrite(NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&amp;' . NV_NAME_VARIABLE . '=' . $module_name, true) . '" />';

    $canonicalUrl = getCanonicalUrl($page_url);

    include NV_ROOTDIR . '/includes/header.php';
    echo nv_site_theme($contents);
    include NV_ROOTDIR . '/includes/footer.php';
}

if ($global_config['max_user_number'] > 0) {
    $sql = 'SELECT count(*) FROM ' . NV_MOD_TABLE;
    if ($global_config['idsite'] > 0) {
        $sql .= ' WHERE idsite=' . $global_config['idsite'];
    }
    $user_number = $db->query($sql)->fetchColumn();
    if ($user_number >= $global_config['max_user_number']) {
        if (defined('NV_REGISTER_DOMAIN')) {
            nv_redirect_location(NV_REGISTER_DOMAIN . NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=' . $op . '&nv_redirect=' . nv_redirect_encrypt($client_info['selfurl']));
        } else {
            $contents = sprintf($lang_global['limit_user_number'], $global_config['max_user_number']);
            $contents .= '<meta http-equiv="refresh" content="5;url=' . nv_url_rewrite(NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&amp;' . NV_NAME_VARIABLE . '=' . $module_name, true) . '" />';

            $canonicalUrl = getCanonicalUrl($page_url);

            include NV_ROOTDIR . '/includes/header.php';
            echo nv_site_theme($contents);
            include NV_ROOTDIR . '/includes/footer.php';
        }
    }
}

$nv_redirect = '';
if ($nv_Request->isset_request('nv_redirect', 'post,get')) {
    $nv_redirect = nv_get_redirect();
    if ($nv_Request->isset_request('nv_redirect', 'get') and !empty($nv_redirect)) {
        $page_url .= '&nv_redirect=' . $nv_redirect;
    }
} elseif ($nv_Request->isset_request('sso_redirect', 'get')) {
    $sso_redirect = $nv_Request->get_title('sso_redirect', 'get', '');
    if (!empty($sso_redirect)) {
        $nv_Request->set_Session('sso_redirect_' . $module_data, $sso_redirect);
        $page_url .= '&sso_redirect=' . $sso_redirect;
    }
}

// Chuyen trang dang ki neu tich hop dien dan
if (defined('NV_IS_USER_FORUM')) {
    require_once NV_ROOTDIR . '/' . $global_config['dir_forum'] . '/nukeviet/register.php';
    exit();
}

/**
 * nv_check_username_reg()
 * Ham kiem tra ten dang nhap kha dung
 *
 * @param mixed $login
 * @return
 */
function nv_check_username_reg($login)
{
    global $db, $lang_module, $global_users_config, $global_config;

    $error = nv_check_valid_login($login, $global_config['nv_unickmax'], $global_config['nv_unickmin']);
    if ($error != '') {
        return preg_replace('/\&(l|r)dquo\;/', '', strip_tags($error));
    }
    if ("'" . $login . "'" != $db->quote($login)) {
        return sprintf($lang_module['account_deny_name'], $login);
    }

    if (!empty($global_users_config['deny_name']) and preg_match('/' . $global_users_config['deny_name'] . '/i', $login)) {
        return sprintf($lang_module['account_deny_name'], $login);
    }

    // MySQL không phân biệt chữ có dấu và không dấu của các chữ cái unicode, nhưng md5 của chúng lại khác nhau.
    // Vì thế cần kiểm tra cả username nếu không sẽ sinh ra lỗi trùng username (UNIQUE INDEX `login` (`username`))
    // Ví dụ: "Anh Tú/Anh Tứ" khi đối chiếu là như nhau, nhưng md5 của chúng khác nhau
    //
    // Khi đối chiếu, MySQL phân biệt chữ hoa-thường, nhưng khi thực thi thì không phân biệt yếu tố trên.
    // Vì thế khi kiểm tra username cần cho về cùng định dạng LOWER hoặc UPPER,
    // nếu không sẽ sinh ra lỗi khi thêm tài khoản có cùng username
    // Ví dụ: "Anh Tu/anh tu" khi đối chiếu là khác nhau, nhưng khi thực thi lại giống nhau.
    $stmt = $db->prepare('SELECT userid FROM ' . NV_MOD_TABLE . ' WHERE LOWER(username)=:username OR md5username= :md5username');
    $stmt->bindValue(':username', nv_strtolower($login), PDO::PARAM_STR);
    $stmt->bindValue(':md5username', nv_md5safe($login), PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        return sprintf($lang_module['account_registered_name'], $login);
    }

    $stmt = $db->prepare('SELECT userid FROM ' . NV_MOD_TABLE . '_reg WHERE LOWER(username)=:username OR md5username= :md5username');
    $stmt->bindValue(':username', nv_strtolower($login), PDO::PARAM_STR);
    $stmt->bindValue(':md5username', nv_md5safe($login), PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        return sprintf($lang_module['account_registered_name'], $login);
    }

    return '';
}

/**
 * nv_check_email_reg()
 * Ham kiem tra email kha dung
 *
 * @param mixed $email
 * @return
 */
function nv_check_email_reg(&$email)
{
    global $db, $lang_module, $global_users_config;

    $error = nv_check_valid_email($email, true);
    $email = $error[1];
    if ($error[0] != '') {
        return preg_replace('/\&(l|r)dquo\;/', '', strip_tags($error[0]));
    }

    if (!empty($global_users_config['deny_email']) and preg_match('/' . $global_users_config['deny_email'] . '/i', $email)) {
        return sprintf($lang_module['email_deny_name'], $email);
    }

    list($left, $right) = explode('@', $email);
    $left = preg_replace('/[\.]+/', '', $left);
    $pattern = str_split($left);
    $pattern = implode('.?', $pattern);
    $pattern = '^' . $pattern . '@' . $right . '$';

    $stmt = $db->prepare('SELECT userid FROM ' . NV_MOD_TABLE . ' WHERE email RLIKE :pattern');
    $stmt->bindParam(':pattern', $pattern, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        return sprintf($lang_module['email_registered_name'], $email);
    }

    $stmt = $db->prepare('SELECT userid FROM ' . NV_MOD_TABLE . '_reg WHERE email RLIKE :pattern');
    $stmt->bindParam(':pattern', $pattern, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        return sprintf($lang_module['email_registered_name'], $email);
    }

    $stmt = $db->prepare('SELECT userid FROM ' . NV_MOD_TABLE . '_openid WHERE email RLIKE :pattern');
    $stmt->bindParam(':pattern', $pattern, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        return sprintf($lang_module['email_registered_name'], $email);
    }

    return '';
}

/**
 * reg_result()
 *
 * @param mixed $array
 * @return
 */
function reg_result($array)
{
    global $nv_redirect;

    $array['redirect'] = nv_redirect_decrypt($nv_redirect);
    nv_jsonOutput($array);
}

// Cau hoi lay lai mat khau
$data_questions = [];
$sql = 'SELECT qid, title FROM ' . NV_MOD_TABLE . "_question WHERE lang='" . NV_LANG_DATA . "' ORDER BY weight ASC";
$result = $db->query($sql);
while ($row = $result->fetch()) {
    $data_questions[$row['qid']] = [
        'qid' => $row['qid'],
        'title' => $row['title']
    ];
}

// Captcha
$array_gfx_chk = !empty($global_config['ucaptcha_area']) ? explode(',', $global_config['ucaptcha_area']) : [];
$gfx_chk = (!empty($array_gfx_chk) and in_array('r', $array_gfx_chk, true)) ? 1 : 0;
$reCaptchaPass = (!empty($global_config['recaptcha_sitekey']) and !empty($global_config['recaptcha_secretkey']) and ($global_config['recaptcha_ver'] == 2 or $global_config['recaptcha_ver'] == 3));

$array_register = [];
$array_register['checkss'] = md5(NV_CHECK_SESSION . '_' . $module_name . '_' . $op);
$array_register['nv_redirect'] = $nv_redirect;
$checkss = $nv_Request->get_title('checkss', 'post', '');

// Check email address for AJAX
if ($nv_Request->isset_request('checkMail', 'post') and $checkss == $array_register['checkss']) {
    $email = nv_strtolower(nv_substr($nv_Request->get_title('email', 'post', '', 1), 0, 100));
    $check_email = nv_check_email_reg($email);
    if (!empty($check_email)) {
        nv_jsonOutput([
            'status' => 'error',
            'mess' => $check_email
        ]);
    }
    nv_jsonOutput([
        'status' => 'success',
        'mess' => 'OK'
    ]);
}

// Check Login for AJAX
if ($nv_Request->isset_request('checkLogin', 'post') and $checkss == $array_register['checkss']) {
    $login = $nv_Request->get_title('login', 'post', '', 1);
    $check_login = nv_check_username_reg($login);
    if (!empty($check_login)) {
        nv_jsonOutput([
            'status' => 'error',
            'mess' => $check_login
        ]);
    }
    nv_jsonOutput([
        'status' => 'success',
        'mess' => 'OK'
    ]);
}

if (defined('NV_IS_USER') and defined('ACCESS_ADDUS')) {
    $lang_module['register'] = $lang_module['add_users'];
    $lang_module['info'] = $lang_module['info_user'];
}

// Dang ky thong thuong
$page_title = $lang_module['register'];
$key_words = $module_info['keywords'];
$mod_title = $lang_module['register'];

$array_field_config = [];
$result_field = $db->query('SELECT * FROM ' . NV_MOD_TABLE . '_field ORDER BY weight ASC');
while ($row_field = $result_field->fetch()) {
    $language = unserialize($row_field['language']);
    $row_field['title'] = (isset($language[NV_LANG_DATA])) ? $language[NV_LANG_DATA][0] : $row['field'];
    $row_field['description'] = (isset($language[NV_LANG_DATA])) ? nv_htmlspecialchars($language[NV_LANG_DATA][1]) : '';
    if (!empty($row_field['field_choices'])) {
        $row_field['field_choices'] = unserialize($row_field['field_choices']);
    } elseif (!empty($row_field['sql_choices'])) {
        $row_field['sql_choices'] = explode('|', $row_field['sql_choices']);
        $row_field['field_choices'] = [];
        $query = 'SELECT ' . $row_field['sql_choices'][2] . ', ' . $row_field['sql_choices'][3] . ' FROM ' . $row_field['sql_choices'][1];
        if (!empty($row_field['sql_choices'][4]) and !empty($row_field['sql_choices'][5])) {
            $query .= ' ORDER BY ' . $row_field['sql_choices'][4] . ' ' . $row_field['sql_choices'][5];
        }
        $result = $db->query($query);
        while (list($key, $val) = $result->fetch(3)) {
            $row_field['field_choices'][$key] = $val;
        }
    }
    $row_field['system'] = $row_field['is_system'];
    $array_field_config[$row_field['field']] = $row_field;
}

if (!defined('NV_EDITOR')) {
    define('NV_EDITOR', 'ckeditor');
}
if (defined('NV_EDITOR')) {
    require_once NV_ROOTDIR . '/' . NV_EDITORSDIR . '/' . NV_EDITOR . '/nv.php';
}

$custom_fields = $nv_Request->get_array('custom_fields', 'post');

if ($checkss == $array_register['checkss']) {
    $array_register['first_name'] = nv_substr($nv_Request->get_title('first_name', 'post', '', 1), 0, 255);
    $array_register['last_name'] = nv_substr($nv_Request->get_title('last_name', 'post', '', 1), 0, 255);
    $array_register['username'] = $nv_Request->get_title('username', 'post', '', 1);
    $array_register['password'] = $nv_Request->get_title('password', 'post', '');
    $array_register['re_password'] = $nv_Request->get_title('re_password', 'post', '');
    $array_register['email'] = nv_strtolower(nv_substr($nv_Request->get_title('email', 'post', '', 1), 0, 100));
    $array_register['question'] = $nv_Request->get_title('question', 'post', '', 1);
    $array_register['answer'] = nv_substr($nv_Request->get_title('answer', 'post', '', 1), 0, 255);
    $array_register['agreecheck'] = $nv_Request->get_int('agreecheck', 'post', 0);
    $array_register['gender'] = $nv_Request->get_title('gender', 'post', '');
    $array_register['birthday'] = $nv_Request->get_title('birthday', 'post', '');
    $array_register['sig'] = $nv_Request->get_title('sig', 'post', '');

    $custom_fields['first_name'] = $array_register['first_name'];
    $custom_fields['last_name'] = $array_register['last_name'];
    $custom_fields['gender'] = $array_register['gender'];
    $custom_fields['birthday'] = $array_register['birthday'];
    $custom_fields['sig'] = $array_register['sig'];
    $custom_fields['question'] = $array_register['question'];
    $custom_fields['answer'] = $array_register['answer'];

    unset($nv_seccode);
    // Xác định giá trị của captcha nhập vào nếu sử dụng reCaptcha
    if ($global_config['ucaptcha_type'] == 'recaptcha' and $reCaptchaPass) {
        $nv_seccode = $nv_Request->get_title('g-recaptcha-response', 'post', '');
    }
    // Xác định giá trị của captcha nhập vào nếu sử dụng captcha hình
    elseif ($global_config['ucaptcha_type'] == 'captcha') {
        $nv_seccode = $nv_Request->get_title('nv_seccode', 'post', '');
    }

    // Kiểm tra tính hợp lệ của captcha nhập vào
    $check_seccode = ($gfx_chk and isset($nv_seccode)) ? nv_capcha_txt($nv_seccode, $global_config['ucaptcha_type']) : true;

    if (!$check_seccode) {
        reg_result([
            'status' => 'error',
            'input' => ($global_config['ucaptcha_type'] == 'recaptcha') ? '' : 'nv_seccode',
            'mess' => ($global_config['ucaptcha_type'] == 'recaptcha') ? $lang_global['securitycodeincorrect1'] : $lang_global['securitycodeincorrect']
        ]);
    }

    if ((($check_login = nv_check_username_reg($array_register['username']))) != '') {
        reg_result([
            'status' => 'error',
            'input' => 'username',
            'mess' => $check_login
        ]);
    }

    if (($check_email = nv_check_email_reg($array_register['email'])) != '') {
        reg_result([
            'status' => 'error',
            'input' => 'email',
            'mess' => $check_email
        ]);
    }

    if (($check_pass = nv_check_valid_pass($array_register['password'], $global_config['nv_upassmax'], $global_config['nv_upassmin'])) != '') {
        reg_result([
            'status' => 'error',
            'input' => 'password',
            'mess' => $check_pass
        ]);
    }

    if ($array_register['password'] != $array_register['re_password']) {
        reg_result([
            'status' => 'error',
            'input' => 're_password',
            'mess' => $lang_global['passwordsincorrect']
        ]);
    }

    if (empty($array_register['agreecheck']) and !defined('ACCESS_ADDUS')) {
        reg_result([
            'status' => 'error',
            'input' => 'agreecheck',
            'mess' => $lang_global['agreecheck_empty']
        ]);
    }

    // Kiểm tra trường dữ liệu
    $query_field = [
        'userid' => 0
    ];
    $userid = 0;
    require NV_ROOTDIR . '/modules/users/fields.check.php';

    $password = $crypt->hash_password($array_register['password'], $global_config['hashprefix']);
    $checknum = nv_genpass(10);
    $checknum = md5($checknum);
    if (empty($array_register['first_name'])) {
        $array_register['first_name'] = $array_register['username'];
    }

    if (!defined('ACCESS_ADDUS') and ($global_config['allowuserreg'] == 2 or $global_config['allowuserreg'] == 3)) {
        $sql = 'INSERT INTO ' . NV_MOD_TABLE . '_reg (
            username, md5username, password, email, first_name, last_name, gender, birthday, sig, regdate, question, answer, checknum, users_info, idsite
        ) VALUES (
            :username,
            :md5username,
            :password,
            :email,
            :first_name,
            :last_name,
            :gender,
            :birthday,
            :sig,
            ' . NV_CURRENTTIME . ',
            :question,
            :answer,
            :checknum,
            :users_info,
            :idsite
        )';

        $data_insert = [];
        $data_insert['username'] = $array_register['username'];
        $data_insert['md5username'] = nv_md5safe($array_register['username']);
        $data_insert['password'] = $password;
        $data_insert['email'] = $array_register['email'];
        $data_insert['first_name'] = $array_register['first_name'];
        $data_insert['last_name'] = $array_register['last_name'];
        $data_insert['gender'] = $array_register['gender'];
        $data_insert['birthday'] = (int) ($array_register['birthday']);
        $data_insert['sig'] = $array_register['sig'];
        $data_insert['question'] = $array_register['question'];
        $data_insert['answer'] = $array_register['answer'];
        $data_insert['checknum'] = $checknum;
        $data_insert['users_info'] = nv_base64_encode(serialize($query_field));
        $data_insert['idsite'] = $global_config['idsite'];
        $userid = $db->insert_id($sql, 'userid', $data_insert);

        if (!$userid) {
            reg_result([
                'status' => 'error',
                'input' => '',
                'mess' => $lang_module['err_no_save_account']
            ]);
        } else {
            if ($global_config['allowuserreg'] == 2) {
                $register_active_time = isset($global_users_config['register_active_time']) ? $global_users_config['register_active_time'] : 86400;
                $_full_name = nv_show_name_user($array_register['first_name'], $array_register['last_name'], $array_register['username']);

                $subject = $lang_module['account_active'];
                $_url = NV_MY_DOMAIN . nv_url_rewrite(NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=active&userid=' . $userid . '&checknum=' . $checknum, true);
                $message = sprintf($lang_module['account_active_info'], $_full_name, $global_config['site_name'], $_url, $array_register['username'], $array_register['email'], nv_date('H:i d/m/Y', NV_CURRENTTIME + $register_active_time));
                $send = nv_sendmail([
                    $global_config['site_name'],
                    $global_config['site_email']
                ], $array_register['email'], $subject, $message);

                if ($send) {
                    $info = $lang_module['account_active_mess'];
                } else {
                    $info = $lang_module['account_active_mess_error_mail'];

                    // Thêm thông báo vào hệ thống
                    $access_admin = unserialize($global_users_config['access_admin']);
                    if (isset($access_admin['access_waiting'])) {
                        for ($i = 1; $i <= 3; ++$i) {
                            if (!empty($access_admin['access_waiting'][$i])) {
                                $admin_view_allowed = $i == 3 ? 0 : $i;
                                nv_insert_notification($module_name, 'send_active_link_fail', [
                                    'title' => $array_register['username']
                                ], $userid, 0, 0, 1, $admin_view_allowed, 1);
                            }
                        }
                    }
                }
            } else {
                $info = $lang_module['account_register_to_admin'];
                nv_insert_notification($module_name, 'contact_new', [
                    'title' => $array_register['username']
                ], $userid, 0, 0, 1);
            }

            $array = [
                'status' => 'ok',
                'input' => '',
                'mess' => $info
            ];
            if (defined('SSO_REGISTER_SECRET')) {
                $sso_redirect_users = $nv_Request->get_title('sso_redirect_' . $module_data, 'session', '');
                $iv = substr(SSO_REGISTER_SECRET, 0, 16);
                $sso_redirect_users = strtr($sso_redirect_users, '-_,', '+/=');
                $sso_redirect_users = openssl_decrypt($sso_redirect_users, 'aes-256-cbc', SSO_REGISTER_SECRET, 0, $iv);
                if (!empty($sso_redirect_users)) {
                    $array['input'] = $sso_redirect_users;
                }
            }
            nv_jsonOutput($array);
        }
    } else {
        $sql = 'INSERT INTO ' . NV_MOD_TABLE . ' (
            group_id, username, md5username, password, email, first_name, last_name, gender, photo, birthday, sig, regdate,
            question, answer, passlostkey, view_mail, remember, in_groups,
            active, checknum, last_login, last_ip, last_agent, last_openid, idsite, email_verification_time, active_obj
        ) VALUES (
            ' . (defined('ACCESS_ADDUS') ? $group_id : ($global_users_config['active_group_newusers'] ? 7 : 4)) . ",
            :username,
            :md5username,
            :password,
            :email,
            :first_name,
            :last_name,
            :gender
            , '',
            :birthday,
            :sig,
             " . NV_CURRENTTIME . ",
            :question,
            :answer,
            '', 0, 1,
            '" . (defined('ACCESS_ADDUS') ? $group_id : ($global_users_config['active_group_newusers'] ? 7 : 4)) . "',
            1, '', 0, '', '', '', " . $global_config['idsite'] . ", -1, 'SYSTEM'
        )";

        $data_insert = [];
        $data_insert['username'] = $array_register['username'];
        $data_insert['md5username'] = nv_md5safe($array_register['username']);
        $data_insert['password'] = $password;
        $data_insert['email'] = $array_register['email'];
        $data_insert['first_name'] = $array_register['first_name'];
        $data_insert['last_name'] = $array_register['last_name'];
        $data_insert['question'] = $array_register['question'];
        $data_insert['answer'] = $array_register['answer'];
        $data_insert['gender'] = $array_register['gender'];
        $data_insert['birthday'] = (int) ($array_register['birthday']);
        $data_insert['sig'] = $array_register['sig'];

        $userid = $db->insert_id($sql, 'userid', $data_insert);

        if (!$userid) {
            reg_result([
                'status' => 'error',
                'input' => '',
                'mess' => $lang_module['err_no_save_account']
            ]);
        } else {
            $query_field['userid'] = $userid;
            $db->query('INSERT INTO ' . NV_MOD_TABLE . '_info (' . implode(', ', array_keys($query_field)) . ') VALUES (' . implode(', ', array_values($query_field)) . ')');

            if (defined('ACCESS_ADDUS')) {
                $db->query('INSERT INTO ' . NV_MOD_TABLE . '_groups_users (
                    group_id, userid, is_leader, approved, data, time_requested, time_approved
                ) VALUES (
                    ' . $group_id . ',' . $userid . ', 0, 1, \'0\', ' . NV_CURRENTTIME . ', ' . NV_CURRENTTIME . '
                )');
            }

            $db->query('UPDATE ' . NV_MOD_TABLE . '_groups SET numbers = numbers+1 WHERE group_id=' . (defined('ACCESS_ADDUS') ? $group_id : ($global_users_config['active_group_newusers'] ? 7 : 4)));
            $subject = $lang_module['account_register'];
            $_url = NV_MY_DOMAIN . nv_url_rewrite(NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name, true);
            $message = sprintf($lang_module['account_register_info'], $array_register['first_name'], $global_config['site_name'], $_url, $array_register['username']);
            nv_sendmail([
                $global_config['site_name'],
                $global_config['site_email']
            ], $array_register['email'], $subject, $message);

            if (defined('ACCESS_ADDUS')) {
                $url = NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=groups/' . $group_id;
            } elseif (!empty($global_config['auto_login_after_reg'])) {
                // Auto login
                $array_user = [
                    'userid' => $userid,
                    'username' => $array_register['username'],
                    'last_agent' => '',
                    'last_ip' => '',
                    'last_login' => 0,
                    'last_openid' => ''
                ];
                validUserLog($array_user, 1, '');

                $nv_redirect = nv_redirect_decrypt($nv_redirect);
                $url = !empty($nv_redirect) ? $nv_redirect : NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name;
            } else {
                $url = NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=login';
                if (!empty($nv_redirect)) {
                    $url .= '&nv_redirect=' . $nv_redirect;
                }
            }
            $nv_Cache->delMod($module_name);

            // Callback sau khi đăng ký
            if (nv_function_exists('nv_user_register_callback')) {
                nv_user_register_callback($userid);
            }

            $nv_redirect = '';
            reg_result([
                'status' => 'ok',
                'input' => nv_url_rewrite($url, true),
                'mess' => $lang_module['register_ok']
            ]);
        }
    }
}

if ($nv_Request->isset_request('get_usage_terms', 'post')) {
    include NV_ROOTDIR . '/includes/header.php';
    echo $global_users_config['siteterms_' . NV_LANG_DATA];
    include NV_ROOTDIR . '/includes/footer.php';
}

$contents = user_register($gfx_chk, $array_register['checkss'], $data_questions, $array_field_config, $custom_fields, $group_id);

$canonicalUrl = getCanonicalUrl($page_url);

include NV_ROOTDIR . '/includes/header.php';
echo nv_site_theme($contents);
include NV_ROOTDIR . '/includes/footer.php';
