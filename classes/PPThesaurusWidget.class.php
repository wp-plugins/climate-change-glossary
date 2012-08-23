<?php

class PPThesaurusWidget extends WP_Widget {

	public function __construct () {
		$sTitle = __(PP_THESAURUS_SIDEBAR_TITLE, 'pp-thesaurus');
		$sDescription = __(PP_THESAURUS_SIDEBAR_DESCRIPTION, 'pp-thesaurus');
		parent::__construct('pp_thesaurus_sidebar_search', $sTitle, array('description' => $sDescription));
	}


	public function form ($aInstance) {
		$aOptions = array(
			'title'	=> 'Search Glossary',
			'info'	=> 'Type a term ...',
			'width'	=> '100%'
		);
		$aInstance = wp_parse_args((array) $aInstance, $aOptions);
		$sTitle = attribute_escape($aInstance['title']);
		$sInfo	= attribute_escape($aInstance['info']);
		$sWidth = attribute_escape($aInstance['width']);
		echo '
		<p>
			<label for="' . $this->get_field_id('title') . '">' . __('Title', 'pp-thesaurus') . ': <br />
			<input id="' . $this->get_field_id('title') . '" class="widefat" name="' . $this->get_field_name('title') . '" type="text" value="' . $sTitle . '" />
			</label>
		</p>
		<p>
			<label for="' . $this->get_field_id('info') . '">' . __('Info text', 'pp-thesaurus') . ': <br />
			<input id="' . $this->get_field_id('info') . '" class="widefat" name="' . $this->get_field_name('info') . '" type="text" value="' . $sInfo . '" />
			</label>
		</p>
		<p>
			<label for="' . $this->get_field_id('width') . '">' . __('Width of the search field', 'pp-thesaurus') . ': <br />
			<input id="' . $this->get_field_id('width') . '" class="widefat" name="' . $this->get_field_name('width') . '" type="text" value="' . $sWidth . '" />
			</label> ("%" ' . __('or', 'pp-thesaurus') . ' "px")
		</p>
		';
	}


	public function update ($aNewInstance, $aOldInstance) {
		$aInstance = $aOldInstance;
		$aInstance['title'] = trim(strip_tags(stripslashes($aNewInstance['title'])));
		$aInstance['info'] 	= trim(strip_tags(stripslashes($aNewInstance['info'])));
		$aInstance['width'] = trim(strip_tags(stripslashes($aNewInstance['width'])));
		return $aInstance;
	}


	public function widget ($aArgs, $aInstance) {
		$oPPTM = PPThesaurusManager::getInstance();
		$sTitle = empty($aInstance['title']) ? '' : apply_filters('widget_title', $aInstance['title']);
		$sInfo	= $aInstance['info'];
		$sWidth	= empty($aInstance['width']) ? '' : 'style="width:' . $aInstance['width'] . '"';

		extract($aArgs);
		echo $before_widget;
		if (!empty($sTitle)) {
			echo $before_title . $sTitle . $after_title;
		}
		echo '
			<script type="text/javascript">
			//<![CDATA[
				var pp_thesaurus_suggest_url = "' . plugins_url('/pp-thesaurus-autocomplete.php', PP_THESAURUS_PLUGIN_FILE) . '?lang=' . $oPPTM->getLanguage() . '";
			//]]>
			</script>
			<div class="PPThesaurus_sidebar">
				<input id="pp_thesaurus_input_term" type="text" name="term" value="" title="' . $sInfo . '" ' . $sWidth . '" />
			</div>
		';
		echo $after_widget;
	}
}
