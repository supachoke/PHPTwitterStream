#!/usr/bin/php -q
<?php
	error_reporting (E_ALL);
	set_time_limit (0);//run script forever
	ignore_user_abort ();//run script in background
	ini_set('memory_limit', '50M');
	date_default_timezone_set ('Asia/Bangkok');
	define ('URL', 'https://stream.twitter.com/1.1/statuses/');

	//-- twitter oauth filter param
	define('TWITTER_CONSUMER_KEY','Your consumer key');
	define('TWITTER_CONSUMER_SECRET','Your consumer secret');
	define('OAUTH_TOKEN', 'Your access token');//access token
	define('OAUTH_SECRET', 'Your access token secret');//access token secret

	//-- Public locations --
	$location = array(-180, -90, 180, 90);//all around the world

	//-- find tracking --
	$track = array("#ai");

	//-- follow --
	$follow = array(
		759251,//@CNN
		1367531//@FoxNews
	);

	echo date ('Y-m-d G:i:s') . " : set track : " . implode(',', $track) . "\n";
	echo date ('Y-m-d G:i:s') . " : set follow : " . implode(',', $follow) . "\n";
	echo date ('Y-m-d G:i:s') . " : set location : " . implode(',', $location) . "\n";
	//-- find tracking --

	$backoff_network_error = $use_backoff_network_error = 0.25*1000000;//microsecs
	$backoff_network_error_max = 16000000;//microsecs
	$backoff_http_error = $use_backoff_http_error = 5;
	$backoff_http_error_max = 320;
	$backoff_rate_limit = $use_backoff_rate_limit = $report_period = 60;
	$backoff_internal_error = 1;
	$total_time_diff = $total_time_before = $cnt = $time_before = $time_diff = $bof_cnt = 0;
	$max_bof_cnt = 10;

	while(1){
		echo date('Y-m-d H:i:s') . " | => : Start connection\n";
		$total_time_diff = $total_time_before = $cnt = $time_before = $time_diff = $total_speed = $total_time_diff_2 = $bof_cnt = $chk_time_before = 0;
		$globaldata = '';
		$return = read_the_stream(array('delimited'=>'length', 'filter_level'=>'none', 'follow'=>implode(',', $follow), 'track'=>implode(',', $track), 'locations'=>implode(',', $location)));
		if ($return === false) {
			echo date('Y-m-d H:i:s') . " | Internal server error. Sleep " . $backoff_internal_error . " second\n";
			sleep($backoff_internal_error);
		}
		echo date('Y-m-d H:i:s') . " | Deconnection\n";
	}
	echo "Finish\n";
	exit(0);
	function read_the_stream($param){
		global $use_backoff_network_error, $use_backoff_http_error, $use_backoff_rate_limit, $backoff_network_error, $backoff_http_error, $backoff_rate_limit, $backoff_network_error_max, $backoff_http_error_max, $backoff_internal_error, $bof_cnt, $max_bof_cnt, $db, $globaldata;

		$oauth_header = gen_oauth_header($param);

		if (!empty ($oauth_header)) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, URL . 'filter.json');
			curl_setopt($ch, CURLOPT_ENCODING, 'deflate, gzip');
			curl_setopt($ch, CURLOPT_NOBODY, 0);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_USERAGENT, 'PHPTwitterStream 1.1');
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
			curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
			curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 90);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: stream.twitter.com', 'Authorization: ' . $oauth_header, 'Expect:', 'X-PHPTwitterStream: 1', 'X-PHPTwitterStream-Version: 1.1'));
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, 'write_callback');
			while (1) {
				if (!$result = curl_exec($ch)) {
					if (!isset ($use_backoff_network_error)) $use_backoff_network_error = $backoff_network_error;
					elseif (empty ($use_backoff_network_error)) $use_backoff_network_error = $backoff_network_error;
					echo date('Y-m-d H:i:s') . " | Network error => " . curl_error($ch) . " (" . curl_errno($ch) . "). Sleep " . $use_backoff_network_error . " microsecs\n";
					usleep($use_backoff_network_error);
					$use_backoff_network_error += $backoff_network_error;
					if ($use_backoff_network_error >= $backoff_network_error_max) $use_backoff_network_error = $backoff_network_error_max;
				} else {
					echo date('Y-m-d H:i:s') . " | Twitter Streaming Operation Complete!\n";
					if (curl_errno($ch)) {
						echo date('Y-m-d H:i:s') . " | Curl Error => " . curl_error($ch) . " (" . curl_errno($ch) . "). Sleep " . $backoff_internal_error . " second\n";
						sleep($backoff_internal_error);
					} else {
						$info = curl_getinfo($ch);
						if ($info["http_code"] == 420) {
							if (!isset ($use_backoff_rate_limit)) $use_backoff_rate_limit = $backoff_rate_limit;
							elseif (empty ($use_backoff_rate_limit)) $use_backoff_rate_limit = $backoff_rate_limit;
							echo date('Y-m-d H:i:s') . " | Stop http_status 420 rate limit => " . print_r($info, true) . "\n$result\nSleep " . $use_backoff_rate_limit . " secs\n";
							sleep($use_backoff_rate_limit);
							$use_backoff_rate_limit *= 2;
						} elseif ($info["http_code"] == 200) {
							$use_backoff_network_error = $use_backoff_http_error = $use_backoff_rate_limit = $bof_cnt = 0;
							echo date('Y-m-d H:i:s') . " | Continue http_status 200 => " . print_r($info, true) . "\n$result\n";
						} else {
							if (!isset ($use_backoff_http_error)) $use_backoff_http_error = $backoff_http_error;
							elseif (empty ($use_backoff_http_error)) $use_backoff_http_error = $backoff_http_error;
							echo date('Y-m-d H:i:s') . " | Stop http error => " . print_r($info, true) . "\n$result\nSleep " . $use_backoff_http_error . " secs\n";
							if ($info["http_code"] == 401) {
								$oauth_header = gen_oauth_header($param);
								if (!empty ($oauth_header)) {
									echo date('Y-m-d H:i:s') . " | create new Oauth credential for re-curl_exec. time $bof_cnt\n";
									curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: stream.twitter.com', 'Authorization: ' . $oauth_header, 'Expect:', 'X-PHPTwitterStream: 1', 'X-PHPTwitterStream-Version: 1.1'));
								} else {
									echo date('Y-m-d H:i:s') . " | Cannot generate re-oauth_header!\n";
									return false;
								}
							} else echo date('Y-m-d H:i:s') . " | Unknow error\n" . print_r($info, true) . "\n\n";
							sleep($use_backoff_http_error);
							$use_backoff_http_error *= 2;
							if ($use_backoff_http_error >= $backoff_http_error_max) {
								$use_backoff_http_error = $backoff_http_error_max;
								$bof_cnt++;
								if ($bof_cnt >= $max_bof_cnt) {
									$bof_cnt = 0;
									echo date('Y-m-d H:i:s') . " | http error max > $max_bof_cnt\n";
									return false;
								}
							}
						}
					}
				}
			}//while
			echo date('Y-m-d H:i:s') . " | Close connection!\n";
			curl_close($ch);
			return false;
		} else {
			echo date('Y-m-d H:i:s') . " | Cannot generate oauth_header!\n";
			return false;
		}
	}
	function gen_oauth_header($param){
		try {
			$oauth = new OAuth (TWITTER_CONSUMER_KEY, TWITTER_CONSUMER_SECRET, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_AUTHORIZATION);
			$oauth->setToken (OAUTH_TOKEN, OAUTH_SECRET);
			$oauth->setNonce(md5(uniqid(mt_rand(), true)));
			$oauth->setRequestEngine(OAUTH_REQENGINE_CURL);
			$oauth->setTimestamp(time());
			$oauth_header = $oauth->getRequestHeader('POST', URL . 'filter.json', $param);
			return $oauth_header;
		} catch(OAuthException $E) {
			echo "Exception caught!\n";
			echo "Response: ". $E->lastResponse . "\n";
			echo date('Y-m-d H:i:s') . " | Oauth error. Exception caught! Response : " . $E->lastResponse . "\n";
			return false;
		}
	}
	function write_callback($ch, $data) {
		global $use_backoff_network_error, $use_backoff_http_error, $use_backoff_rate_limit, $total_time_diff, $total_time_before, $cnt, $time_diff, $time_before, $total_time_diff_2, $total_speed, $pheanstalk, $report_period, $db, $globaldata;
		if (strlen ($data) > 2) {
			$posstart = strpos($data, "\r\n{");
			$posstop = strpos($data, "}\r\n");
			if ($posstart !== false and $posstop !== false) {
				$start = $stop = 1;
				$globaldata = substr ($data, $posstart);
			} elseif ($posstart !== false and $posstop === false) {
				$start = 1;
				$stop = 0;
				$globaldata = substr ($data, $posstart);
			} elseif ($posstart === false and $posstop === false) {
				$start = $stop = 0;
				$globaldata .= $data;
			} elseif ($posstart === false and $posstop !== false) {
				$start = 0;
				$stop = 1;
				$globaldata .= $data;
			}

			if ($stop == 1) {
				// Check if any error occurred
				if (!curl_errno($ch)) {
					$info = curl_getinfo($ch);
					if ($info['http_code'] == 200) $use_backoff_network_error = $use_backoff_http_error = $use_backoff_rate_limit = 0;
					$total_time_diff = $info['total_time'] - $total_time_before;
					$total_time_before = $info['total_time'];
					$total_time_diff_2 += $total_time_diff;
					$total_speed += $info['speed_download'];
					$cnt++;
					if ($time_before > 0) $time_diff = time()-$time_before;
					if ($time_diff >= $report_period or $time_diff <= 0) $time_before = time();
					if ($time_diff >= $report_period) {//per minute
						$tt = $cnt/$time_diff;//tweets per sec
						$avg = $total_time_diff_2/$cnt;
						$sp = byte_convert($total_speed/$cnt);
						$cnt = $time_diff = $total_speed = $total_time_diff_2 = 0;
					}

					if (empty ($cnt)) echo date('Y-m-d H:i:s') . " | Took average " . $avg . " seconds/tweet, rate " . $tt . " tweets/sec, data transfer speed " . $sp . "/sec\n";
				} else echo date('Y-m-d H:i:s') . " | Error was found in write_callback : " . curl_error($ch) . " (" . curl_errno($ch) . ")\n$data\n";

				$json = $globaldata;
				echo date('Y-m-d H:i:s') . " | $json\n\n";
				unset ($globaldata);
				$data2 = str_replace (array('\"', "'", "\\n"), array('\\\\"', "\\'", "\\\\n"), $json);
				$data2 = json_decode ($json, true);
                echo date('Y-m-d H:i:s') . " | " . print_r($data2, true) . "\n\n";
			}
		}
		return strlen($data);
	}
	function byte_convert($bytes) {
		$symbol = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		$exp = 0;
		$converted_value = 0;
		if( $bytes > 0 ) {
			$exp = floor( log($bytes)/log(1024) );
			$converted_value = ( $bytes/pow(1024,floor($exp)) );
		}
		return sprintf( '%.2f '.$symbol[$exp], $converted_value );
	}
?>