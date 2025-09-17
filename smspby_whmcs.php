<?php
require_once("smsclass.php");
require_once("templater.php");
use SMSpby\Lib\Smspby\Smspby;

$smspby = new Smspby();

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function smspby_whmcs_config() 
{
    $config = [
			"name"        => "SMSp.by",
			"description" => "WHMCS SMS Addon. Больше информации на https://smsp.by/",
			"version"     => "1.1.6",
			"author"      => "SMSp.by",
			"language"    => "russian",
    ];
    return $config;
}

function smspby_whmcs_activate() 
{
    $config = smspby_whmcs_config();
    $query = "CREATE TABLE IF NOT EXISTS `mod_Smspby_otp` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` varchar(11) CHARACTER SET utf8 NOT NULL,
        `phone` varchar(20) CHARACTER SET utf8 NOT NULL UNIQUE,
        `otp` varchar(6) CHARACTER SET utf8 NOT NULL,
        `verify` tinyint(1) DEFAULT 0,
        `resend_count` int(2) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `verify_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
    mysql_query($query);
	
    $query = "CREATE TABLE IF NOT EXISTS `mod_Smspby_messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sender` varchar(40) NOT NULL,
        `to` varchar(18) DEFAULT NULL,
        `text` text,
        `phid` varchar(50) DEFAULT NULL,
        `status` varchar(100) DEFAULT NULL,
        `errors` text,
        `logs` text,
        `user` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
	mysql_query($query);

    $query = "CREATE TABLE IF NOT EXISTS `mod_Smspby_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `api` varchar(40) CHARACTER SET utf8 NOT NULL,
        `apiparams` varchar(500) CHARACTER SET utf8 NOT NULL,
        `wantsmsfield` int(11) DEFAULT NULL,
        `gsmnumberfield` int(11) DEFAULT NULL,
        `resend_time` int(5) DEFAULT NULL,
        `sms_count_limit` int(2) DEFAULT NULL,
        `dateformat` varchar(12) CHARACTER SET utf8 DEFAULT NULL,
        `version` varchar(6) CHARACTER SET utf8 DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
	mysql_query($query);
	
    $query = "INSERT INTO `mod_Smspby_settings` (`api`, `apiparams`, `wantsmsfield`, `gsmnumberfield`, `resend_time`, `sms_count_limit`, `dateformat`, `version`) VALUES (
        'sms', '{\"senderid\":\"\",\"signature\":\"\",\"country_code\":\"\"}', 0, 0, 15, 0, '%d.%m.%y', '{$config['version']}'
    );";
	mysql_query($query);

    $query = "CREATE TABLE IF NOT EXISTS `mod_Smspby_templates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(50) CHARACTER SET utf8 NOT NULL,
        `type` enum('client','admin') CHARACTER SET utf8 NOT NULL,
        `admingsm` varchar(255) CHARACTER SET utf8 NOT NULL,
        `template` varchar(240) CHARACTER SET utf8 NOT NULL,
        `variables` varchar(500) CHARACTER SET utf8 NOT NULL,
        `active` tinyint(1) NOT NULL,
        `extra` varchar(3) CHARACTER SET utf8 NOT NULL,
        `description` text CHARACTER SET utf8,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
	mysql_query($query);
	
    //Creating hooks
	$class = new Sms();
    $class->checkHooks();
    return array(
        'status' => 'success',
        'description' => 'SMSp.by successfully activated :)'
    );
}

function smspby_whmcs_deactivate() 
{
    $query = "DROP TABLE `mod_Smspby_templates`";
	mysql_query($query);
    $query = "DROP TABLE `mod_Smspby_settings`";
    mysql_query($query);
	$query = "DROP TABLE `mod_Smspby_otp`";
    mysql_query($query);
    $query = "DROP TABLE `mod_Smspby_messages`";
    mysql_query($query);

    return array(
        'status' => 'success',
        'description' => 'SMSp.by successfully deactivated :('
    );
}

function smspby_whmcs_upgrade($vars) 
{
    // $version = $vars['version'];
    $class = new Sms();
    $class->checkHooks();
}

function smspby_whmcs_output($vars)
{
    $smspbyTemplatesPath = $_SERVER['DOCUMENT_ROOT'] . '/modules/addons/smspby_whmcs/templates/';
    $config = smspby_whmcs_config();
	$LANG       = $vars['_lang'];
    putenv("TZ=Europe/Minsk");
    
    $class = new Sms();
	$tab   = $_GET['tab'];
    $type = $_GET['type'];
    $templ = new TemplateEngine($smspbyTemplatesPath);
    $templ->assign('LANG', $LANG);
    $templ->assign('type', $type);
    $templ->assign('tab', $tab);
    echo $templ->render('adminMainHeader.twig');

    if (!isset($tab) || $tab == "settings") {
        /* UPDATE SETTINGS */
        if ($_POST['params']) {
			$update = [
                'api'            => $_POST['api'],
                'apiparams'      => htmlspecialchars_decode(json_encode($_POST['params'])),
                'wantsmsfield'    => $_POST['wantsmsfield'],
                'gsmnumberfield'  => $_POST['gsmnumberfield'],
                'dateformat'     => $_POST['dateformat']
            ];
            update_query("mod_Smspby_settings", $update, "");
        }
        /* UPDATE SETTINGS */

		if ($_POST['logout']) {
            $query = "TRUNCATE TABLE `mod_Smspby_settings`";
	        mysql_query($query);
            $query = "INSERT INTO `mod_Smspby_settings` (`api`, `apiparams`, `wantsmsfield`, `gsmnumberfield`, `resend_time`, `sms_count_limit`, `dateformat`, `version`) VALUES ('sms', '{\"senderid\":\"\",\"signature\":\"\",\"country_code\":\"\"}', 0, 0, 15, 0, '%d.%m.%y', '{$config['version']}')";
	        mysql_query($query);
        }

        $apiparams = $class->getParams();
        if ($apiparams['pattern'] == '') {
            $apiparams['pattern'] = '+375';
        }
        
        $templ = new TemplateEngine($smspbyTemplatesPath);
        $templ->assign('LANG', $LANG);
        $templ->assign('apiparams', $apiparams);
        $templ->assign('senderid', $apiparams['senderid']);
        echo $templ->render('adminSettings.twig');
    } elseif ($tab == "templates") {
        if ($_POST['submit']) {
            $where = array("type" => array("sqltype" => "LIKE", "value" => $_GET['type']));
            $result = select_query("mod_Smspby_templates", "*", $where);
            while ($data = mysql_fetch_array($result)) {
                if ($_POST[$data['id'] . '_active'] == "on") {
                    $tmp_active = 1;
                } else {
                    $tmp_active = 0;
                }
                $update = array(
                    "template" => $_POST[$data['id'] . '_template'],
                    "active"   => $tmp_active
                );
                if(isset($_POST[$data['id'] . '_extra'])){
                    $update['extra']= trim($_POST[$data['id'] . '_extra']);
                }
                if(isset($_POST[$data['id'] . '_admingsm'])){
                    $update['admingsm'] = $_POST[$data['id'] . '_admingsm'];
                    $update['admingsm'] = str_replace(" ","",$update['admingsm']);
                }
                update_query("mod_Smspby_templates", $update, "id = " . $data['id']);
            }
        }

        echo '<form action="" method="post">
        <input type="hidden" name="action" value="save" />
            <div class="internalDiv">
                <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3" style="margin:0px;border: 0px;">
                    <tbody>';
        $where = array("type" => array("sqltype" => "LIKE", "value" => $_GET['type']));
        $result = select_query("mod_Smspby_templates", "*", $where);

        while ($data = mysql_fetch_array($result)) {
            if ($data['active'] == 1) {
                $active   = 'checked = "checked"';
				$disabled = '';
            } else {
                $active   = '';
				$disabled = 'readonly=true';
            }
            $desc       = json_decode($data['description']);
            if (isset($desc->$LANG['lang'])) {
                $name   = $desc->$LANG['lang'];
            } else {
                $name   = $data['name'];
            }
			
			$tokenarray = explode(",",$data['variables']);
			    ?>
            <tr>
                <td class="fieldlabel"  style="float:right;"><?php echo $LANG['parameter']; ?></td>
                <td class="<?php echo ''.$data['id'].'_link' ?>">
				<?php
				foreach ($tokenarray as $token) {
					echo '<a href="javascript:void(0)" id="'.$token.'" class="font12 left setalink" data-token="'.$token.'" onclick="insertToken(this)">'.ucfirst(preg_replace("/[{}]/", "", $token)).'</a> | ';
				}
				?></td>
            </tr>
           <?php
            echo '
                <tr>
                    <td class="fieldlabel" width="30%"><input type="checkbox" value="on" id="' . $data['id'] . '_checkbox" name="' . $data['id'] . '_active" ' . $active . ' onchange="togglecheckbox(this)"><label for="' . $data['id'] . '_checkbox" >' . $name . '</label></td>
                    <td class="fieldarea">
                        <textarea name="' . $data['id'] . '_template" class="form-control" '.$disabled.' id="'.$data['id'].'_check">' . $data['template'] . '</textarea>
                    </td>
                </tr>';

            if (!empty($data['extra'])) {
                echo '
                <tr>
                    <td class="fieldlabel" width="30%">'.$LANG['ekstra'].'</td>
                    <td class="fieldarea">
                        <input type="text" name="'.$data['id'].'_extra" value="'.$data['extra'].'">
                    </td>
                </tr>
                ';
            }
            if ($_GET['type'] == "admin") {
                echo '
                <tr>
                    <td class="fieldlabel" width="30%">'.$LANG['admingsm'].'</td>
                    <td class="fieldarea">
                        <input type="text" class="extraField form-control" name="'.$data['id'].'_admingsm" placeholder="e.g. 37525XXX,37529XXXXX" value="'.$data['admingsm'].'">
                    </td>
                </tr>
                ';
            }
            echo '<tr>
                <td colspan="2"><hr></td>
            </tr>';
        }
        echo '
        </tbody>
                </table>
            <p style="text-align:center"><input type="submit" name="submit" class="btn btn-primary save_btn" value="'.$LANG['save'].'"></p>
            </div>
        </form>';
    } elseif ($tab == "messages") {
        // Delete SMS if requested
        if (!empty($_GET['deletesms'])) {
            $smsid = (int)$_GET['deletesms'];
            mysql_query("DELETE FROM `mod_Smspby_messages` WHERE id = '{$smsid}'");
        }

        // Pagination values
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = (isset($_GET['limit']) && $_GET['limit'] <= 50) ? (int)$_GET['limit'] : 10;
        $start = ($page > 1) ? ($page * $limit) - $limit : 0;
        $order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

        // Get messages ordered by date
        $sql = "SELECT `m`.*, `user`.`firstname`, `user`.`lastname`
                FROM `mod_Smspby_messages` as `m`
                JOIN `tblclients` as `user` ON `m`.`user` = `user`.`id`
                ORDER BY `m`.`created_at` {$order} LIMIT {$start}, {$limit}";
        $messagesRes = mysql_query($sql);

        // Get total records
        $res = mysql_query("SELECT count(id) as toplam FROM `mod_Smspby_messages`");
        $totalData = mysql_fetch_array($res);
        $toplam = $totalData['toplam'];

        // Page calculation
        $sayfa = ceil($toplam / $limit);

        $messages = [];
        $phids = [];
        while ($data = mysql_fetch_array($messagesRes)) {
            if ($data['phid']) {
                $phids[$data['phid']] = '';
            }
            $messages[] = $data; // Collect all messages
        }
        if (!empty($phids)) {
            $reports = $class->getReports(array_keys($phids));
            foreach ($reports as $phid => $status) {
                $phids[$phid] = $status;
                mysql_query("UPDATE mod_Smspby_messages SET status = '{$status}' WHERE phid = '{$phid}'");
            }
            // Update statuses in $messages array
            foreach ($messages as &$msg) {
                if (!empty($msg['phid']) && isset($phids[$msg['phid']])) {
                    $msg['status'] = $phids[$msg['phid']];
                }
            }
            unset($msg);
        }

        $listPages = "";
        for ($a = 1; $a <= $sayfa; $a++) {
            $selected = ($page == $a) ? 'selected="selected"' : '';
            $listPages .= "<option value='addonmodules.php?module=smspby_whmcs&tab=messages&page={$a}&limit={$limit}&order={$order}' {$selected}>{$a}</option>";
        }

        $templ = new TemplateEngine($smspbyTemplatesPath);
        $templ->assign('LANG', $LANG);
        $templ->assign('messages', $messages);
        $templ->assign('pages', $listPages);
        echo $templ->render('adminMessages.twig');
    } elseif ($tab=="sendbulk") {
        $apiparams = $class->getParams();
        if(!empty($_POST['client'])){
            $userinf     = explode("_", $_POST['client']);
            $userid      = $userinf[0];
            $gsmnumber   = $userinf[1];
            $replacefrom = ["{firstname}", "{lastname}"];
            $replaceto   = [$userinf[2], $userinf[3]];
            $message     = str_replace($replacefrom, $replaceto, $_POST['message']);
            $class->setGsmnumber($gsmnumber);
            $class->setMessage($message);
            $class->setUserid($userid);
            $result = $class->send();
            if ($result == false) {
                $responseToShow = $class->getErrors();
            } else {
                $responseToShow = $LANG['smssent'] . ' ' . $gsmnumber;
            }
        }

        $userSql = "SELECT `a`.`id`,`a`.`firstname`, `a`.`lastname`, `a`.`country`, `a`.`phonenumber` as `gsmnumber` FROM `tblclients` as `a` order by `a`.`firstname`";
        $clients = '';
        $result = mysql_query($userSql);
        while ($data = mysql_fetch_array($result)) {
            $clients .= '<option value="'.$data['id'].'_'.$data['gsmnumber'].'_'.$data['firstname'].'_'.$data['lastname'].'_'.$data['country'].'">'.$data['firstname'].' '.$data['lastname'].' (#'.$data['id'].')</option>';
        }
        echo  '<script src="https://www.smsalert.co.in/js/sms-char-length.js"></script>';
        echo '
        <script>
        jQuery.fn.filterByText = function(textbox, selectSingleMatch) {
          return this.each(function() {
            var select = this;
            var options = [];
            $(select).find("option").each(function() {
              options.push({value: $(this).val(), text: $(this).text()});
            });
            $(select).data("options", options);
            $(textbox).bind("change keyup", function() {
              var options = $(select).empty().scrollTop(0).data("options");
              var search = $.trim($(this).val());
              var regex = new RegExp(search,"gi");

              $.each(options, function(i) {
                var option = options[i];
                if(option.text.match(regex) !== null) {
                  $(select).append(
                     $("<option>").text(option.text).val(option.value)
                  );
                }
              });
              if (selectSingleMatch === true && 
                  $(select).children().length === 1) {
                $(select).children().get(0).selected = true;
              }
            });
          });
        };
        $(function() {
          $("#clientdrop").filterByText($("#textbox"), true);
        });  
        </script>';
        echo '<form action="" method="post">
        <input type="hidden" name="action" value="save" />
            <div class="internalDiv" >'.$responseToShow.'
                <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3" style="margin:0px;border: 0px;">
                    <tbody>
                        <tr>
                            <td class="fieldlabel" width="30%">'.$LANG['client'].'</td>
                            <td class="fieldarea">
                                <input id="textbox" type="text" placeholder="Type client name" style="width:100% !important;padding:5px" class="form-control"><br>
                                <select name="client" class="form-control" multiple id="clientdrop" style="padding:5px">
                                    <option value="">'.$LANG['selectclient'].'</option>
                                    ' . $clients . '
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td class="fieldlabel" width="30%">'.$LANG['message'].'</td>
                            <td class="fieldarea">
                               <textarea rows="5" name="message" style="padding:5px" class="form-control textarea_message" id="SmsSmscontent"></textarea><div class="smscounter right"><span id="smscount">1</span> SMS </div><div class="right" id="character">Character(s),</div>
                               <div id="counttxt" class="right">
                               <div id="count">0</div></div>
                            </td>
                        </tr>
                        <tr>
                            <td class="fieldlabel" width="30%">'.$LANG['parameter'].'</td>
                            <td class="fieldarea">
                                {firstname},{lastname}<br>
								
                            </td>
                        </tr>
						<tr>
						<td class="fieldlabel" width="30%"></td>
						<td class="fieldarea"><button type="submit" class="save_btn btn btn-sm btn-primary">'.$LANG['send'].'</button></td>
						</tr>
                    </tbody>
                </table>
            
            </div>
        </form>';


    } elseif ($tab == "advanced_settings") {
        if ($_POST['submit']) {
            $update = array(
                'resend_time' => $_POST['resend_timer'],
                'sms_count_limit' => $_POST['sms_count_limit']
            );
            update_query("mod_Smspby_settings", $update, "");
        }
		$settings = $class->getSettings();
		$resend_time = !empty($settings['resend_time']) ? $settings['resend_time'] : 15;
        $sms_count_limit = !empty($settings['sms_count_limit']) ? $settings['sms_count_limit'] : 0;

        $templ = new TemplateEngine($smspbyTemplatesPath);
        $templ->assign('LANG', $LANG);
        $templ->assign('resend_time', $resend_time);
        $templ->assign('sms_count_limit', $sms_count_limit);
        echo $templ->render('adminAdvancedSettings.twig');
    }

    $credit =  $class->getBalance();
    if (!$credit) {
        $credit = 'error';
    }
    $templ = new TemplateEngine($smspbyTemplatesPath);
    $templ->assign('LANG', $LANG);
    $templ->assign('balance', $credit);
    $templ->assign('sms_count_limit', $sms_count_limit);
    echo $templ->render('adminMainFooter.twig');
}
