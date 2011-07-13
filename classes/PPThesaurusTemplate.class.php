<?php

class PPThesaurusTemplate {

	private $oPPTM;
	private $oItem;

	public function __construct () {
		$this->oPPTM = PPThesaurusManager::getInstance();
		$this->oItem = NULL;
	}

	public function showABCIndex ($aAtts) {
		$iPPThesaurusId = get_option('PPThesaurusId');
		$oPage 			= get_page($iPPThesaurusId);
		$i 				= 1;

		if (isset($_GET['filter'])) {
			$iChar = $_GET['filter'];
		} else {
			try {
				if (is_null($this->oItem)) {
					$this->oItem = $this->oPPTM->getItem($_GET['uri']);
				}
			} catch (Exception $e) {
				return '<p>' . _e('An error has occurred while reading concept data.', 'Poolparty Thesaurus') . '</p>';
			}

			$iChar = ord(strtoupper($this->oItem->prefLabel));
		}
		$aIndex 	= $this->oPPTM->getAbcIndex($iChar);
		$iCount 	= count($aIndex);
		$sContent 	= '<ul class="PPThesaurusAbcIndex">';
		foreach($aIndex as $sChar => $sKind) {
			$sClass = ($i == 1) ? 'first' : ($i == $iCount ? 'last' : '');
			$sLetter = ($sChar == 'ALL') ? 'ALL' : chr($sChar);
			$sLink = '<a href="' . get_option('siteurl') . '/' . $oPage->post_name . '?filter=' . $sChar . '">' . $sLetter . '</a>';
			switch ($sKind) {
				case 'disabled':
					if (!empty($sClass)) {
						$sClass = ' class="' . $sClass . '"';
					}
					$sContent .= '<li' . $sClass . '>' . $sLetter . '</li>';
					break;

				case 'enabled':
					if (!empty($sClass)) {
						$sClass = ' class="' . $sClass . '"';
					}
					$sContent .= '<li' . $sClass . '>' . $sLink . '</li>';
					break;

				case 'selected':
					$sContent .= '<li class="selected ' . $sClass . '">' . $sLink . '</li>';
					break;
			}
			$i++;
		}
		$sContent .= '</ul>';

		return $sContent;
	}


	public function showItemDetails ($aAtts) {
		try {
			if (is_null($this->oItem)) {
				$this->oItem = $this->oPPTM->getItem($_GET['uri']);
			}
		} catch (Exception $e) {
			return '<p>' . _e('An error has occurred while reading concept data.', 'Poolparty Thesaurus') . '</p>';
		}

		$sContent = '<div class="PPThesaurusDetails">';
		if ($this->oItem->altLabels) {
			$sContent .= '<div class="synonyms"><strong>Synonyms:</strong> ' . implode(', ', $this->oItem->altLabels) . '</div>';
		}
		$sContent .= '<p class="description">' . $this->oPPTM->getDefinition($this->oItem->uri, $this->oItem->definition) . '</p>';

		if ($this->oItem->scopeNote) {
			$sContent .= '<blockquote>' . $this->oItem->scopeNote . '</blockquote>';
		}

		if ($this->oItem->relatedList) {
			$sContent .= '<p class="relation"><strong>Related terms:</strong><br />' . implode(', ', pp_thesaurus_to_link($this->oItem->relatedList)) . '</p>';
		}

		if ($this->oItem->broaderList) {
			$sContent .= '<p class="relation"><strong>Broader terms:</strong><br />' . implode(', ', pp_thesaurus_to_link($this->oItem->broaderList)) . '</p>';
		}

		if ($this->oItem->narrowerList) {
			$sContent .= '<p class="relation"><strong>Narrower terms:</strong><br />' . implode(', ', pp_thesaurus_to_link($this->oItem->narrowerList)) . '</p>';
		}

		if ($this->oItem->uri) {
			$sLink = $this->oItem->uri . '/' . urlencode($this->oItem->prefLabel) . '.htm';
			$sContent .= '<p>Go to <strong>' . $this->oItem->prefLabel . '</strong> on <a href="' . $sLink . '" target="_blank" title="go to reegle.info">reegle.info for all details</a>.</p>';
		}

		if ($this->oItem->searchLink) {
			$sContent .= '<p><strong>Search for</strong> <a href="' . $this->oItem->searchLink . '" title="Search for ' . $this->oItem->prefLabel . '">' . $this->oItem->prefLabel . '</a></p>';
		}

		$sContent .= '</div>';

		return $sContent;
	}


	public function setTitle ($sTitle) {
	    $iPPThesaurusId = get_option('PPThesaurusId');
	    $aChildren  = get_children(array('numberposts'  => 1,
										 'post_parent'  => $iPPThesaurusId,
										 'post_type'    => 'page'));
		$oChild = array_shift($aChildren);

		if (!is_page($oChild->ID)) {
			return $sTitle;
		}

		try {
			if (is_null($this->oItem)) {
				$this->oItem = $this->oPPTM->getItem($_GET['uri']);
			}
		} catch (Exception $e) {
			return 'Error';
		}

		return $this->oItem->prefLabel;
	}


	public function showItemList ($aAtts) {
		$aList 		= $this->oPPTM->getList($_GET['filter']);
		$iCount 	= count($aList);
		$sContent 	= '';
		if ($iCount > 0) {
			$sContent .= '<ul class="PPThesaurusList">';
			$i = 1;
			foreach($aList as $oConcept) {
				if ($oConcept->rel != PPThesaurusManager::$sSkosUri . 'hiddenLabel') {
					$sClass = ($i == 1) ? ' class="first"' : ($i == $iCount ? ' class="last"' : '');
					$sDefinition = $this->oPPTM->getDefinition($oConcept->uri, $oConcept->definition, true);
					$sContent .= '<li' . $sClass . '>' . pp_thesaurus_get_link($oConcept->label, $oConcept->uri, $oConcept->prefLabel, $sDefinition, true) . '</li>';
					$i++;
				}
			}
			$sContent .= '</ul>';
		}

		return $sContent;
	}
}
