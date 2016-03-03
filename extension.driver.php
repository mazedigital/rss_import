<?php

include_once(EXTENSIONS . '/rss_import/lib/class.rssimport.php');

class extension_Rss_Import extends Extension {

	public function fetchNavigation()
	{
		$navigation = array(
			array(
				'location'  => __('Blueprints'),
				'name'      => __('RSS Import'),
				'link'      => '/import/'
			)
		);

		return $navigation;
	}		
	
	/**
	 * Installation
	 */
	public function install() {
		// Roles table:
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_rss_import` (
				`id` INT(11) unsigned NOT NULL auto_increment,
				`entry_id` INT(11) unsigned NOT NULL,
				`guid` VARCHAR(255) NOT NULL,
				PRIMARY KEY (`id`)
			);
		");
	}

	public static function baseURL(){
		return SYMPHONY_URL . '/extension/rss_import/';
	}

}
?>