<?php
/*
Plugin Name: Climate change glossary
Plugin URI: http://poolparty.punkt.at
Description: This plugin imports a SKOS thesaurus via <a href="https://github.com/semsol/arc2">ARC2</a>. It highlighs terms and generates links automatically in any page which contains terms from the thesaurus.
Version: 1.0
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
define('PP_THESAURUS_DEFAULT_ENDPOINT', 'http://poolparty.reegle.info/PoolParty/SPARQLEndPoint/urn:uuid:1D8778C3-B838-0001-CC4A-151D10A4B0B0');


/* includes */

include_once(PP_THESAURUS_PLUGIN_DIR . '/classes/PPThesaurusManager.class.php');
include_once(PP_THESAURUS_PLUGIN_DIR . '/classes/PPThesaurusItem.class.php');
include_once(PP_THESAURUS_PLUGIN_DIR . '/classes/PPThesaurusTemplate.class.php');


if (class_exists('PPThesaurusTemplate')) {
	$oPPTManager 	= PPThesaurusManager::getInstance();
	$oPPTTemplate 	= new PPThesaurusTemplate();

	/* add options */
	add_option('PPThesaurusId', 				0);									// The ID of the main PoolParty Thesaurus page
	add_option('PPThesaurusSparqlEndpoint', 	PP_THESAURUS_DEFAULT_ENDPOINT);		// The SPARQL endpoint for the data import/update
	add_option('PPThesaurusPopup', true);											// Show the tooltip
	add_option('PPThesaurusLanguage', 'en');										// The thesaurus language

	/* hooks */
	add_action('init',			'pp_thesaurus_init');
	add_action('admin_menu', 	'pp_thesaurus_settings_request');
	add_filter('the_content', 	array($oPPTManager, 'parse'));
	add_filter('the_title', 	array($oPPTTemplate, 'setTitle'));

	/* register shortcode */
	add_shortcode('ccg-abcindex', 		array($oPPTTemplate, 'showABCIndex'));
	add_shortcode('ccg-itemdetails', 	array($oPPTTemplate, 'showItemDetails'));
	add_shortcode('ccg-itemlist', 		array($oPPTTemplate, 'showItemList'));
}



/* load Javascript file into header */

function pp_thesaurus_init () {
	if (!is_admin() && get_option('PPThesaurusPopup')) {	// instruction to only load if it is not the admin area
		wp_enqueue_script('jquery');
		wp_enqueue_script('unitip_script', plugins_url('/js/unitip.js', __FILE__), array('jquery'));
		wp_enqueue_style('unitip_style', plugins_url('/js/unitip/unitip.css', __FILE__));
		wp_enqueue_style('ppthesaurus_style', plugins_url('/css/style.css', __FILE__));
	}
}


/* wp-admin */

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
	?>
	<div class="wrap">
		<h2><?php _e('Climate change glossary settings', 'pp-thesaurus'); ?></h2>
		<form method="post" action="" enctype="multipart/form-data">
	<?php
	if (!empty($sError)) {
	?>
			<p style="color:red;"><strong><?php echo $sError; ?></strong></p>
	<?php
	} elseif (isset($_POST['secureToken']) && empty($sError)) {
	?>
			<p style="color:green;"><strong>Settings saved</strong></p>
	<?php
	}
	$bPopup 			= get_option('PPThesaurusPopup');
	$sLanguage 			= get_option('PPThesaurusLanguage');
	$sImportFile 		= get_option('PPThesaurusImportFile');
	$sSparqlEndpoint 	= get_option('PPThesaurusSparqlEndpoint');
	$sUpdated 			= get_option('PPThesaurusUpdated');
	if ($bPopup == true) {
		$sPopupTrue = 'checked="checked" ';
	} else {
		$sPopupFalse = 'checked="checked" ';
	}
	if ($sLanguage == 'en') {
		$sLangEN 	= 'checked="checked" ';
	} else {
		$sLangOther = 'checked="checked" ';
		$sOther		= $sLanguage;
	}
	$sFrom = empty($sImportFile) ? empty($sSparqlEndpoint) ? 'undefined' : 'Sparql endpoint' : $sImportFile;
	$sDate = empty($sUpdated) ? 'undefined' : date('d.m.Y', $sUpdated);
	if (empty($sSparqlEndpoint)) {
		$sSparqlEndpoint = PP_THESAURUS_DEFAULT_ENDPOINT;
	}
	?>
			<h3><?php _e('Common settings', 'pp-thesaurus'); ?></h3>
			<form method="post" action="">
			<table class="form-table">
				<tr valign="baseline">
					<th scope="row"><?php _e('Set the mouseover effect', 'pp-thesaurus'); ?></th>
					<td>
						<input id="popup_true" type="radio" name="popup" value="true" <?php echo $sPopupTrue; ?>/>
						<label for="popup_true"><?php _e('show the description in a tooltip',  'pp-thesaurus'); ?></label><br />
						<input id="popup_false" type="radio" name="popup" value="false" <?php echo $sPopupFalse; ?>/>
						<label for="popup_false"><?php _e('show only the title',  'pp-thesaurus'); ?></label>
					</td>
				</tr>
				<tr valign="baseline">
					<th scope="row"><?php _e('Set the Thesaurus language', 'pp-thesaurus'); ?></th>
					<td>
						<input type="radio" name="language" value="en" <?php echo $sLangEN; ?>/> en 
						<input type="radio" name="language" value="other" <?php echo $sLangOther; ?>/> <?php _e('other', 'pp-thesaurus'); ?>: 
						<input type="text" name="other" value="<?php echo $sOther; ?>" />
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
					<th scope="row" colspan="2"><?php _e('Import/Update SKOS Thesaurus from:', 'pp-thesaurus'); ?></th>
				</tr>
				<tr valign="baseline">
					<th scope="row"><?php _e('Sparql endpoint', 'pp-thesaurus'); ?></th>
					<td>
						URL: <input type="text" size="50" name="SparqlEndpoint" value="<?php echo $sSparqlEndpoint; ?>" />
					</td>
				</tr>
				<tr valign="baseline">
					<th scope="row"><?php _e('RDF/XML file', 'pp-thesaurus'); ?></th>
					<td><input type="file" size="50" name="rdfFile" value="" /></td>
				</tr>
				<tr valign="baseline">
					<th scope="row" colspan="2">
	<?php
	if (PPThesaurusManager::existsTripleStore()) {
		echo "Last data update on <strong>$sDate</strong> from <strong>$sFrom</strong>";
	} else {
		echo '<span style="color:red;">Please import a SKOS Thesaurus.</span>';
	}
	?>
					</th>
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


function pp_thesaurus_settings_save () {
	$sError = '';
	switch ($_POST['from']) {
		case 'common_settings':
			$sLanguage = $_POST['language'] == 'other' ? empty($_POST['other']) ? 'en' : $_POST['other'] : $_POST['language'];
			update_option('PPThesaurusPopup', $_POST['popup'] == 'true' ? true : false);
			update_option('PPThesaurusLanguage', $sLanguage);
			break;

		case 'data_settings':
			if (empty($_FILES['rdfFile']['name']) && empty($_POST['SparqlEndpoint'])) {
				$sError = 'Please indicate the SPARQL endpoint or the SKOS file to be imported.';
			} else {
				$sError = pp_thesaurus_import();
			}
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
	// Load RDF-Data into ARC store
	try {
		if (!empty($_FILES['rdfFile']['name'])) {
			PPThesaurusManager::importFromFile();
			update_option('PPThesaurusImportFile', $_FILES['rdfFile']['name']);
		} else {
			PPThesaurusManager::importFromEndpoint();
			update_option('PPThesaurusSparqlEndpoint', $_POST['SparqlEndpoint']);
			update_option('PPThesaurusImportFile', '');
		}
	} catch (Exception $e) {
		return $e->getMessage();
	}
	update_option('PPThesaurusUpdated', time());
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
		$sDefinition = $oPPTM->getDefinition($oItem->uri, $oItem->definition, true);
		$aLinks[] = pp_thesaurus_get_link( $oItem->prefLabel, $oItem->uri, $oItem->prefLabel, $sDefinition, true);
	}

	return $aLinks;
}

function pp_thesaurus_get_link ($sText, $sUri, $sPrefLabel, $sDefinition, $bShowLink=false) {
	if (empty($sDefinition)) {
		if ($bShowLink) {
			$sPage = getTemplatePage();
			$sLink = '<a class="ppThesaurus" href="' . $sPage . '?uri=' . $sUri . '" title="Term: ' . $sPrefLabel . '">' . $sText . '</a>';
		} else {
			$sLink = $sText;
		}
	} else {
		$sPage = getTemplatePage();
		$sLink = '<a class="ppThesaurus" href="' . $sPage . '?uri=' . $sUri . '" title="Term: ' . $sPrefLabel . '">' . $sText . '</a>';
		$sLink .= '<span style="display:none;">' . $sDefinition . '</span>';
	}

	return $sLink;
}


function getTemplatePage () {
	$iPPThesaurusId = get_option('PPThesaurusId');

	$aChildren 	= get_children(array('numberposts'	=> 1,
									 'post_parent'	=> $iPPThesaurusId,
									 'post_type'	=> 'page'));
	$oChild = array_shift($aChildren);

	return get_page_link($iPPThesaurusId) . '/' . $oChild->post_name;
}

