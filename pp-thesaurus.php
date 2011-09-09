<?php
/*
Plugin Name: Climate change glossary
Plugin URI: http://poolparty.punkt.at
Description: This plugin imports a SKOS thesaurus via <a href="https://github.com/semsol/arc2">ARC2</a>. It highlighs terms and generates links automatically in any page which contains terms from the thesaurus.
Version: 1.2
Author: reegle.info
Author URI: http://www.reegle.info
*/

/*  
	Copyright 2011  Kurt Moser  (email: moser@punkt.at)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/




/* defines */

define('PP_THESAURUS_PLUGIN_DIR', dirname(__FILE__));
define('PP_THESAURUS_ENDPOINT', 'http://poolparty.reegle.info/PoolParty/SPARQLEndPoint/urn:uuid:1D8778C3-B838-0001-CC4A-151D10A4B0B0');
define('PP_THESAURUS_DBPEDIA_ENDPOINT', 'http://sparql.reegle.info');


/* includes */

include_once(PP_THESAURUS_PLUGIN_DIR . '/classes/PPThesaurusManager.class.php');
include_once(PP_THESAURUS_PLUGIN_DIR . '/classes/PPThesaurusItem.class.php');
include_once(PP_THESAURUS_PLUGIN_DIR . '/classes/PPThesaurusTemplate.class.php');

if (file_exists(PP_THESAURUS_PLUGIN_DIR . '/arc/ARC2.php')) {
	include_once(PP_THESAURUS_PLUGIN_DIR . '/arc/ARC2.php');

	if (class_exists('PPThesaurusTemplate')) {
		$oPPTManager 	= PPThesaurusManager::getInstance();
		$oPPTTemplate 	= new PPThesaurusTemplate();

		/* add options */
		add_option('PPThesaurusId', 				0);									// The ID of the main PoolParty Thesaurus page
		add_option('PPThesaurusPopup', 				1);									// Show the tooltip
		add_option('PPThesaurusLanguage', 			'en');								// The thesaurus language
		add_option('PPThesaurusImportFile', 		'');

		/* hooks */
		add_action('init',			'pp_thesaurus_init');
		add_action('admin_menu', 	'pp_thesaurus_settings_request');
		add_filter('the_content', 	array($oPPTManager, 'parse'), 11);
		add_filter('the_title', 	array($oPPTTemplate, 'setTitle'));

		/* register shortcode */
		add_shortcode('ccg-abcindex', 		array($oPPTTemplate, 'showABCIndex'));
		add_shortcode('ccg-itemdetails', 	array($oPPTTemplate, 'showItemDetails'));
		add_shortcode('ccg-itemlist', 		array($oPPTTemplate, 'showItemList'));
		add_shortcode('ccg-noparse', 		array($oPPTManager, 'cutContent'));
	}
} else {
	add_action('admin_menu', 'pp_thesaurus_settings_request');
}



/* load Javascript file into header */

function pp_thesaurus_init () {
	if (!is_admin()) {		// instruction to only load if it is not the admin area
		$iPopup = get_option('PPThesaurusPopup');
		if ($iPopup <= 1) {
			wp_enqueue_style('ppthesaurus_style', plugins_url('/css/style.css', __FILE__));
			if ($iPopup == 1) {
				wp_enqueue_script('jquery');
				wp_enqueue_script('unitip_script', plugins_url('/js/unitip.js', __FILE__), array('jquery'));
				wp_enqueue_style('unitip_style', plugins_url('/js/unitip/unitip.css', __FILE__));
			}
		}
	}
}


/* wp-admin */
$pp_thesaurus_updated = false;

function pp_thesaurus_settings_request () {
	add_options_page('PoolParty Thesaurus Settings', 'Climate change glossary', 10, __FILE__, 'pp_thesaurus_settings');
}

function pp_thesaurus_settings () {
	$sError = '';
	if (isset($_POST['secureToken']) && ($_POST['secureToken'] == pp_thesaurus_get_secure_token())) {
		$sError = pp_thesaurus_settings_save();
	}
	pp_thesaurus_settings_page($sError);
}

function pp_thesaurus_settings_page ($sError='') {
	global $pp_thesaurus_updated;

	if (!class_exists('ARC2')) {
	?>
	<div class="wrap">
		<h2><?php _e('Climate change glossary settings', 'pp-thesaurus'); ?></h2>
		<div id="message" class="error"><p><strong>Please install ARC2!</strong></p></div>
		<p>
		Download ARC2 from <a href="https://github.com/semsol/arc2" target="_blank">https://github.com/semsol/arc2</a> and unzip it.<br />
		Open the unziped folder and upload the entire contents into the "/wp-content/plugins/climate-change-glossary/arc/" directory.
		</p>
	</div>
	<?php
		exit;
	}

	$iPopup 	= get_option('PPThesaurusPopup');
	$sUpdated 	= get_option('PPThesaurusUpdated');
	switch ($iPopup) {
		case 0:
			$sPopup0 = 'checked="checked" ';
			break;
		case 1:
			$sPopup1 = 'checked="checked" ';
			break;
		case 2:
			$sPopup2 = 'checked="checked" ';
			break;
	}
	$sDate = empty($sUpdated) ? 'undefined' : date('d.m.Y', $sUpdated);
	$oPPTM = PPThesaurusManager::getInstance();

	?>
	<div class="wrap">
		<h2><?php _e('Climate change glossary settings', 'pp-thesaurus'); ?></h2>
	<?php
	if (!empty($sError)) {
		echo '<div id="message" class="error"><p><strong><?php echo $sError; ?></strong></p></div>';
	} elseif (isset($_POST['secureToken']) && empty($sError)) {
		echo '<div id="message" class="updated fade"><p>Settings saved.</p>';
		if ($pp_thesaurus_updated) {
			echo '<p>Please select the thesaurus languages.</p>';
		}
		echo '</div>';
	}
	?>
			<h3><?php _e('Common settings', 'pp-thesaurus'); ?></h3>
			<form method="post" action="">
			<table class="form-table">
				<tr valign="baseline">
					<th scope="row"><?php _e('Options for automatic linking of recognized terms', 'pp-thesaurus'); ?></th>
					<td>
						<input id="popup_1" type="radio" name="popup" value="1" <?php echo $sPopup1; ?>/>
						<label for="popup_1"><?php _e('link & show description in tooltip',  'pp-thesaurus'); ?></label><br />
						<input id="popup_0" type="radio" name="popup" value="0" <?php echo $sPopup0; ?>/>
						<label for="popup_0"><?php _e('link without tooltip',  'pp-thesaurus'); ?></label><br />
						<input id="popup_2" type="radio" name="popup" value="2" <?php echo $sPopup2; ?>/>
						<label for="popup_2"><?php _e('automatic linking disabled',  'pp-thesaurus'); ?></label>
					</td>
				</tr>
				<tr valign="baseline">
	<?php
	if ($oPPTM->existsTripleStore()) {
		echo '<th scope="row">' . __('Thesaurus language', 'pp-thesaurus') . '</th>';
	} else {
		echo '<th scope="row">' . __('Set the thesaurus language after the import', 'pp-thesaurus') . '</th>';
	}
	?>
					<td>
						<?php echo pp_thesaurus_get_language_form(); ?>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Save settings', 'pp-thesaurus') ?>" />
				<input type="hidden" name="secureToken" value="<?php echo pp_thesaurus_get_secure_token(); ?>" />
				<input type="hidden" name="from" value="common_settings" />
			</p>
			</form>


			<p>&nbsp;</p>
			<h3><?php _e('Data settings', 'pp-thesaurus'); ?></h3>
			<form method="post" action="" enctype="multipart/form-data">
			<table class="form-table">
				<tr valign="baseline">
				</tr>
				<tr valign="baseline">
					<th scope="row">
	<?php
	if ($oPPTM->existsTripleStore()) {
		echo "Last data update on <strong>$sDate</strong>";
	} else {
		echo '<span style="color:red;">Please click on "Import/Update Thesaurus".</span>';
	}
	?>
					</th>
				</tr>
				<tr valign="baseline">
					<th scope="row"><?php _e("Uploading the thesaurus can take a few minutes (4-5 minutes).<br />Please remain patient and don't interrupt the procedure.", 'pp-thesaurus'); ?></th>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e('Import/Update Thesaurus', 'pp-thesaurus') ?>" />
				<input type="hidden" name="secureToken" value="<?php echo pp_thesaurus_get_secure_token(); ?>" />
				<input type="hidden" name="from" value="data_settings" />
			</p>
			</form>
			<p>
				This plugin is provided by the <a href="http://poolparty.punkt.at/" target="_blank">PoolParty</a> Team.<br />
				PoolParty is an easy-to-use SKOS editor for the Semantic Web
			</p>
			<input type="hidden" name="secureToken" value="<?php echo pp_thesaurus_get_secure_token(); ?>" />
		</form>
	</div>
	<?php
}



function pp_thesaurus_get_language_form () {

	$oPPTM = PPThesaurusManager::getInstance();
	if (!$oPPTM->existsTripleStore()) {
		return '';
	}

	$aStoredLanguages = array();
	if ($sLang = get_option('PPThesaurusLanguage')) {
		$aStoredLanguages = split('#', $sLang);
	}

	$aThesLanguages = $oPPTM->getLanguages();
	$aSysLanguages  = array();
	if (function_exists('qtrans_getSortedLanguages')) {
		$aLang = qtrans_getSortedLanguages();
		foreach ($aLang as $sLang) {
			$aSysLanguages[$sLang] = qtrans_getLanguageName($sLang);
		}
	}

	$sContent = '';
	if (empty($aSysLanguages)) {
		$sFirstLang = $aStoredLanguages[0];
		foreach ($aThesLanguages as $sLang) {
			$sChecked = $sLang == $sFirstLang ? 'checked="checked" ' : '';
			$sContent .= '<input id="lang_' . $sLang . '" type="radio" name="languages[]" value="' . $sLang . '" ' . $sChecked . '/> ';
			$sContent .= '<label for="lang_' . $sLang . '">' . $sLang . '</label><br />';
		}
	} else {
		$aFlags = get_option('qtranslate_flags');
		$sFlagPath = get_option('qtranslate_flag_location');
		$sFlagPath = plugins_url() . substr($sFlagPath, strpos($sFlagPath, '/'));
		foreach ($aSysLanguages as $sLang => $sLangName) {
			if (in_array($sLang, $aThesLanguages)) {
				if (empty($aStoredLanguages)) {
					$sChecked = 'checked="checked" ';
				} else {
					$sChecked = in_array($sLang, $aStoredLanguages) ? 'checked="checked" ' : '';
				}
				$sContent .= '<input id="lang_' . $sLang . '" type="checkbox" name="languages[]" value="' . $sLang . '" ' . $sChecked . '/> ';
				$sContent .= '<label for="lang_' . $sLang . '">';
				$sContent .= '<img src="' . $sFlagPath . $aFlags[$sLang] . '" alt="Language: ' . $sLangName . '" /> ' . $sLangName . '</label><br />';
			} else {
				$sContent .= '<input id="lang_' . $sLang . '" type="checkbox" name="languages[]" value="' . $sLang . '" disabled="disabled" /> ';
				$sContent .= '<img src="' . $sFlagPath . $aFlags[$sLang] . '" alt="Language: ' . $sLangName . '" /> ' . $sLangName . ' (not available)<br />';
			}
		}
	}
	return $sContent;
}



function pp_thesaurus_settings_save () {
	$sError = '';
	switch ($_POST['from']) {
		case 'common_settings':
			update_option('PPThesaurusPopup', $_POST['popup']);
			update_option('PPThesaurusLanguage', join('#', $_POST['languages']));
			break;

		case 'data_settings':
			$sError = pp_thesaurus_import();
			break;
	}
	if (get_option('PPThesaurusId') == 0) {
		$iPageId = pp_thesaurus_add_pages();
		update_option('PPThesaurusId', $iPageId);
	}
	return $sError;
}

function pp_thesaurus_add_pages() {
	$aPageConf = array(
		'post_type'		=> 'page',
		'post_status'	=> 'publish',
		'post_title'	=> 'Glossary',
		'post_content'	=> "[ccg-abcindex]\n[ccg-itemlist]"
	);
	if (!($iPageId = wp_insert_post($aPageConf))) {
		return 0;
	}
	$aChildPageConf = array(
		'post_type'		=> 'page',
		'post_status'	=> 'publish',
		'post_title'	=> 'Item',
		'post_content'	=> "[ccg-abcindex]\n[ccg-itemdetails]",
		'post_parent'	=> $iPageId
	);
	if (!($iChildPageId = wp_insert_post($aChildPageConf))) {
		return 0;
	}

	return $iPageId;
}

function pp_thesaurus_import () {
	global $pp_thesaurus_updated;
	// Load RDF-Data into ARC store
	try {
		PPThesaurusManager::importFromEndpoint();
	} catch (Exception $e) {
		return $e->getMessage();
	}
	update_option('PPThesaurusUpdated', time());
	$pp_thesaurus_updated = true;

	return '';
}



function pp_thesaurus_get_secure_token () {
	return substr(md5(DB_USER . DB_NAME), -10);
}






/* wp-content */

function pp_thesaurus_to_link ($aItemList) {
	if (empty($aItemList)) {
		return array();
	}

	$oPPTM = PPThesaurusManager::getInstance();
	$aLinks = array();
	foreach ($aItemList as $oItem) {
		$sDefinition = $oPPTM->getDefinition($oItem->uri, $oItem->definition, true, true);
		$aLinks[] = pp_thesaurus_get_link( $oItem->prefLabel, $oItem->uri, $oItem->prefLabel, $sDefinition, true);
	}

	return $aLinks;
}

function pp_thesaurus_get_link ($sText, $sUri, $sPrefLabel, $sDefinition, $bShowLink=false) {
	if (empty($sDefinition)) {
		if ($bShowLink) {
			$sPage = pp_thesaurus_get_template_page();
			$sLink = '<a class="ppThesaurus" href="' . $sPage . '?uri=' . $sUri . '" title="Term: ' . $sPrefLabel . '">' . $sText . '</a>';
		} else {
			$sLink = $sText;
		}
	} else {
		$sPage = pp_thesaurus_get_template_page();
		$sLink = '<a class="ppThesaurus" href="' . $sPage . '?uri=' . $sUri . '" title="Term: ' . $sPrefLabel . '">' . $sText . '</a>';
		$sLink .= '<span style="display:none;">' . $sDefinition . '</span>';
	}

	return $sLink;
}


function pp_thesaurus_get_template_page () {
	$iPPThesaurusId = get_option('PPThesaurusId');

	$aChildren 	= get_children(array('numberposts'	=> 1,
									 'post_parent'	=> $iPPThesaurusId,
									 'post_type'	=> 'page'));
	$oChild = array_shift($aChildren);

	return get_page_link($iPPThesaurusId) . '/' . $oChild->post_name;
}

