<?php
$sPath = dirname(dirname(dirname(dirname(__FILE__))));

require_once($sPath.'/wp-config.php');
require_once(PP_THESAURUS_PLUGIN_DIR . '/classes/PPThesaurusManager.class.php');
require_once(PP_THESAURUS_PLUGIN_DIR . '/classes/PPThesaurusItem.class.php');

$sUrl			= pp_thesaurus_get_template_page();
$oPPTManager 	= PPThesaurusManager::getInstance();
$aConcepts		= $oPPTManager->searchConcepts($_GET['q'], 100, $sUrl);

echo join("\n", $aConcepts);
