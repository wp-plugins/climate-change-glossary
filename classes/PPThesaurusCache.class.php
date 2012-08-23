<?php

class PPThesaurusCache {

	public static function getConcepts ($aConcepts) {
		$iPostId	= $GLOBALS['post']->ID;
		$sContent	= $GLOBALS['post']->post_content;
		$aCache 	= array();

		$aCache = get_post_meta($iPostId, 'pp-thesaurus-cache', true);
		if (!is_array($aCache)) {
			$aCache = array();
			foreach($aConcepts as $iId => $oConcept){
				$sPattern = addcslashes($oConcept->label, '/.*+()');
				// Ist der Label in Grossbuchstaben, dann auf casesensitive schalten
				$sPattern = '/(\W)(' . $sPattern . ')(\W)/';
				if (strcmp($sPattern, strtoupper($sPattern))) {
					$sPattern .= 'i';
				}
				if (preg_match($sPattern, $sContent, $aMatches)) {
					$aCache[] = $oConcept;
				}
			}
			update_post_meta($iPostId, 'pp-thesaurus-cache', $aCache);
		}
		return $aCache;
	}


	public static function deletePost ($iPostId) {
		delete_post_meta($iPostId, 'pp-thesaurus-cache');
	}


	public static function deleteAll () {
		$aAllPosts = get_posts('numberposts=-1&post_type=post&post_status=any');
		foreach ($aAllPosts as $oPostInfo) {
			delete_post_meta($oPostInfo->ID, 'pp-thesaurus-cache');
		}
	}
}
