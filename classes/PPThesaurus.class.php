<?php

class PPThesaurus {

	const VERSION = 1.0;

	public $aWPOptions;


	public function __construct () {
		$this->upgradeOptions();
		$this->aWPOptions = get_option('PPThesaurus');
		$this->initHooks();
	}


	public function initHooks () {
		add_action('init',          array($this, 'initTextdomain'));
		add_action('admin_menu',    array($this, 'initMenu'));
		if ($this->existARC2Class()) {
			$oPPTM = PPThesaurusManager::getInstance();
			$oPPTT = PPThesaurusTemplate::getInstance();
			add_action('init',          array($this, 'init'));
			add_action('widgets_init',	create_function('', 'return register_widget("PPThesaurusWidget");'));
			add_action('save_post',		array(PPThesaurusCache, 'deletePost'));
			add_filter('the_content',   array($oPPTM, 'parse'), 100);
			add_filter('the_title',     array($oPPTT, 'setTitle'));
			add_filter('wp_title',      array($oPPTT, 'setWPTitle'), 10, 3);

			/* register shortcode */
			add_shortcode(PP_THESAURUS_SHORTCODE_PREFIX . '-abcindex',       array($oPPTT, 'showABCIndex'));
			add_shortcode(PP_THESAURUS_SHORTCODE_PREFIX . '-itemlist',       array($oPPTT, 'showItemList'));
			add_shortcode(PP_THESAURUS_SHORTCODE_PREFIX . '-itemdetails',    array($oPPTT, 'showItemDetails'));
			add_shortcode(PP_THESAURUS_SHORTCODE_PREFIX . '-noparse',        array($oPPTM, 'cutContent'));
		}
	}




	// -----------------------------------------------
	// Hooks and Shortcodes
	// -----------------------------------------------

	public function init () {
		if (!is_admin()) {      // instruction to only load if it is not the admin area
			wp_enqueue_script('jquery');
			wp_enqueue_script('ppt_autocomplete_script', plugins_url('/js/jquery.autocomplete.min.js', PP_THESAURUS_PLUGIN_FILE), array('jquery'));
			wp_enqueue_script('ppt_common_script', plugins_url('/js/script.js', PP_THESAURUS_PLUGIN_FILE), array('ppt_autocomplete_script'));
			wp_enqueue_style('ppt_autocomplete_style', plugins_url('/css/jquery.autocomplete.css', PP_THESAURUS_PLUGIN_FILE));
			wp_enqueue_style('ppt_style', plugins_url('/css/style.css', PP_THESAURUS_PLUGIN_FILE));
			if ($this->aWPOptions['linking'] == 'tooltip') {
				wp_enqueue_script('unitip_script', plugins_url('/js/unitip.js', PP_THESAURUS_PLUGIN_FILE), array('jquery'));
				wp_enqueue_style('unitip_style', plugins_url('/js/unitip/unitip.css', PP_THESAURUS_PLUGIN_FILE));
			}
		}
	}


	public function initTextdomain () {
		load_plugin_textdomain('pp-thesaurus', false, PP_THESAURUS_PLUGIN_DIR_REL . 'languages');
	}


	public function initMenu () {
		add_options_page(PP_THESAURUS_PLUGIN_NAME, PP_THESAURUS_PLUGIN_NAME, 'manage_options', 'pp-thesaurus-menu', array($this, 'initSettings'));
	}


	public function initSettings () {
		if (!$this->existARC2Class()) {
			echo '
			<div class="wrap">
				<div class="icon32" id="icon-options-general"></div>
				<h2>' . PP_THESAURUS_PLUGIN_NAME . ' ' . __('Settings', 'pp-thesaurus') . '</h2>
				' . $this->showMessage(__('Please install ARC2 first before you can change the settings!', 'pp-thesaurus'), 'error') . '
				<p>' . __('Download ARC2 from https://github.com/semsol/arc2 and unzip it. Open the unziped folder and upload the entire contents into the \'/wp-content/plugins/poolparty-thesaurus/arc/\' directory.', 'pp-thesaurus') . '</p>
			</div>
			';
			exit();
		}

		$this->saveSettings();
		echo '
			<div class="wrap">
				<div class="icon32" id="icon-options-general"></div>
				<h2>' . PP_THESAURUS_PLUGIN_NAME . ' ' . __('Settings', 'pp-thesaurus') . '</h2>
				<h3>' . __('Common settings', 'pp-thesaurus') . '</h3>
				<form method="post" action="">
		';
		wp_nonce_field('pp-thesaurus', 'pp-thesaurus-nonce');

		$oPPStore 			= PPThesaurusARC2Store::getInstance();
		$sLinking_tooltip 	= '';
		$sLinking_only_link	= '';
		$sLinking_disabled	= '';
		$sLinking 			= $this->aWPOptions['linking'];
		$sVariable 			= 'sLinking_' . $sLinking;
		$$sVariable 		= 'checked="checked" ';
		$sBlacklist			= $this->aWPOptions['termsBlacklist'];

		$sImportFile 		= $this->aWPOptions['importFile'];
		$sThesaurusEndpoint	= $this->aWPOptions['thesaurusEndpoint'];
		$sDBPediaEndpoint 	= $this->aWPOptions['dbpediaEndpoint'];
		$sUpdated 			= $this->aWPOptions['updated'];
		$sDate = empty($sUpdated) ? 'undefined' : date('d.m.Y', $sUpdated);
		$sFrom = empty($sImportFile) ? empty($sThesaurusEndpoint) ? 'undefined' : 'Thesaurus endpoint' : $sImportFile;

		echo '
				<table class="form-table">
					<tr valign="baseline">
						<th scope="row">' . __('Options for automated linking of recognized terms', 'pp-thesaurus') . '</th>
						<td>
							<input id="linking_tooltip" type="radio" name="linking" value="tooltip" ' . $sLinking_tooltip . '/>
							<label for="linking_tooltip">' . __('link and show description in tooltip',  'pp-thesaurus') . '</label><br />
							<input id="linking_only_link" type="radio" name="linking" value="only_link" ' . $sLinking_only_link . '/>
							<label for="linking_only_link">' . __('link without tooltip',  'pp-thesaurus') . '</label><br />
							<input id="linking_disabled" type="radio" name="linking" value="disabled" ' . $sLinking_disabled . '/>
							<label for="linking_disabled">' . __('automated linking disabled',  'pp-thesaurus') . '</label>
						</td>
					</tr>
					<tr valign="baseline">
						<th scope="row">' . __('Terms excluded from automated linking', 'pp-thesaurus') . '</th>
						<td>
							<input type="text" class="regular-text" name="termsBlacklist" value="' . $sBlacklist . '"  />
							<span class="description">(' . __('comma separated values', 'pp-thesaurus') . ')</span>
						<td>
					</tr>
					<tr valign="baseline">
						<th scope="row">' . __('Thesaurus languages', 'pp-thesaurus') . '</th>
		';
		if ($oPPStore->existsData()) {
			echo '		<td>' . $this->languageSettings() . '</td>';
		} else {
			echo '		<th scope="row" style="color:red;">' . __('Set the thesaurus languages after the import', 'pp-thesaurus') . '</th>';
		}
		echo '		</tr>';
		if (PP_THESAURUS_DBPEDIA_ENDPOINT_SHOW) {
			echo '
					<tr valign="baseline">
						<th scope="row">' . __('DBPedia SPARQL endpoint', 'pp-thesaurus') . '</th>
						<td>
							URL: <input type="text" size="50" name="dbpediaEndpoint" value="' . $sDBPediaEndpoint . '" />
						</td>
					</tr>
			';
		}
		echo '
				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="' . __('Save settings', 'pp-thesaurus') . '" />
					<input type="hidden" name="from" value="common_settings" />
				</p>
			</form>

			<p>&nbsp;</p>
			<h3>' . __('Data settings', 'pp-thesaurus') . '</h3>
			<form method="post" action="" enctype="multipart/form-data">
		';
		wp_nonce_field('pp-thesaurus', 'pp-thesaurus-nonce');
		echo '
				<table class="form-table">
		';
		if (PP_THESAURUS_ENDPOINT_SHOW || PP_THESAURUS_IMPORT_FILE_SHOW) {
			echo '
					<tr valign="baseline">
						<th scope="row" colspan="2"><strong>' . __('Import/Update SKOS Thesaurus from', 'pp-thesaurus') . '</strong>:</th>
					</tr>
			';
		}
		if (PP_THESAURUS_ENDPOINT_SHOW) {
			echo '
					<tr valign="baseline">
						<th scope="row">' . __('Thesaurus endpoint', 'pp-thesaurus') . '</th>
						<td>
							URL: <input type="text" size="50" name="thesaurusEndpoint" value="' . $sThesaurusEndpoint . '" />
						</td>
					</tr>
			';
		}
		if (PP_THESAURUS_IMPORT_FILE_SHOW) {
			echo '
					<tr valign="baseline">
						<th scope="row">' . __('RDF/XML file', 'pp-thesaurus') . ' (max. ' . ini_get('post_max_size') . 'B)</th>
						<td><input type="file" size="50" name="importFile" value="" /></td>
					</tr>
			';
		}
		echo '
					<tr valign="baseline">
						<th scope="row" colspan="2">
		';
		if (PP_THESAURUS_ENDPOINT_SHOW && PP_THESAURUS_IMPORT_FILE_SHOW) {
			if ($oPPStore->existsData()) {
				printf(__('Last data update on %1$s from %2$s', 'pp-thesaurus'), "<strong>$sDate</strong>", "<strong>$sFrom</strong>");
			} else {
				echo '<span style="color:red;">' . __('Please import a SKOS Thesaurus', 'pp-thesaurus') . '.</span>';
			}
		} else {
			if ($oPPStore->existsData()) {
				printf(__('Last data update on %1$s', 'pp-thesaurus'), "<strong>$sDate</strong>");
			} else {
				echo '<span style="color:red;">' . __('Please click on "Import/Update Thesaurus"', 'pp-thesaurus') . '.</span>';
			}
		}
		echo '
						</th>
					</tr>
					<tr valign="baseline">
						<th scope="row" colspan="2">' . __("Uploading the thesaurus can take a few minutes (4-5 minutes).<br />Please remain patient and don't interrupt the procedure.", 'pp-thesaurus') . '</th>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="' . __('Import/Update Thesaurus', 'pp-thesaurus') . '" />
					<input type="hidden" name="from" value="data_settings" />
				</p>
			</form>
			<p>
				This plugin is provided by the <a href="http://poolparty.biz/" target="_blank">PoolParty</a> Team.<br />
				PoolParty is an easy-to-use SKOS editor for the Semantic Web
			</p>
		</div>
		';
	}


	protected function languageSettings () {
		$aStoredLanguages = array();
		if ($sLang = $this->aWPOptions['languages']) {
			$aStoredLanguages = split('#', $sLang);
		}

		$oPPTM = PPThesaurusManager::getInstance();
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
					$sChecked = in_array($sLang, $aStoredLanguages) ? 'checked="checked" ' : '';
					$sContent .= '<input id="lang_' . $sLang . '" type="checkbox" name="languages[]" value="' . $sLang . '" ' . $sChecked . '/> ';
					$sContent .= '<label for="lang_' . $sLang . '">';
					$sContent .= '<img src="' . $sFlagPath . $aFlags[$sLang] . '" alt="Language: ' . $sLangName . '" /> ' . $sLangName . '</label><br />';
				} else {
					$sContent .= '<input id="lang_' . $sLang . '" type="checkbox" name="languages[]" value="' . $sLang . '" disabled="disabled" /> ';
					$sContent .= '<img src="' . $sFlagPath . $aFlags[$sLang] . '" alt="Language: ' . $sLangName . '" /> ';
					$sContent .= $sLangName . ' (' . __('not available', 'pp-thesaurus') . ')<br />';
				}
			}
		}
		return $sContent;
	}


	protected function saveSettings () {
		if (isset($_POST['from']) && isset($_POST['pp-thesaurus-nonce']) && wp_verify_nonce($_POST['pp-thesaurus-nonce'], 'pp-thesaurus')) {
			$bError = false;
			switch ($_POST['from']) {
				case 'common_settings':
					$this->aWPOptions['linking'] = $_POST['linking'];
					if (!preg_match('/^[-\w,_ ]*$/', $_POST['termsBlacklist'])) {
						echo $this->showMessage(__('Invalid characters in the comma separated list.', 'pp-thesaurus'), 'error');
						$bError = true;
					}
					$this->aWPOptions['termsBlacklist'] = esc_html(trim($_POST['termsBlacklist']));
					$this->aWPOptions['languages'] = (!isset($_POST['languages']) || empty($_POST['languages'])) ? '' : join('#', $_POST['languages']);
					if (isset($_POST['dbpediaEndpoint'])) {
						$this->aWPOptions['dbpediaEndpoint'] = trim($_POST['dbpediaEndpoint']);
					}
					break;

				case 'data_settings':
					if (PP_THESAURUS_ENDPOINT_SHOW && PP_THESAURUS_IMPORT_FILE_SHOW && 
							empty($_POST['thesaurusEndpoint']) && empty($_FILES['importFile']['name'])) {
						echo $this->showMessage(__('Please indicate the SPARQL endpoint or the SKOS file to be imported.', 'pp-thesaurus'), 'error');
						$bError = true;
					} elseif (PP_THESAURUS_ENDPOINT_SHOW && !PP_THESAURUS_IMPORT_FILE_SHOW && empty($_POST['thesaurusEndpoint'])) {
						echo $this->showMessage(__('Please indicate the SPARQL endpoint.', 'pp-thesaurus'), 'error');
						$bError = true;
					} elseif (!PP_THESAURUS_ENDPOINT_SHOW && PP_THESAURUS_IMPORT_FILE_SHOW && empty($_FILES['importFile']['name'])) {
						echo $this->showMessage(__('Please indicate the SKOS file to be imported.', 'pp-thesaurus'), 'error');
						$bError = true;
					}
					if (!$bError) {
						try {
							if (!empty($_FILES['importFile']['name'])) {
								PPThesaurusARC2Store::importFromFile();
								$this->aWPOptions['importFile']			= $_FILES['importFile']['name'];
							} else {
								PPThesaurusARC2Store::importFromEndpoint();
								$this->aWPOptions['languages']			= $this->setDefaultLanguages();
								$this->aWPOptions['thesaurusEndpoint'] 	= $_POST['thesaurusEndpoint'];
								$this->aWPOptions['importFile']			= '';
							}
						} catch (Exception $e) {
							echo $this->showMessage($e->getMessage(), 'error');
							$bError = true;
						}
					}
					$this->aWPOptions['updated'] = time();
					break;
			}
			if (!$bError) {
				PPThesaurusCache::deleteALL();
				update_option('PPThesaurus', $this->aWPOptions);
				echo $this->showMessage(__('Settings saved.', 'pp-thesaurus'), 'updated fade');
			}
		}
	}


	protected function setDefaultLanguages () {
		// Set the default languages only if a new SPAQL endpoint is given
		if ($this->aWPOptions['thesaurusEndpoint'] == $_POST['thesaurusEndpoint']) {
			return $this->aWPOptions['languages'];
		}

		$oPPStore	= PPThesaurusARC2Store::getInstance();
		$oPPTM 		= PPThesaurusManager::getInstance();
		if (!$oPPStore->existsData()) {
			// No thesaurus data is given
			return $this->aWPOptions['languages'];
		}

		$aThesLanguages	= $oPPTM->getLanguages();
		if (!function_exists('qtrans_getSortedLanguages')) {
			return $aThesLanguages[0];
		}

		$aLanguages = array();
		$aSysLanguages = qtrans_getSortedLanguages();
		foreach ($aSysLanguages as $sLang) {
			if (in_array($sLang, $aThesLanguages)) {
				$aLanguages[] = $sLang;
			}
		}
		sort($aLanguages, SORT_STRING);
		return join('#', $aLanguages);
	}





	// -----------------------------------------------
	// Install methodes
	// -----------------------------------------------

	/**
	 *  Activation hook: installs the plugin
	 */
	public function install () {
		// install ARC2
		if (!$this->existARC2Class()) {
			$this->installARC2();
		}
		$oPPStore = PPThesaurusARC2Store::getInstance();
		$oPPStore->setUp();
		unset($oPPStore);

		// add glossary pages
		$iPageId = $this->addGlossaryPages();

		// save default options
		$aOptions = array(
			'version'			=> self::VERSION,					// options version
			'pageId'			=> $iPageId,						// The ID of the main glossary page
			'linking'			=> 'tooltip',						// Options for automated linking [tooltip (1)|only_link (0)|disabled (2)]
			'languages'			=> 'en',							// Enabled languages for the thesaurus
			'termsBlacklist'	=> '',								// Terms excluded from automated linking
			'dbpediaEndpoint'	=> PP_THESAURUS_DBPEDIA_ENDPOINT,	// The DBPedia SPARQL endpoint
			'thesaurusEndpoint'	=> PP_THESAURUS_ENDPOINT,			// The thesaurus SPARQL endpoint for import/update it
			'importFile'		=> '',								// The file name from the with the thesaurus data
			'updated'			=> ''								// The last import/update date
		);
		update_option('PPThesaurus', $aOptions);
	}


	protected function addGlossaryPages () {
		// page with the list of concepts
		$aPageConf = array(
			'post_type'     => 'page',
			'post_status'   => 'publish',
			'post_title'    => 'Glossary',
			'post_content'  => "[" . PP_THESAURUS_SHORTCODE_PREFIX . "-abcindex]\n[" . PP_THESAURUS_SHORTCODE_PREFIX . "-itemlist]",
			'post_parent'   => 0
		);
		if (!($iPageId = wp_insert_post($aPageConf))) {
			die(__('The glossary pages cannot be created.', 'pp-thesaurus'));
		}
		// page with the details of a concept
		$aChildPageConf = array(
			'post_type'     => 'page',
			'post_status'   => 'publish',
			'post_title'    => 'Item',
			'post_content'  => "[" . PP_THESAURUS_SHORTCODE_PREFIX . "-abcindex]\n[" . PP_THESAURUS_SHORTCODE_PREFIX . "-itemdetails]",
			'post_parent'   => $iPageId
		);
		if (!($iChildPageId = wp_insert_post($aChildPageConf))) {
			die(__('The glossary pages cannot be created.', 'pp-thesaurus'));

		}

		return $iPageId;
	}


	protected function installARC2 () {
		if (!is_writable(PP_THESAURUS_PLUGIN_DIR)) {
			die(__('The plugin folder is not writable. Please install ARC2 manually.', 'pp-thesaurus'));
			return false;
		}

		$sDir = getcwd();
		chdir(PP_THESAURUS_PLUGIN_DIR);

		// download ARC2
		$sTarFileName   = 'arc.tar.gz';
		$sCmd           = 'wget --no-check-certificate -T 2 -t 1 -O ' . $sTarFileName . ' ' . PP_THESAURUS_ARC_URL . ' 2>&1';
		$aOutput        = array();
		exec($sCmd, $aOutput, $iResult);
		if ($iResult != 0) {
			chdir($sDir);
			die(__('ARC2 cannot be installed. Please install it manually.', 'pp-thesaurus'));
			return false;
		}

		// untar the file
		$sCmd       = 'tar -xvzf ' . $sTarFileName . ' 2>&1';
		$aOutput    = array();
		exec($sCmd, $aOutput, $iResult);
		if ($iResult != 0) {
			chdir($sDir);
			die(__('ARC2 cannot be installed. Please install it manually.', 'pp-thesaurus'));
			return false;
		}

		// delete old arc direcotry and tar file
		@rmdir('arc');
		@unlink($sTarFileName);

		// rename the ARC2 folder to arc
		$sCmd       = 'mv semsol-arc2-* arc 2>&1';
		$aOutput    = array();
		if ($iResult != 0) {
			chdir($sDir);
			die(__('ARC2 cannot be installed. Please install it manually.', 'pp-thesaurus'));
			return false;
		}

		chdir($sDir);

		// create ARC2 database tables
		$oPPStore = PPThesaurusARC2Store::getInstance();
		$oPPStore->setUp();
		unset($oPPStore);

		return true;
	}





	// -----------------------------------------------
	// Deinstall methodes
	// -----------------------------------------------

	/**
	 *  Deactivation hook: deinstalls the plugin
	 */
	public function deinstall () {
		// delete glossary pages
		$this->deleteGlossaryPages();

		// delete options
		delete_option('PPThesaurus');

		// deinstall ARC2
		$this->deinstallARC2();

		// delete cache
		PPThesaurusCache::deleteALL();
	}


	protected function deleteGlossaryPages () {
		$aOptions 	= get_option('PPThesaurus');
		$iPageId 	= $aOptions['pageId'];

		// get child page
		$aChildren  = get_children(array('numberposts'  => 1,
										 'post_parent'  => $iPageId,
										 'post_type'    => 'page'));
		$oChildPage = array_shift($aChildren);
		$iChildPageId = $oChildPage->ID;

		// delete pages
		wp_delete_post($iPageId, true);
		wp_delete_post($iChildPageId, true);
	}


	protected function deinstallARC2 () {
		// create ARC2 database tables
		$oPPStore = PPThesaurusARC2Store::getInstance();
		$oPPStore->drop();
		unset($oPPStore);

		if (!is_writable(PP_THESAURUS_PLUGIN_DIR)) {
			return false;
		}

		$sDir = getcwd();
		chdir(PP_THESAURUS_PLUGIN_DIR);

		// delete arc direcotry
		@rmdir('arc');

		chdir($sDir);
		return true;
	}




	// -----------------------------------------------
	// Upgrade methode
	// -----------------------------------------------

	/**
	 * Upgrades the old options to the new options
	 */
	protected function upgradeOptions () {
		if (get_option('PPThesaurusId') === false) {
			return true;
		}

		// tranfer the values from the old options into the new options
		$iPopup = get_option('PPThesaurusPopup');
		$sLinking = ($iPopup == 0) ? 'only_link' : (($iPopup == 1) ? 'tooltip' : 'disabled');
		$aOptions = array(
			'version'			=> self::VERSION,								// options version
			'pageId'			=> get_option('PPThesaurusId'),					// The ID of the main glossary page
			'linking'			=> $sLinking,									// Options for automated linking [tooltip|only_link|disabled]
			'termsBlacklist'	=> '',											// Terms excluded from automated linking
			'languages'			=> get_option('PPThesaurusLanguage'),			// Enabled languages for the thesaurus
			'dbpediaEndpoint'	=> get_option('PPThesaurusDBPediaEndpoint'),	// The DBPedia SPARQL endpoint
			'thesaurusEndpoint'	=> get_option('PPThesaurusSparqlEndpoint'),		// The thesaurus SPARQL endpoint for import/update it
			'importFile'		=> get_option('PPThesaurusImportFile'),			// The file name from the with the thesaurus data
			'updated'			=> get_option('PPThesaurusUpdated')				// The last import/update date
		);
		update_option('PPThesaurus', $aOptions);

		/* delete old options */
		delete_option('PPThesaurusId');
		delete_option('PPThesaurusPopup');
		delete_option('PPThesaurusLanguage');
		delete_option('PPThesaurusDBPediaEndpoint');
		delete_option('PPThesaurusSparqlEndpoint');
		delete_option('PPThesaurusImportFile');
		delete_option('PPThesaurusUpdated');
		delete_option('PPThesaurusSidebarTitle');
		delete_option('PPThesaurusSidebarInfo');
		delete_option('PPThesaurusSidebarWidth');
	}



	// -----------------------------------------------
	// Helper methods
	// -----------------------------------------------

	protected function existARC2Class () {
		if (class_exists('ARC2') || file_exists(PP_THESAURUS_PLUGIN_DIR . 'arc/ARC2.php')) {
			return true;
		}
		return false;
	}


	protected function showMessage ($sMessage, $sClass='info') {
		return '
			<div id="message" class="' . $sClass  . '">
				<p><strong>' .  $sMessage . '</strong></p>
			</div>
		';
	}
}
