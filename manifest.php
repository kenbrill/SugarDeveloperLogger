<?php
$manifest = array(
    'acceptable_sugar_flavors' => array('CE','PRO','CORP','ENT','ULT'),
    'acceptable_sugar_versions' => array(
        'exact_matches' => array(),
        'regex_matches' => array('(.*?)\.(.*?)\.(.*?)$'),
    ),
    'author' => 'Kenneth Brill',
    'description' => 'Sugar Developers Log',
    'icon' => '',
    'is_uninstallable' => true,
    'name' => 'SugarDeveloperLogger',
    'published_date' => '2018-01-28 20:28:20',
    'type' => 'module',
    'version' => '1.0'
);

$installdefs =array (
  'id' => 'CUSTOM1517192858',
  'copy' =>
  array (
    0 =>
    array (
      'from' => '<basepath>/files/custom/include/SugarLogger/SugarDeveloperLogger.php',
      'to' => 'custom/include/SugarLogger/SugarDeveloperLogger.php',
      'timestamp' => '2018-01-28 20:19:59',
    ),
    1 =>
    array (
      'from' => '<basepath>/files/custom/Extension/application/Ext/Utils/sugarLogger.php',
      'to' => 'custom/Extension/application/Ext/Utils/sugarLogger.php',
      'timestamp' => '2018-01-28 20:11:46',
    ),
    2 =>
    array (
      'from' => '<basepath>/files/custom/Extension/application/Ext/Utils/appgati.php',
      'to' => 'custom/Extension/application/Ext/Utils/appgati.php',
      'timestamp' => '2018-01-28 15:17:11',
    ),
  ),
  'pre_execute' =>
  array (
  ),
);
