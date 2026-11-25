<?php
$class    = new Sms();
$template = $class->getTemplateDetails('ClientAreaRegister_clientarea');

if (empty($template['active'])) {
	return null;
}
	
if (isset($_POST['action'])) {
	$action = $_POST['action'];
	$userID = $_POST['user_id'] ?? null;
	$class->setUserid($userID);
	switch ($action) {
		case 'resendOtp':
			$res = $class->sendOtpDetails($userID, true);
			if (!$res['status']) {
				echo json_encode(['code' => 0, 'message' => 'Error while sending OTP: ' . $res['error'], 'data' => []]);
			}
			exit;
		case 'otpVerification':
			$userOTP = $_POST['otp'];
			$userPhone = $class->cleanPhone($_POST['user_phone']);
			$class->setGsmnumber($userPhone);
			if ($class->verifyotp($userOTP)) {
				echo json_encode(['code' => 1, 'message' => 'Your phone number has been verified.', 'data' => []]);
			} else {
				echo json_encode(['code' => 0, 'message' => 'OTP you have entered didn\'t match', 'data' => []]);
			}
			exit;
	}
}

// Action Hooks.
add_hook('AdminAreaClientSummaryPage', 1, function($vars) {
	$class   = new Sms();
	$userID = $vars['userid'];

	if ($userID) {
		$userDetails = mysql_fetch_array($class->getClientDetailsBy($userID));
		$phone = $class->cleanPhone($userDetails['gsmnumber']);
		$res = select_query("mod_Smspby_otp", "*", array('user_id' => $userID, 'phone' => $phone));
		$data = mysql_fetch_array($res); 

		if ($data && $data['verify']) {
			return '<div class="clientsummaryactions">Этот пользователь: <span id="taxstatus" class="csajaxtoggle"><strong class="textgreen">Верифицировал свой номер телефона</strong></span></div>';
		} else {
			return '<div class="verification-banner email-verification alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                &nbsp;
                Этот пользователь еще не верифицировал свой номер телефона                
            </div>';
			
		}
	}
});  

add_hook('ClientAreaHeadOutput', 1, function($vars) {
	return '
		<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css" />
		<script src="//cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.js"></script>
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootbox.js/4.4.0/bootbox.min.js"></script>
	';
});

// Блок на сранице клиента, если номер не подтвержден.
add_hook('ClientAreaPage', 1, function($vars) {
	if (isset($vars['client'])) {
		$class = new Sms();
		$phone = $class->cleanPhone($vars['client']->phonenumber);
		$res = select_query("mod_Smspby_otp", "*", array('user_id' => $vars['client']->id, 'phone' => $phone));
		$data = mysql_fetch_array($res);

		$output['phoneverifycheck'] = isset($data['verify']) ? $data['verify'] : 0;
		$output['phoneverifylink'] = '<a href="javascript::void(0)" id="showOtp" style="pointer-events: auto; cursor: pointer; opacity: 1; float: center; color: white; text-decoration: none; display: block;">Выслать код подтверждения</a>';
		
		return $output;	
	} 
});

add_hook('ClientAreaHeaderOutput', 1, function($vars) {
	if(isset($vars['client'])) {	
		$class   = new Sms();
		$config = $class->getSettings();	
		$return = '';
		
		$cleanPhone = $class->cleanPhone($vars['client']->phonenumber);
		$res = select_query("mod_Smspby_otp", "*", array('user_id' => $vars['client']->id, 'phone' => $cleanPhone));
		$data = mysql_fetch_array($res);
		$base = basename($_SERVER['REQUEST_URI']);
		$filename = explode("?", $base);
		if (empty($data)) {
			$otpInput = '<p class="text-left" style="font-size:23px;">Код активации для ' . $vars['client']->phonenumber . '</p>';
			$button = '<a class="btn btn-success btn-sm btn-block" href="javascript::void(0)" name="resend-otp" style="pointer-events: auto; cursor: pointer; opacity: 1; float: right; font-size:16px;"><i class="fas fa-mobile-alt"></i>&nbsp; Отправить код на телефон</a>';
			$showOneMore = ' style="display: none;"';
		} elseif ($data['resend_count'] >= ($config['sms_count_limit'] - 1) && $config['sms_count_limit'] > 0) {
			$otpInput = '
			<p class="text-left" style="font-size:22px;padding-bottom:10px;">Код активации для ' . $vars['client']->phonenumber . '</p>
			<div class="form-group col-lg-6 col-xs-12">
				<input type="text" name="otp" class="form-control" id="otp" placeholder="Введите код">
				<p class="text-left" style="font-size: 13px; margin-top: 20px;">Вы <span style="color:red;">превысили лимит отправки кода</span> на этот номер. Свяжитесь с <a href="/submitticket.php">технической поддержкой</a> или введите последний высланный код.</p>
			</div>';
			$button = '<button type="submit" class="btn btn-primary">Подтвердить код</button>';
			$showOneMore = ' style="display: none;"';
		} elseif (!$data['verify']) {
			$otpInput = '
			<p class="text-left" style="font-size:23px;">Код активации для ' . $vars['client']->phonenumber . '</p>
			<div class="form-group col-lg-12 col-xs-12">
				<input type="text" name="otp" class="form-control" id="otp" placeholder="Введите код">
			</div>';
			$button = '<button type="submit" class="btn btn-primary">Проверить код</button>';
			$showOneMore = '';
		}
		if(empty($data) || !$data['verify']) {
			if($filename[0] != 'cart.php' && $base != 'clientarea.php?action=details') {
				$return = '
				<!-- Trigger the modal with a button -->
				<!-- Modal -->
				<form method="post" class="using-password-strength" action="" onSubmit="return checkOtpForm(this);" role="form" name="otpVerification" id="frmOtpVerification">
					<input type="hidden" name="action" value="otpVerification">
					<input type="hidden" name="user_id" value="' . $vars['client']->id . '">
					<input type="hidden" name="user_phone" value="' . $vars['client']->phonenumber . '">
					<div id="hook-modal" class="modal fade" role="dialog" style="display: none;">
						<div class="modal-dialog">
							<!-- Modal content-->
							<div class="modal-content">
								<div class="modal-header">
									<div clas="row">
										<div class="col-lg-6">
										<h4 class="modal-title">Активация номера</h4>
										</div>
										<div class="col-lg-6">
											<a href="javascript::void(0)" id="closeOtp" style="pointer-events: auto; cursor: pointer; opacity: 1; float: right;color: #000;font-size: 18px;"><i class="fas fa-times"></i></a>
										</div>
									</div>
									
								</div>
								<div class="modal-body col-lg-12">
									' . $otpInput . '
									<div class="form-group col-lg-6 col-xs-12">
										' . $button . '
									</div>
								</div>
								<div class="modal-footer">
									<hr/>
									<p class="text-center margin-10">
										<a href="javascript::void(0)" id="logout" style="pointer-events: auto; cursor: pointer; opacity: 1; float: left;">Изменить номер</a>
									</p>
									<p class="text-center margin-10"'.$showOneMore.'>
										<a href="javascript::void(0)" id="resend-otp-one-more" name="resend-otp" style="pointer-events: auto; cursor: pointer; opacity: 1; float: right;">Отправить еще раз</a>
										<span id="timer" style="min-width: 80px; float: right; display: none;">00:00:00 sec</span>
									</p>
								</div>
							</div>
						</div>
					</div>
				</form>';
			}
		}
		return $return;		
	}
});

add_hook('ClientAreaFooterOutput', 1, function($vars) {
	if(isset($vars['client'])) {
		$return = '';
		$class = new Sms();
		$phone = $class->cleanPhone($vars['client']->phonenumber);
		$res = select_query("mod_Smspby_otp", "*", array('user_id' => $vars['client']->id, 'phone' => $phone));
		$data = mysql_fetch_array($res);
		$base = basename($_SERVER['REQUEST_URI']);
		$filename = explode("?", $base);
		if(empty($data) || !$data['verify']) {
			$class = new Sms();
			if($filename[0] != 'cart.php' && $base != 'clientarea.php?action=details') {
				$class = new Sms();
				$settings = $class->getSettings();
				$resend_time = !empty($settings['resend_time']) ? $settings['resend_time'] : 15;
				$return = '
				<script type="text/javascript">
					$(window).on(\'load\', function() {
						if (document.cookie.indexOf("otpHide") === -1 || document.cookie.replace(/(?:(?:^|.*;\s*)otpHide\s*\=\s*([^;]*).*$)|^.*$/, "$1") == 0) {
							$(\'#hook-modal\').modal(\'show\');
							$(\'#hook-modal\').modal({ backdrop: \'static\', keyboard: false });
							timerCount();
						}
					});

					function timerCount() {
						var timer = function(secs) {
							var sec_num = parseInt(secs, 10);
							var hours = Math.floor(sec_num / 3600) % 24;
							var minutes = Math.floor(sec_num / 60) % 60;
							var seconds = sec_num % 60;
							hours = hours < 10 ? "0" + hours : hours;
							minutes = minutes < 10 ? "0" + minutes : minutes;
							seconds = seconds < 10 ? "0" + seconds : seconds;
							return [hours, minutes, seconds].join(":");
						};

						document.getElementById("timer").style.display = "block";
						document.getElementById("timer").innerHTML = timer('.$resend_time.') + " sec&nbsp;";
						var counter = '.$resend_time.';
						interval = setInterval(function() {
							counter--;
							document.getElementById("timer").innerHTML = timer(counter) + " sec&nbsp;";
							if (counter == 0) {
								counterRunning = false;
								document.getElementById("timer").style.display = "none";
								var cssString = "pointer-events: auto; cursor: pointer; opacity: 1; float:right";
								document.getElementById("resend-otp-one-more").style.cssText = cssString;
								clearInterval(interval);
							} else {
								document.getElementById("resend-otp-one-more").style.cssText = "pointer-events: none; cursor: default; opacity: 1; float:right";
							}
						}, 1000);
					}

					$("a[name=resend-otp]").click(function(e) {
						timerCount();
						$.ajax({
							url: \'clientarea.php\',
							type: \'post\',
							data: {
								"user_id": $("input[name=user_id]").val(),
								"action": "resendOtp"
							},
							beforeSend: function() {
								// Can we add anything here.
							},
							cache: true,
							dataType: \'json\',
							crossDomain: true,
							success: function(data) {
								console.log(data);
								if (data.code == 1) {
									swal({
										title: "Success!",
										text: data.message,
										type: "success"
									}, function() {
										window.location = "clientarea.php";
									});
								} else {
									bootbox.alert(data.message);
								}
   								location.reload();
							},
							error: function(data) {
								console.log(\'Error:\', data);
								location.reload();
							}
						});
					});

					$("#logout").click(function(e) {
						window.location = "clientarea.php?action=details";
					});

					$("#closeOtp").click(function(e) {
						$(\'#hook-modal\').modal(\'hide\');
						document.cookie = "otpHide=1";
					});

					$("#showOtp").click(function(e) {
						$(\'#hook-modal\').modal(\'show\');
						document.cookie = "otpHide=0";
					});

					function checkOtpForm(elements) {
						if (elements.otp.value == \'\') {
							swal({
								title: "Error!",
								text: "Please Enter OTP",
								type: "error"
							}, function() {
								elements.otp.focus();
							});
						} else {
							$.ajax({
								url: \'clientarea.php\',
								type: \'post\',
								data: $(elements).serialize(),
								beforeSend: function() {
									// Can we add anything here.
								},
								cache: true,
								dataType: \'json\',
								crossDomain: true,
								success: function(data) {
									console.log(data);
									if (data.code == 1) {
										swal({
											title: "Success!",
											text: data.message,
											type: "success"
										}, function() {
											window.location = "clientarea.php";
										});
									} else {
										swal({
											title: "Error!",
											text: data.message,
											type: "error"
										}, function() {
											$("#otp").val("");
										});
									}
								},
								error: function(data) {
									console.log(\'Error:\', data);
								}
							});
						}
						return false;
					}
				</script>';
			}
		}
		return $return;
	}
});
