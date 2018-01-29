<?php
//SugarDeveloperLogger
if (!defined('sugarEntry') || !sugarEntry) {
	die('Not A Valid Entry Point');
}

/**
 * A developer centric SugarLogger override file.  It should be compatible with all versions
 * of SugarCRM and all OSs, however I have only tested it on 7.x and Linux.
 *
 * It enhances error tracing by adding the Class::Function to each message. It also allows for
 * Email, Notifications and Timers custom log levels.  The timers not ony provide the running
 * time, they also provide memory usage, system load and other debugging values.
 *
 * PHP version 5.6+
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom
 * the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING
 * BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @category   Logging
 * @package    SugarDeveloperLogger
 * @author     Kenneth Brill <ken.brill@gmail.com>
 * @copyright  2017-2018 Kenneth Brill
 * @license    https://opensource.org/licenses/MIT  MIT License
 * @version    1.0
 * @link       https://wallencreeksoftware.blogspot.com/
 */

require_once('include/SugarLogger/SugarLogger.php');
require_once('include/SugarPHPMailer.php');
require_once('custom/Extension/application/Ext/Utils/appgati.php');

/**
 * Custom SugarCRM Logger
 * @api
 */
class SugarDeveloperLogger extends SugarLogger
{
	private $appgati;
	private $line;
	private $file;
	private $notificationUsers;

	//This is the NAME that will be used in emails, NOT AN ADDRESS JUST A NAME
	private $emailFrom = 'SugarCRM Logging System';
	//The GUID of the team you want to send emails and notifications to
	protected $developerTeam = '0b2f3b7e-2b7f-11e7-8353-52d504b662cb';
	//When this is set to TRUE, the code will only log the custom log levels
	// like $GLOBALS['log']->email when the user is in the developers team (above)
	// if it is set to FALSE then it will log these for everyone.
	// No mater how it is set, log levels FATAL and below are logged for everyone
	// as per normal.
	protected $onlyLogDevelopers = true;

	public function __construct()
	{
		$this->appgati = new AppGati();
		parent::__construct();
	}

	/**
	 * see LoggerTemplate::log()
	 */
	public function log($level, $message)
	{
		if (!$this->initialized) {
			return;
		}

		//Fill in the team members
		if(empty($this->notificationUsers)) {
			$this->notificationUsers = $this->getUsersFromTeam();
		}
		//Get the log level - This allows for custom log levels
		$level = $this->getRealLevel($level);

		//lets get the current user id or default to -none- if it is not set yet
		$userID = (!empty($GLOBALS['current_user']->id)) ? $GLOBALS['current_user']->id : '-none-';

		//if we haven't opened a file pointer yet let's do that
		if (!$this->fp) {
			$this->fp = fopen($this->full_log_file, 'a');
		}

		// change to a string if there is just one entry
		if (is_array($message) && count($message) == 1) {
			$message = array_shift($message);
		}
		// change to a human-readable array output if it's any other array
		if (is_array($message) && !empty($message)) {
			$message = print_r($message, true);
		} else {
			$message = '';
		}

		//There are some things that do not need to be logged.
		$shouldBeLogged = true;

		//Perform some further processing on the message based
		// on its log level
		switch ($level) {
			case 'fatal':
				$shouldBeLogged = $this->fatalProcessing($message);
				break;
			case 'email':
				if($this->isTeamUser()) {
					$this->sendEmail('SugarCRM Developer Log', $message);
				} else {
					$shouldBeLogged = false;
				}
				break;
			case 'error':
			case 'security':
				$this->sendEmail('SugarCRM Developer Log', $message);
				$this->sendNotification('SugarCRM Developer Log', $message);
				break;
			case 'notification':
				if($this->isTeamUser()) {
					$this->sendNotification('SugarCRM Developer Log', $message);
				} else {
					$shouldBeLogged = false;
				}
				break;
			case 'header':
				if($this->isTeamUser()) {
					$message = $this->createHeader($message);
				} else {
					$shouldBeLogged = false;
				}
				break;
			case 'timer_start':
				if($this->isTeamUser()) {
					if (!empty($message)) {
						$timer_label = $message;
					} else {
						$timer_label = Sugarcrm\Sugarcrm\Util\Uuid::uuid1();
					}
					$_SESSION['last_timer'] = $timer_label;
					$this->appgati->Step($timer_label);
					$message = $this->createHeader("Timer ({$timer_label}) Started");
				} else {
					$shouldBeLogged = false;
				}
				break;
			case 'timer_report':
				if($this->isTeamUser()) {
					if (!empty($message)) {
						$prev_timer = $message;
					} else {
						$prev_timer = $_SESSION['last_timer'];
					}
					$timer_label = Sugarcrm\Sugarcrm\Util\Uuid::uuid1();
					$this->appgati->Step($timer_label);
					$header = $this->createHeader("Timer ({$prev_timer}) Report");
					$message = $header . print_r($this->appgati->Report($prev_timer, $timer_label), true);
				} else {
					$shouldBeLogged = false;
				}
				break;
			case 'timer_stop':
				if($this->isTeamUser()) {
					if (!empty($message)) {
						$timer_begin_label = $message;
						$timer_stop_label = $message . '_stop';
					} else {
						$timer_stop_label = Sugarcrm\Sugarcrm\Util\Uuid::uuid1();
						$timer_begin_label = $_SESSION['last_timer'];
					}
					$this->appgati->Step($timer_stop_label);
					$header = $this->createHeader("Timer ({$timer_begin_label}) Stopped");
					$message = $header . PHP_EOL . print_r($this->appgati->Report($timer_begin_label,
							$timer_stop_label), true);
				} else {
					$shouldBeLogged = false;
				}
				break;
			case 'rolllog':
				if($this->isTeamUser()) {
					$this->rollLog(true);
				}
				$shouldBeLogged = false;
				break;
			default:
				$shouldBeLogged = true;
				break;
		}

		//Add the file name and line number of the $GLOBALS['log'] call
		if (!empty($this->file)) {
			$message .= PHP_EOL . '-->' . $this->file . "[{$this->line}]";
		}

		//write out to the file including the time in the dateFormat the
		// process id , the user id , and the log level as well as the message
		if ($shouldBeLogged) {
			$this->write(strftime($this->dateFormat) . ' [' . getmypid() . '][' . $userID . '][' . strtoupper($level) . '] ' . $message . "\n");
		}
	}

	/**
	 * Some adhoc special cases for FATAL messages
	 * @param string $message
	 * @return bool
	 */
	private function fatalProcessing($message)
	{
		//This sends out any failed queries.  These are so important I force
		// it to send out both an EMail and set a Notification
		if (stristr($message, 'Query Failed') !== false) {
			$this->sendEMail('SugarCRM Error: Query Failed', $message);
			$this->sendNotification('SugarCRM Error: Query Failed', $message);
			return true;
		}

		//This sends out any FATAL message that begins with '^'
		// I call these 'Flagged Errors'
		if (substr($message, 0, 1) == "^") {
			$this->sendEMail('SugarCRM Flagged Fatal Error', $message);
			//If the flag has an asterisk after it then don't bother actually logging
			// it.  Its just for Email or Notifications
			if (substr($message, 0, 2) == "^*") {
				return false;
			} else {
				return true;
			}
		}
		return true;
	}

	/**
	 * Allows for custom log levels like
	 *   $GLOBALS['log']->email();
	 *
	 * Any log level that SugarCRM doesnt recognise gets remapped to the current log level
	 *   for example if you have your log level set to FATAL and you have this
	 *   $GLOBALS['log']->email('Whatever');
	 *   The SugarLogger will change 'email' to 'fatal' and send it through.  This function
	 *   rescues the original log level call so we can act on it.
	 *
	 * @param string $level
	 * @return string
	 */
	private function getRealLevel($level)
	{
		//Get as lightweight a backtrace as possible.  Only 4 call back and no args
		$x = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
		//If we look backwards 4 steps we have the call to the $GLOBALS['log']
		//   We just get the info from that section of the array
		if (isset($x[3]['function']) && !empty($x[3]['function'])) {
			$realLevel = $x[3]['function'];
		} else {
			$realLevel = $level;
		}
		//Get the file name and line number where the $GLOBALS['log'] call was made
		$this->file = (isset($x[3]['file'])) ? $x[3]['file'] : '';
		$this->line = (isset($x[3]['line'])) ? $x[3]['line'] : '';
		return strtolower($realLevel);
	}

	/**
	 * @param string $subject
	 * @param string $message
	 */
	private function sendEMail($subject, $message)
	{

		try {
			$emailObj = new Email();
			$emailBodyHtml = $this->addDebugBacktrace($message);
			$defaults = $emailObj->getSystemDefaultEmail();
			$mail = new SugarPHPMailer();
			$mail->setMailerForSystem();
			$mail->ContentType = "text/html";
			$mail->From = $defaults['email'];
			$mail->FromName = $this->emailFrom;

			$mail->Subject = $subject;
			$mail->Body = $emailBodyHtml;
			$mail->prepForOutbound();

			$users = $this->notificationUsers;
			if (!empty($users)) {
				foreach ($users as $userBean) {
					$emailAddress = $userBean->email1;
					if (!empty($emailAddress)) {
						if (!$this->isDuplicateEmail($subject, $message, $userBean)) {
							$mail->AddAddress($emailAddress);
						}
					} else {
						$GLOBALS['log']->fatal($userBean->name . "has no email address");
					}
				}
				@$mail->Send();
			} else {
				$GLOBALS['log']->fatal("THERE ARE NO MEMBERS FOR TEAM " . $this->developerTeam);
			}
		}
		catch (Exception $e) {
			$GLOBALS['log']->fatal($e->getMessage());
		}
	}

	/**
	 * do a lightweight duplicate email check.  At least it will not send out
	 * the same email over and over in the same session
	 * @param $subject
	 * @param $message
	 * @param $user
	 * @return bool
	 */
	private function isDuplicateEmail($subject, $message, $user)
	{
		if (!isset($_SESSION[$user->id][$message])) {
			$_SESSION[$user->id][$message] = 1;
			return false;
		} else {
			return true;
		}
	}

	/**
	 * @param string $subject
	 * @param string $message
	 */
	private function sendNotification($subject, $message)
	{
		$users = $this->getUsersFromTeam();
		if (!empty($users)) {
			//We dont want the same message going out over and over.
			if (!$this->isDuplicate($subject, $message, $users)) {
				$notificationBean = new Notifications();
				$notificationBean->name = $subject;
				$notificationBean->description = $this->addDebugBacktrace($message);
				$notificationBean->assigned_user_id = $users->id;
				$notificationBean->save();
			}
		}
	}

	/**
	 * @param string    $subject
	 * @param string    $message
	 * @param SugarBean $user
	 * @return bool
	 */
	private function isDuplicate($subject, $message, $user)
	{
		//Check for duplicates
		try {
			$query = new SugarQuery();
			$query->from(BeanFactory::getBean("Notifications"));
			$query->select(array('id'));
			$query->where()
				->equals('assigned_user_id', $user->id)
				->contains('description', $message)
				->equals('is_read', '0')
				->equals('name', $subject);
			$result = $query->getOne();
			if (!empty($result)) {
				return true;
			} else {
				return false;
			}
		}
		catch (Exception $e) {
			$GLOBALS['log']->fatal($e->getMessage());
			return false;
		}
	}

	/**
	 * @param String $message
	 * @return string
	 */
	private function addDebugBacktrace($message)
	{
		global $sugar_config;
		$debug_backtrace = debug_backtrace(null, 8);
		$message .= "<br><hr><b>SITE: " . $sugar_config['site_url'] . "</b>";
		$message .= "<br><hr>_REQUEST: <pre>" . print_r($_REQUEST, true) . "</pre>";
		$message .= "<br><hr>_SERVER: <pre>" . print_r($_SERVER, true) . "</pre>";
		$message .= "<br><hr><pre>" . print_r($debug_backtrace, true) . "</pre>";
		return $message;
	}

	/**
	 * @return mixed
	 */
	private function getUsersFromTeam()
	{
		$teamBean = BeanFactory::getBean('Teams', $this->developerTeam);
		$teamMembers = $teamBean->get_team_members(true);
		return $teamMembers;
	}

	private function createHeader($message)
	{
		$headerWidth = 80;
		if (nl2br($message) == $message && stristr($message, "<br>") === false) {
			//Make sure we are wide enough to accommodate $message
			if (strlen($message) + 4 > $headerWidth) {
				$headerWidth = strlen($message) + 6;
			}
			$header = PHP_EOL . str_repeat('*', $headerWidth) . PHP_EOL;
			$titleLength = strlen($message);
			$titleLine = str_repeat('*', intval(($headerWidth - ($titleLength + 2)) / 2));
			$titleLine .= " {$message} ";
			$titleLine .= str_repeat('*', intval(($headerWidth - ($titleLength + 2)) / 2));
			$titleLine .= str_repeat('*', $headerWidth - strlen($titleLine)) . PHP_EOL;
		} else {
			//Allow the user to use \n to break up a string as well
			$message = nl2br($message);
			$messageLines = explode("<br>", $message);
			//Find the longest string and make sure we are wide enough to accommodate it
			$maxLength = max(array_map('strlen', $messageLines));
			if ($maxLength + 4 > $headerWidth) {
				$headerWidth = $maxLength + 6;
			}
			$header = PHP_EOL . str_repeat('*', $headerWidth) . PHP_EOL;
			$titleLine = '';
			foreach ($messageLines as $messageLine) {
				$titleLength = strlen($messageLine);
				$multiLine = '';
				$multiLine .= str_repeat('*', intval(($headerWidth - ($titleLength + 2)) / 2));
				$multiLine .= " {$messageLine} ";
				$multiLine .= str_repeat('*', intval(($headerWidth - ($titleLength + 2)) / 2));
				$multiLine .= str_repeat('*', $headerWidth - strlen($multiLine));
				$titleLine .= $multiLine . PHP_EOL;
			}
		}
		$header .= $titleLine;
		$header .= str_repeat('*', $headerWidth);
		return $header;
	}

	/**
	 * @return bool
	 */
	private function isTeamUser() {
		foreach($this->notificationUsers as $user) {
			if($GLOBALS['current_user']->id == $user->id) {
				return true;
			}
		}
		if($this->onlyLogDevelopers == false) {
			return true;
		}
		return false;
	}
}
