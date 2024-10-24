<?php
	global $uojSupportedLanguages, $uojMainJudgerWorkPath;
	$uojSupportedLanguages = array('C', 'C++', 'C++11', 'Java8', 'Java11', 'Pascal', 'Python2', 'Python3');
	$uojMainJudgerWorkPath = "/opt/uoj/judger/uoj_judger";
	
	function authenticateJudger() {
		if (!is_string($_POST['judger_name']) || !is_string($_POST['password'])) {
			return false;
		}
		$esc_judger_name = DB::escape($_POST['judger_name']);
		$judger = DB::selectFirst("select password from judger_info where judger_name = '$esc_judger_name'");
		if ($judger == null) {
			return false;
		}
		return $judger['password'] == $_POST['password'];
	}
	
	function judgerCodeStr($code) {
		switch ($code) {
			case 0:
				return "Accepted";
			case 1:
				return "Wrong Answer";
			case 2:
				return "Runtime Error";
			case 3:
				return "Memory Limit Exceeded";
			case 4:
				return "Time Limit Exceeded";
			case 5:
				return "Output Limit Exceeded";
			case 6:
				return "Dangerous Syscalls";
			case 7:
				return "Judgement Failed";
			default:
				return "No Comment";
		}
	}
	
	class StrictFileReader {
		private $f;
		private $buf = '', $off = 0;

		public function __construct($file_name) {
			$this->f = fopen($file_name, 'r');
		}

		public function failed() {
			return $this->f === false;
		}

		public function readChar() {
			if (isset($this->buf[$this->off])) {
				return $this->buf[$this->off++];
			}
			return fgetc($this->f);
		}
		public function unreadChar($c) {
			$this->buf .= $c;
			if ($this->off > 1000) {
				$this->buf = substr($this->buf, $this->off);
				$this->off = 0;
			}
		}

		public function readString() {
			$str = '';
			while (true) {
				$c = $this->readChar();
				if ($c === false) {
					break;
				} elseif ($c === " " || $c === "\n" || $c === "\r") {
					$this->unreadChar($c);
					break;
				} else {
					$str .= $c;
				}
			}
			return $str;
		}
		public function ignoreWhite() {
			while (true) {
				$c = $this->readChar();
				if ($c === false) {
					break;
				} elseif ($c === " " || $c === "\n" || $c === "\r") {
					continue;
				} else {
					$this->unreadChar($c);
					break;
				}
			}
		}

		public function eof() {
			return feof($this->f);
		}

		public function close() {
			fclose($this->f);
		}
	}

	function getUOJConf($file_name) {
		$reader = new StrictFileReader($file_name);
		if ($reader->failed()) {
			return -1;
		}

		$conf = array();
		while (!$reader->eof()) {
			$reader->ignoreWhite();
			$key = $reader->readString();
			if ($key === '') {
				break;
			}
			$reader->ignoreWhite();
			$val = $reader->readString();
			if ($val === '') {
				break;
			}

			if (isset($conf[$key])) {
				return -2;
			}
			$conf[$key] = $val;
		}
		$reader->close();
		return $conf;
	}
	function putUOJConf($file_name, $conf) {
		$f = fopen($file_name, 'w');
		foreach ($conf as $key => $val) {
			fwrite($f, "$key $val\n");
		}
		fclose($f);
	}
	
	function getUOJConfVal($conf, $key, $default_val) {
		if (isset($conf[$key])) {
			return $conf[$key];
		} else {
			return $default_val;
		}
	}
	
	function getUOJProblemInputFileName($problem_conf, $num) {
		return getUOJConfVal($problem_conf, 'input_pre', 'input') . $num . '.' . getUOJConfVal($problem_conf, 'input_suf', 'txt');
	}
	function getUOJProblemOutputFileName($problem_conf, $num) {
		return getUOJConfVal($problem_conf, 'output_pre', 'output') . $num . '.' . getUOJConfVal($problem_conf, 'output_suf', 'txt');
	}
	function getUOJProblemExtraInputFileName($problem_conf, $num) {
		return 'ex_' . getUOJConfVal($problem_conf, 'input_pre', 'input') . $num . '.' . getUOJConfVal($problem_conf, 'input_suf', 'txt');
	}
	function getUOJProblemExtraOutputFileName($problem_conf, $num) {
		return 'ex_' . getUOJConfVal($problem_conf, 'output_pre', 'output') . $num . '.' . getUOJConfVal($problem_conf, 'output_suf', 'txt');
	}
	
	function rejudgeProblem($problem) {
		DB::query("update submissions set judge_time = NULL , result = '' , score = NULL , status = 'Waiting Rejudge' where problem_id = ${problem['id']}");
	}
	function rejudgeProblemAC($problem) {
		DB::query("update submissions set judge_time = NULL , result = '' , score = NULL , status = 'Waiting Rejudge' where problem_id = ${problem['id']} and score = 100");
	}
	function rejudgeProblemGe87($problem) {
		DB::query("update submissions set judge_time = NULL , result = '' , score = NULL , status = 'Waiting Rejudge' where problem_id = ${problem['id']} and score >= 87");
	}
	function rejudgeProblemGe90($problem) {
		DB::query("update submissions set judge_time = NULL , result = '' , score = NULL , status = 'Waiting Rejudge' where problem_id = ${problem['id']} and score >= 90");
	}
	function rejudgeSubmission($submission) {
		DB::query("update submissions set judge_time = NULL , result = '' , score = NULL , status = 'Waiting Rejudge' where id = ${submission['id']}");
	}
	function updateBestACSubmissions($username, $problem_id) {
		$best = DB::selectFirst("select id, used_time, used_memory, tot_size from submissions where submitter = '$username' and problem_id = $problem_id and score = 100 order by used_time, used_memory, tot_size asc limit 1");
		$shortest = DB::selectFirst("select id, used_time, used_memory, tot_size from submissions where submitter = '$username' and problem_id = $problem_id and score = 100 order by tot_size, used_time, used_memory asc limit 1");
		DB::delete("delete from best_ac_submissions where submitter = '$username' and problem_id = $problem_id");
		if ($best) {
			DB::insert("insert into best_ac_submissions (problem_id, submitter, submission_id, used_time, used_memory, tot_size, shortest_id, shortest_used_time, shortest_used_memory, shortest_tot_size) values ($problem_id, '$username', ${best['id']}, ${best['used_time']}, ${best['used_memory']}, ${best['tot_size']}, ${shortest['id']}, ${shortest['used_time']}, ${shortest['used_memory']}, ${shortest['tot_size']})");
		}

		$cnt = DB::selectCount("select count(*) from best_ac_submissions where submitter='$username'");
		DB::update("update user_info set ac_num = $cnt where username='$username'");
		
		DB::update("update problems set ac_num = (select count(*) from submissions where problem_id = problems.id and score = 100), submit_num = (select count(*) from submissions where problem_id = problems.id) where id = $problem_id");
	}

	function rescorePassSubmissions($problem_id) {
		$best = DB::selectFirst("select used_time from submissions where problem_id = $problem_id and score >= 90 or score is not NULL order by used_time asc limit 1");
		$result = DB::query("select id, used_time, submitter, score, contest_id from submissions where problem_id = $problem_id and score >= 90");
		while ($submission = DB::fetch($result, MYSQLI_ASSOC)) {
			if($submission['used_time'] > 2 * best['used_time']) {
				// 比赛时就不发私信了，万一是oi赛制就不好了
				if($submission['score'] == 100 && $submission['contest_id'] == null) {
					$content = '很遗憾，您的提交 ' . $submission['id'] . ' 的用时超过了最优解的两倍。最优解用时：' . $best['used_time'] . 'ms，您的用时：' . $submission['used_time'] . 'ms。';
					sendSystemMsg($submission['submitter'], '被卡常了QWQ', $content);
				}
				// TODO: 替换更好的计分方法
				$score = floor(100 - ($submission['used_time'] / (2.0 * best['used_time']) - 1) * 2);
				if($score < 90) $score = 90;
				DB::update("update submissions set score = $score where id = {$submission['id']}");
			} else {
				DB::update("update submissions set score = 100 where id = {$submission['id']}");
			}
		}
	}
?>
