<?php

include_once(PP_THESAURUS_PLUGIN_DIR . '/arc/ARC2.php');

function getWpPrefix () {
	global $wpdb;
	return $wpdb->prefix;
}


class PPThesaurusManager {

	protected static $oInstance;
	public static $sSkosUri = 'http://www.w3.org/2004/02/skos/core#';

	protected $oStore;
	protected $aList;
	protected $sLanguage;



	protected function __construct () {
		// Den internen ARC-Triplestore konfigurieren
		$aConfig = array(
			'db_host'		=> DB_HOST,
			'db_name'		=> DB_NAME,
			'db_user'		=> DB_USER,
			'db_pwd'		=> DB_PASSWORD,
			'store_name'	=> getWpPrefix() . 'pp_thesaurus',
		);
		$this->oStore = ARC2::getStore($aConfig);
		if (!$this->oStore->isSetUp()) {
			$this->oStore->setUp();
		}
		$this->aList = array();
		$this->sLanguage 	= get_option('PPThesaurusLanguage');
	}

	public static function getInstance () {
		if(!isset(self::$oInstance)){
			$sClass =  __CLASS__;
			self::$oInstance = new $sClass();
		}
		return self::$oInstance;
	}

	public static function existsTripleStore () {
		$aConfig = array(
			'db_host'		=> DB_HOST,
			'db_name'		=> DB_NAME,
			'db_user'		=> DB_USER,
			'db_pwd'		=> DB_PASSWORD,
			'store_name'	=> getWpPrefix() . 'pp_thesaurus',
		);
		$oStore = ARC2::getStore($aConfig);
		if (!$oStore->isSetUp()) {
			return false;
		}
		$sQuery = "
			PREFIX skos: <" . self::$sSkosUri . ">

			SELECT ?concept
			WHERE {
				?concept a skos:Concept .
			}
			LIMIT 1";
		$aRow = $oStore->query($sQuery, 'row');
		return count($aRow);
	}

	public static function importFromFile () {
		$aUploadFile = $_FILES['rdfFile'];

		// Es wurde kein SKOS File zum Importieren angegeben
		if ($aUploadFile['error'] == 4) {
			return true;
		}

		// Upgeloadete File ueberpruefen
		if ($aUploadFile['error'] >= 1) {
			throw new Exception ('An error has occured while uploading the file.');
		}
		if ($aUploadFile['type'] != 'application/rdf+xml') {
			throw new Exception ('The specified file is not an RDF file.');
		}
		if (!is_uploaded_file($aUploadFile['tmp_name'])) {
			throw new Exception ('An error has occured while uploading the file.');
		}

		// Das angegebene SKOS File in den ARC-Triplestore laden
		$aConfig = array(
			'db_host'		=> DB_HOST,
			'db_name'		=> DB_NAME,
			'db_user'		=> DB_USER,
			'db_pwd'		=> DB_PASSWORD,
			'store_name'	=> getWpPrefix() . 'pp_thesaurus',
		);
		$oStore = ARC2::getStore($aConfig);
		if (!$oStore->isSetUp()) {
			$oStore->setUp();
		}

		// All tables are emptied
		$oStore->reset();

		// Load RDF data into ARC store
		if (!($oStore->query('LOAD <file://' . $aUploadFile['tmp_name'] . '>'))) {
			throw new Exception ('The RDF data could not be stored to the database.');
		}
	}

	public static function importFromEndpoint () {

		// Get data from spaql endpoint
		if (empty($_POST['SparqlEndpoint'])) {
			throw new Exception ('No SPARQL endpoint has been indicated.');
		}

		$aConfig = array(
			'remote_store_endpoint'	=> $_POST['SparqlEndpoint'],
			'remote_store_timeout'	=> 2
		);
		$oEPStore = ARC2::getRemoteStore($aConfig);

		// Save data into ARC store
		$aConfig = array(
			'db_host'		=> DB_HOST,
			'db_name'		=> DB_NAME,
			'db_user'		=> DB_USER,
			'db_pwd'		=> DB_PASSWORD,
			'store_name'	=> getWpPrefix() . 'pp_thesaurus',
		);
		$oARCStore = ARC2::getStore($aConfig);
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
			throw new Exception ('The transfer of data from the SPARQL endpoint is not possible.');
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
				throw new Exception ('Storing the data from the SPARQL endpoint in the database is not possible.');
			}
			self::importFromEndPointLoop($oEPStore, $oARCStore, ++$iCounter);
		}
	}


	public function parse ($sContent) {
		$aConcepts = $this->getConcepts();

		// Die vohandenen HTML-Tags suchen und mit einem Placeholder tauschen, damit sie nicht ueberschrieben werden
		$aSearches = array(
			"<a [^>]+>(.*?)<\/a>",				// match all html links
			"<select [^>]+>(.*?)<\/select>",	// match all dropdown lists
			"<[a-z][a-z0-9]*\s+\S+=[^>]+>"		// match all tags with attributes
		);
		preg_match_all('/' . join('|', $aSearches) . '/i', $sContent, $aMatches);
		$aTagMatches = $aMatches[0];
		$iTagMatchCount = count($aTagMatches);
		for ($i=0; $i<$iTagMatchCount; $i++) {
		    $sContent = str_replace($aTagMatches[$i], "<pp-linkplaceholder>$i</pp-linkplaceholder>", $sContent);
		}

		// Um die gefundenen Begriffe im Content einen speziellen Tag legen (nur dem 1. Fund pro Begriff)
		$aTermMatches = array();
		foreach($aConcepts as $iId => $oConcept){
			$sLabel = addcslashes($oConcept->label, '/.*+');
			$sLabel = '/\b' . $sLabel . '\b/i';
			if (preg_match($sLabel, $sContent, $aMatches)) {
				$aTermMatches[$iId] = $aMatches[0];
				$sPlaceholder = "<pp-termplaceholder>$iId</pp-termplaceholder>";
				$sContent = preg_replace($sLabel, $sPlaceholder, $sContent, 1);
			}
		}

		foreach ($aTermMatches as $iId => $sMatch) {
			$oConcept 		= $aConcepts[$iId];
			$sDefinition 	= $this->getDefinition($oConcept->uri, $oConcept->definition, true);
			$sPlaceholder 	= "<pp-termplaceholder>$iId</pp-termplaceholder>";
			$sLink 			= pp_thesaurus_get_link($sMatch, $oConcept->uri, $oConcept->prefLabel, $sDefinition);
			$sContent 		= str_replace($sPlaceholder, $sLink, $sContent);
		}

		// Alle Placeholder wieder wieder richtig stellen
		for ($i=0; $i<$iTagMatchCount; $i++) {
		    $sContent = str_replace("<pp-linkplaceholder>$i</pp-linkplaceholder>", $aTagMatches[$i], $sContent);
		}

		return $sContent;
	}


	public function getDefinition ($sConceptUri, $sDefinition, $bTruncate=false) {
		if (preg_match('/No reegle definition available/i', $sDefinition)) {
			$sDefinition = '';
		}
		if (empty($sDefinition)) {
			$sQuery = "
				PREFIX skos: <" . self::$sSkosUri . ">

				SELECT ?dbpediaUri
				WHERE {
					<$sConceptUri> a skos:Concept .

					OPTIONAL {
						<$sConceptUri> skos:exactMatch ?dbpediaUri .
					}
					OPTIONAL {
						<$sConceptUri> skos:closeMatch ?dbpediaUri .
					}

					FILTER(regex(str(?dbpediaUri), '^http://dbpedia.org', 'i'))
				}";
			$aRow = $this->oStore->query($sQuery, 'row');

			if ($this->oStore->getErrors()) {
				throw new Exception ("Could not execute query $sQuery");
			}

			if (!empty($aRow)) {
				$sDbPediaUri = $aRow['dbpediaUri'];
			}

		}
		if (empty($sDefinition) && !empty($sDbPediaUri)) {
			$sDefinition = $this->getDbPediaDefinition($sDbPediaUri);
		}

		if ($bTruncate) {
			return $this->truncate($sDefinition);
		}
		return $sDefinition;
	}


	protected function getDbPediaDefinition ($sConceptUri) {
		$aConfig = array(
			'remote_store_endpoint'	=> 'http://sparql.reegle.info',
			'remote_store_timeout'	=> 2
		);
		$oEPStore = ARC2::getRemoteStore($aConfig);

		$sQuery = "
			PREFIX rdfs:<http://www.w3.org/2000/01/rdf-schema#>
			PREFIX dbpedia-owl:<http://dbpedia.org/ontology/>

			SELECT *
			WHERE {
				<$sConceptUri> rdfs:label ?label.
				<$sConceptUri> dbpedia-owl:abstract ?description.
				FILTER (lang(?label) = '" . $this->sLanguage . "' && lang(?description) = '" . $this->sLanguage . "').
			}";

		$aRow = $oEPStore->query($sQuery, 'row');
		if ($this->oStore->getErrors() || empty($aRow)) {
			return '';
		}
		
		return trim($aRow['description']);
	}

	protected function truncate($sText, $iLength = 300, $sEtc = ' ...', $bBreakWords = false) {
		if ($iLength == 0) {
			return '';
		}

		if (strlen($sText) > $iLength) {
			$iLength -= min($iLength, strlen($sEtc));
			if (!$bBreakWords) {
				$sText = preg_replace('/\s+?(\S+)?$/', '', substr($sText, 0, $iLength+1));
			}
			return substr($sText, 0, $iLength) . $sEtc;
		} else {
			return $sText;
		}
	}

	public function exists () {
		$aList = $this->getConcepts();
		return !empty($aList);
	}


	public function getAbcIndex ($sFilter='ALL') {
		$aList = $this->getConcepts('sortByLabel');

		$aIndex['ALL'] 	= 'enabled';
		for ($i=65; $i<=90; $i++) {
			$aIndex[$i] = 'disabled';
		}
		$aIndex[35] 	= 'disabled'; // is the "#" sign

		foreach ($aList as $oConcept) {
			$sChar = ord(strtoupper($oConcept->label));
			if (!($sChar >= 65 && $sChar <= 90)) {
				$sChar = '35';
			}
			$aIndex[$sChar] = ($sChar == $sFilter) ? 'selected' : 'enabled';
		}

		if (strtoupper($sFilter) == 'ALL') {
			$aIndex['ALL'] = 'selected';
		}

		return $aIndex;
	}


	public function getList ($sFilter='ALL') {
		$aList = $this->getConcepts('sortByLabel');
		if (strtoupper($sFilter) == 'ALL') {
			return $aList;
		}

		$aReturn = array();
		foreach ($aList as $iId => $oConcept) {
			$sChar = ord(strtoupper($oConcept->label));
			if (!($sChar >= 65 && $sChar <= 90)) {
				$sChar = '35';
			}
			if ($sChar == $sFilter) {
				$aReturn[$iId] = $oConcept;
			}
		}

		return $aReturn;
	}


	public function getItem ($sUri) {
		$sUri = trim($sUri);
		if (empty($sUri)) {
			return null;
		}
		$sQuery = "
			PREFIX skos: <" . self::$sSkosUri . ">

			SELECT *
			WHERE {
				<$sUri> a skos:Concept .
				<$sUri> skos:prefLabel ?prefLabel FILTER (lang(?prefLabel) = '" . $this->sLanguage . "') .
				<$sUri> skos:definition ?definition FILTER (lang(?definition) = '" . $this->sLanguage . "') .
				OPTIONAL {<$sUri> skos:altLabel ?altLabel FILTER (lang(?altLabel) = '" . $this->sLanguage . "') . }
				OPTIONAL {<$sUri> skos:hiddenLabel ?hiddenLabel FILTER (lang(?hiddenLabel) = '" . $this->sLanguage . "') . }
				OPTIONAL {<$sUri> skos:scopeNote ?scopeNote FILTER (lang(?scopeNote) = '" . $this->sLanguage . "') . }
				OPTIONAL {<$sUri> skos:notation ?notation . }

				FILTER(!bound(?notation)).
			}
		";
		$aRows = $this->oStore->query($sQuery, 'rows');

		if ($this->oStore->getErrors()) {
			throw new Exception ("Could not execute query $sQuery");
		}
		if (empty($aRows)) {
			return null;
		}

		$oItem 			= new PPThesaurusItem();
		$aAltLables 	= array();
		$aHiddenLables 	= array();
		$aDefinitions 	= array();
		$bWithRelations = true;

		foreach ($aRows as $aRow) {
			$oItem->uri = $sUri;
			$oItem->prefLabel = trim($aRow['prefLabel']);
			if (isset($aRow['altLabel'])) {
				$aAltLabels[] = trim($aRow['altLabel']);
			}
			if (isset($aRow['hiddenLabel'])) {
				$aHiddenLabels[] = trim($aRow['hiddenLabel']);
			}
			if (isset($aRow['definition'])) {
				$aDefinitions[] = $aRow['definition'];
			}
			if (isset($aRow['scopeNote'])) {
				$oItem->scopeNote = $aRow['scopeNote'];
			}
			if ($bWithRelations) {
				$oItem->broaderList 	= $this->getItemRelations($sUri, 'broader');
				$oItem->narrowerList 	= $this->getItemRelations($sUri, 'narrower');
				$oItem->relatedList 	= $this->getItemRelations($sUri, 'related');
				$bWithRelations = false;
			}
		}

		if (!empty($aAltLabels)) {
			$oItem->altLabels = array_unique($aAltLabels);
		}
		if (!empty($aHiddenLabels)) {
			$oItem->hiddenLabels = array_unique($aHiddenLabels);
		}
		if (!empty($aDefinitions)) {
			$oItem->definition = join('<br />', array_unique($aDefinitions));
		}
		$oItem->searchLink = get_option('siteurl') . '?s=' . urlencode($oItem->prefLabel);

		return $oItem;
	}


	protected function getItemRelations ($sUri, $sRelType) {
		$sQuery = "
			PREFIX skos: <" . self::$sSkosUri . ">

			SELECT DISTINCT ?relation ?prefLabel ?definition
			WHERE {
				<$sUri> a skos:Concept .
				<$sUri> skos:$sRelType ?relation .
				?relation skos:prefLabel ?prefLabel .
				?relation skos:definition ?definition .
				OPTIONAL { ?relation skos:notation ?notation. }
			  
				FILTER (lang(?prefLabel) = '" . $this->sLanguage . "' && !bound(?notation)) .
			}
		";

		$aRows = $this->oStore->query($sQuery, 'rows');
		if (count($aRows) <= 0) {
			return array();
		}

		if ($this->oStore->getErrors()) {
			throw new Exception ("Could not execute query $sQuery");
		}

		$aResult 		= array();
		$aDefinitions	= array();
		$sLastConcept 	= '';
		$oItem 			= new PPThesaurusItem();
		$bFirst 		= true;
		foreach ($aRows as $aRow) {
			if ($aRow['relation'] != $sLastConcept) {
				if (!$bFirst) {
					$oItem->definition 	= join('<br />', $aDefinitions);
					$aResult[] 			= $oItem;
					$oItem				= new PPThesaurusItem();
					$aDefinitions		= array();
				}
				$sLastConcept 		= $aRow['relation'];
				$oItem->uri 		= $aRow['relation'];
				$oItem->prefLabel 	= trim($aRow['prefLabel']);
			}
			$aDefinitions[] = $aRow['definition'];
			$bFirst = false;
		}
		$oItem->definition 	= join('<br />', $aDefinitions);
		$aResult[] 			= $oItem;

		return $aResult;
	}


	protected function getConcepts ($sSort='sortByCount' ) {
		if (empty($this->aList)) {
			$sQuery = "
				PREFIX skos: <" . self::$sSkosUri . ">

				SELECT DISTINCT ?concept ?label ?definition ?rel
				WHERE {
					?concept a skos:Concept .
			  		?concept skos:definition ?definition .
					?concept ?rel ?label .
					OPTIONAL { ?concept skos:notation ?notation. }

					{ ?concept skos:prefLabel ?label . }
					UNION
					{ ?concept skos:altLabel ?label . }
					UNION
					{ ?concept skos:hiddenLabel ?label . }

					FILTER (str(?label) != '' && lang(?label) = '" . $this->sLanguage . "' && !bound(?notation)).
				}
			";

			$aRows = $this->oStore->query($sQuery, 'rows');

			if ($this->oStore->getErrors()) {
				throw new Exception ("Could not execute query: $sQuery");
			}
			if (count($aRows) <= 0) {
				return $this->aList;
			}

			$sLastConcept 	= '';
			$oConcept 		= new PPThesaurusItem();
			$aDefinitions	= array();
			$bFirst 		= true;
			$aPrefLabels	= array();
			$aOtherLabels	= array();
			$i				= 0;
			foreach ($aRows as $aRow) {
				if ($aRow['label'] != $sLastConcept) {
					if (!$bFirst) {
						$oConcept->definition = join('<br />', $aDefinitions);
						if ($oConcept->rel == self::$sSkosUri . 'prefLabel') {
							$aPrefLabels[$oConcept->uri] = $oConcept->label;
							$oConcept->prefLabel = $oConcept->label;
							if (isset($aOtherLabels[$oConcept->uri]) && !empty($aOtherLabels[$oConcept->uri])) {
								foreach ($aOtherLabels[$oConcept->uri] as $iId) {
									$this->aList[$iId]->prefLabel = $oConcept->prefLabel;
								}
							}
						} else {
							$aOtherLabels[$oConcept->uri][] = $i;
							if (isset($aPrefLabels[$oConcept->uri])) {
								$oConcept->prefLabel = $aPrefLabels[$oConcept->uri];
							}
						}
						$this->aList[$i++] 	= $oConcept;
						$oConcept 			= new PPThesaurusItem();
						$aDefinitions		= array();
					}
					$sLastConcept 		= $aRow['label'];
					$sLabel 			= preg_replace('/ {2,}/', ' ', $aRow['label']);
					$oConcept->uri 		= $aRow['concept'];
					$oConcept->label 	= trim($sLabel);
					$oConcept->rel 		= $aRow['rel'];
					$oConcept->count 	= count(explode(' ', $oConcept->label));
				}
				$sDefinition = trim($aRow['definition']);
				if (!empty($sDefinition)) {
					$aDefinitions[] = $sDefinition;
				}
				$bFirst = false;
			}
			$oConcept->definition 	= join('<br />', $aDefinitions);
			$this->aList[$i] 		= $oConcept;

			unset($aPrefLabels);
			unset($aOtherLabels);
		}

		usort($this->aList, array($this, $sSort));

		return $this->aList;
	}


	protected function sortByCount ($a, $b) {
		if ($a->count == $b->count) {
			return 0;
		}
		return ($a->count < $b->count) ? 1 : -1;
	}


	protected function sortByLabel ($a, $b) {
		return strcasecmp($a->label, $b->label);
	}
}
