=== climate change glossary ===
Author URI: http://www.reegle.info
Plugin URI: http://poolparty.punkt.at
Contributors: reegle
Tags: renewable energy, energy efficiency, climate development, renewables, efficiency, glossary, thesaurus, poolparty, skos, rdf
Requires at least: 2.9
Tested up to: 2.9
Stable tag: trunk


This plugin imports a SKOS thesaurus via ARC2. It highlighs terms and generates links automatically in any page which contains terms from the thesaurus.




== Description ==

With this plugin any SKOS thesaurus can be imported into your Wordpress blog (via ARC2 into an RDF triple store) and with only two pages (automatically generated) the whole thesaurus can be displayed and used as a glossary on your homepage. One page is the main page of the glossary which displays all concepts with their preferred labels and their alternative labels (synonyms). The list of concepts is displayed in an alphabetical order. Concepts can be filtered by their first letters. The second page represents the detail view of each concept. All kinds of labels and relations (prefLabel, altLabel, hiddenLabel, definition, scopNote, broader, narrower und related) of a given term (concept) can be loaded and displayed. 
Each Wordpress posts is analysed by the plugin to find out if phrases occur which are also labels of a concept (prefLabel, altLabel or hiddenLabel) in the thesaurus. The first hit will automatically generate links to the corresponding concept and its definition.

Thanks to Benjamin Nowack: The thesaurus is imported and into the system and is queried via ARC2 (https://github.com/semsol/arc2).
Thanks to rduffy (http://wordpress.org/extend/plugins/profile/rduffy). His 'Glossary' Plugin (http://wordpress.org/extend/plugins/automatic-glossary) inpired me, and I was able to develop this plugin on top of his ideas.

Works with PHP 5, MySQL 5 und ARC2





== Installation ==

Install using WordPress:
1. Log in and go to 'Plugins' and click on 'Add New'.
1. Search for 'climate change glossary' and hit the 'Install Now' link in the results. WordPress will install it.
1. See below

Install manually:
1. Download the plugin zip file and unzip it.
1. Upload the plugin contents into your WordPress installation's plugin directory on the server. The plugin's .php files, readme.txt and subfolders should be installed in the `wp-content/plugins/climate-change-glossary/` directory.
1. See below

More installation points:
1. Download ARC2 from https://github.com/semsol/arc2 and unzip it.
1. Open the unziped folder and upload the entire contents into the `/wp-content/plugins/climate-change-glossary/arc/` directory.
1. From the Plugin Management page in Wordpress, activate the 'Climate change glossary' plugin.
1. Go to 'Settings' -> 'Climate change glossary' in Wordpress and click on 'Import/Update Thesaurus'. Uploading the thesaurus can take a few minutes (4-5 minutes). Please remain patient and don't interrupt the procedure.





== Frequently Asked Questions ==

= Does my main automatically generated glossary page need to be titled "Glossary"? =

No. It can be called whatever you like. You can enter a content if you like, but be careful with the shortcuts.

= Does my automatically generated subpage need to be titled "Item"? =

No. It can be called whatever you like. You can enter a content if you like, but be careful with the shortcuts.

= How do I add a thesaurus item?  =

You will need a SKOS thesaurus management tool like PoolParty (http://poolparty.punkt.at/) to add/modify terms. The glossary is generated automatically from the imported thesaurus.

= How can I update the glossary? =

Simply load the updated thesaurus again (admin area: 'Settings' -> 'Climate change glossary'). The old thesaurus will be overwritten. New or updated concepts will be recognized immediately by the link generator.

= How to style the tooltip? =

This tooltip consists of a CSS file and three PNG pictures which can be found in the plugin directory (`js/unitip/`). Two pictures consist of the top and bottom edge with and without the pointer and the third picture consists of the middle part.
To style this tooltip, the three pictures can be interchanged and the CSS file adjusted accordingly.





== Screenshots ==

1. Tooltip with the description of a concept
2. The detail page of a concept
3. Admin settings page




== Changelog ==

= 1.0 =
