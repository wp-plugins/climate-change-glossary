<?php

if (file_exists(PP_THESAURUS_PLUGIN_DIR . 'arc/ARC2.php')) {
	require_once(PP_THESAURUS_PLUGIN_DIR . 'arc/ARC2.php');
}


function getWpPrefix () {
	global $wpdb;
	return $wpdb->prefix;
}



class PPThesaurusARC2Store {

	const SKOS_CORE = 'http://www.w3.org/2004/02/skos/core#';

	protected static $oInstance;
	protected $oStore;
	protected $bExistsData;


	protected function __construct () {
		$this->oStore = ARC2::getStore(self::getStoreConfig());
		$this->bExistsData = null;
	}


	public static function getInstance () {
		if(!isset(self::$oInstance)){
			$sClass =  __CLASS__;
			self::$oInstance = new $sClass();
		}
		return self::$oInstance;
	}


	protected function getStoreConfig () {
		$aConfig = array(
			'db_host'		=> DB_HOST,
			'db_name'		=> DB_NAME,
			'db_user'		=> DB_USER,
			'db_pwd'		=> DB_PASSWORD,
			'store_name'	=> getWpPrefix() . 'pp_thesaurus',
		);

		return $aConfig;
	}


	public function getStore () {
		return $this->oStore;
	}


	public function setUp () {
		if (!$this->oStore->isSetUp()) {
			$this->oStore->setUp();
		}
	}


	public function drop () {
		if ($this->oStore->isSetUp()) {
			$this->oStore->drop();
		}
	}
	
	public function existsData () {
		if (is_null($this->bExistsData)) {
			$sQuery = "
				PREFIX skos: <" . self::SKOS_CORE . ">

				SELECT ?concept
				WHERE {
					?concept a skos:Concept .
				}
				LIMIT 1";
			$aRow = $this->oStore->query($sQuery, 'row');
			$this->bExistsData = count($aRow) ? true : false;
		}

		return $this->bExistsData;
	}


	public static function importFromFile () {
		$aUploadFile = $_FILES['importFile'];

		// Es wurde kein SKOS File zum Importieren angegeben
		if ($aUploadFile['error'] == 4) {
			return true;
		}

		// Downgeloadete File ueberpruefen
		if ($aUploadFile['error'] >= 1) {
			throw new Exception (__('An error has occured while downloading the file.', 'pp-thesaurus'));
		}
		if ($aUploadFile['type'] != 'application/rdf+xml') {
			throw new Exception (__('The specified file is not an RDF file.', 'pp-thesaurus'));
		}
		if (!is_uploaded_file($aUploadFile['tmp_name'])) {
			throw new Exception (__('An error has occured while downloading the file.', 'pp-thesaurus'));
		}

		// Das angegebene SKOS File in den ARC-Triplestore laden
		$oStore = ARC2::getStore(self::getStoreConfig());
		if (!$oStore->isSetUp()) {
			$oStore->setUp();
		}

		// All tables are emptied
		$oStore->reset();

		// Load RDF data into ARC store
		if (!($oStore->query('LOAD <file://' . $aUploadFile['tmp_name'] . '>'))) {
			throw new Exception (__('An error has occured while storing the RDF data to the database.', 'pp-thesaurus'));
		}
	}


	public static function importFromEndpoint () {

		// Get data from spaql endpoint
		$sThesaurusEndpoint = empty($_POST['thesaurusEndpoint']) ? PP_THESAURUS_ENDPOINT : $_POST['thesaurusEndpoint'];
		if (empty($sThesaurusEndpoint)) {
			throw new Exception (__('No SPARQL endpoint has been indicated.', 'pp-thesaurus'));
		}

		$aConfig = array(
			'remote_store_endpoint'	=> $sThesaurusEndpoint,
			'remote_store_timeout'	=> 2
		);
		$oEPStore = ARC2::getRemoteStore($aConfig);

		// Save data into ARC store
		$oARCStore = ARC2::getStore(self::getStoreConfig());
		if (!$oARCStore->isSetUp()) {
			$oARCStore->setUp();
		}

		// All tables are emptied
		$oARCStore->reset();

		self::importFromEndpointLoop($oEPStore, $oARCStore);
	}


	protected static function importFromEndpointLoop (&$oEPStore, &$oARCStore, $iCounter=0) {
		$iLimit = 1000;
		$iOffset = $iCounter * $iLimit;
		$sQuery = "
			CONSTRUCT {	?s ?p ?o }
			WHERE {?s ?p ?o }
			LIMIT $iLimit
			OFFSET $iOffset";

		$aData = $oEPStore->query($sQuery, 'raw');
		if ($aError = $oEPStore->getErrors()) {
			throw new Exception (__('The transfer of data from the SPARQL endpoint is not possible.', 'pp-thesaurus'));
		}

		// Insert data
		if (!empty($aData)) {
			foreach ($aData as &$aConcept) {
				if (isset($aConcept['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
					foreach ($aConcept['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as &$aType) {
						$aType['value'] = str_replace(array('(', ')', ',', ';'), array('%28', '%29', '%2C', '%3B'), $aType['value']);
						$iPos = strrpos($aType['value'], '/');
						$sName = str_replace(array('.', ':'), array('%2E', '%3A'), substr($aType['value'], $iPos));
						$aType['value'] = substr($aType['value'], 0, $iPos) . $sName;
					}
				}
			}
			$oARCStore->insert($aData, '');
			if ($aError = $oARCStore->getErrors()) {
				throw new Exception (__('An error has occured while storing the data from the SPARQL endpoint to the database.', 'pp-thesaurus'));
			}
			self::importFromEndpointLoop($oEPStore, $oARCStore, ++$iCounter);
		}
	}
}
