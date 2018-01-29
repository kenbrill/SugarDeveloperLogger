# SugarDeveloperLogger
SugarDeveloperLogger - A Custom SugarCRM Logger

 This is what I added

    Each log entry will be tagged with the file name and line number of the $GLOBALS['log'] call.  Remember this is only for the important log levels, not all of them, so it's impact on your log is not that bad.

Sun Jan 28 09:54:21 2018 [70204][-none-][FATAL] THIS IS A TEST
-->/Applications/MAMP/htdocs/crm-workingCopy/index.php[37]
view raw
sugarcrm.log hosted with ❤ by GitHub
Each custom log level can be limited down to a predefined list of users so you dont have to see your test log entries for all users, just testers.
Timers, you can start, stop and report on an unlimited number of timers.  Just by invoking the start_timer command and naming the timer like this

$GLOBALS['log']->timer_start("timer1"); 

Headers, You can easily add very visible headers (Both single and multiline) to the log. Try to keep each line to less than 80 characters but it will scale it if you do not.  You can 'break' lines with <br> or '\n' characters.

$GLOBALS['log']->header("This is a test"); 

- or -

$GLOBALS['log']->header("This is a test<br>on multiple lines"); 

Sun Jan 28 15:30:59 2018 [83628][-none-][HEADER] 
********************************************************************************
******************************** This is a test ********************************
***************************** On multiple Comments *****************************
******************************* in the log file ********************************
********************************************************************************
-->/Applications/MAMP/htdocs/crm-workingCopy/index.php[28]
Sun Jan 28 15:30:59 2018 [83628][-none-][HEADER] 
********************************************************************************
******************************* All on one line ********************************
********************************************************************************
-->/Applications/MAMP/htdocs/crm-workingCopy/index.php[29]
view raw
sugarcrm.log hosted with ❤ by GitHub
Log Rolling, you can tell SugarCRM to roll the log at any time like this.  I use this to clear the log before a big test.  Its pretty easy to go overboard on this one, dont do it in a loop.

$GLOBALS['log']->rollLog();

Email, You can log a message via Email for all the really important stuff like this

$GLOBALS['log']->email("This is a test");

Notifications, you can also use SugarCRM's built in notification system like this

$GLOBALS['log']->notification("This is a test");

The code will Email you about any SQL Query failures, no extra log call needed.  It will also email you whenever a log level of 'error' or 'secruity' is used as you should never miss those.
