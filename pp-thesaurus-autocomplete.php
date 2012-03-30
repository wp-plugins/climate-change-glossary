<?php

$sPath = dirname(dirname(dirname(dirname(__FILE__))));
if (!defined('ABSPATH')) {
	define('ABSPATH', $sPath . '/');
}

require_once($sPath.'/wp-config.php');
require_once('./classes/PPThesaurusManager.class.php');
require_once('./classes/PPThesaurusItem.class.php');

$sUrl			= pp_thesaurus_get_template_page();
$oPPTManager 	= PPThesaurusManager::getInstance();
$aConcepts		= $oPPTManager->searchConcepts($_GET['q'], 100, $sUrl);

echo join("\n", $aConcepts);
