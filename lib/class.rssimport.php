<?php

	Class RssImportManager {
		const __OK__ = 100;
		const __PARTIAL_OK__ = 110;
		const __ERROR_PREPARING__ = 200;
		const __ERROR_VALIDATING__ = 210;
		const __ERROR_CREATING__ = 220;
		
		public static function markdownify($string) {
			$string = html_entity_decode($string);
			require_once(EXTENSIONS . '/xmlimporter/lib/markdownify/markdownify_extra.php');
			$markdownify = new Markdownify(true, MDFY_BODYWIDTH, false);

			// anything in divs should not be considered as content
			$markdownify->drop[] = 'div';

			// use inline links
			$markdownify->linksInline = true;

			$markdown = $markdownify->parseString($string);
			$markdown = htmlspecialchars($markdown, ENT_NOQUOTES, 'UTF-8');
			return $markdown;
		}

		/**
		 * Insert an entry given a `$guid`.
		 *
		 * @param integer $guid
		 * @param boolean $purge_members
		 * @return boolean
		 */
		public static function create($guid,$datasource) {

			$datasource = DatasourceManager::create($datasource);
			
			$result = $datasource->execute();

			$dom = new DOMDocument();
			$dom->loadXML($result->generate(true));

				// If expression is an XSL file should use xsl templates 
				$xsl = new DOMDocument;
				$xsl->load(EXTENSIONS.'/rss_import/templates/single.xsl');

				$variable = $xsl->createElementNS('http://www.w3.org/1999/XSL/Transform','xsl:param');
				// var_dump($variable);die;

				$domAttribute = $xsl->createAttribute('name');
				$domAttribute->value = 'guid';
				$variable->appendChild($domAttribute);

				$domAttribute = $xsl->createAttribute('select');
				$domAttribute->value = "'" . $guid . "'";
				$variable->appendChild($domAttribute);

				$guidNode = $xsl->getElementsByTagName('guid')->item(0);

				$root = $xsl->documentElement;

				$root->replaceChild($variable,$guidNode);

				$proc = new XSLTProcessor;
				$proc->importStyleSheet($xsl);

				$html = trim(substr($proc->transformToXML($dom), strlen('<?xml version="1.0"?>')));

			// echo $html;die;

			
			$result = XMLElement::convertFromXMLString('single',$html);

			$current = array();

			$options = array(
					'section' => '3',
				);

			$entry = EntryManager::create();
			$entry->set('section_id', $options['section']);
			$entry->set('author_id', is_null(Symphony::Engine()->Author()) ? '1' : Symphony::Engine()->Author()->get('id'));
			$entry->set('modification_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
			$entry->set('modification_date', DateTimeObj::get('Y-m-d H:i:s'));
			$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
			$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));

			$values = array();

			$values['headline'] = $result->getChildByName('title',0)->getValue();
			$values['link']['handle'] = General::createHandle($result->getChildByName('title',0)->getValue());
			$values['excerpt'] = RssImportManager::markdownify($result->getChildByName('description',0)->getValue());
			$values['body'] = RssImportManager::markdownify($result->getChildByName('content',0)->getValue());
			$values['authors'] = $result->getChildByName('author',0)->getValue();
			$values['type'] = 'Article';
			$values['section'] = '50393';

			$passed = true;

			// Validate:
			try {
				if (__ENTRY_FIELD_ERROR__ == $entry->checkPostData($values, $current['errors'])) {
					$passed = false;
				}

				else if (__ENTRY_OK__ != $entry->setDataFromPost($values, $current['errors'], true, true)) {
					$passed = false;
				}
			}
			catch (Exception $ex) {
				$passed = false;
				$current['errors'] = array($ex->getMessage());

				Symphony::Log()->pushToLog(sprintf('RSS Import: Failed to set values for %s, %s', $guid, $ex->getMessage()), E_NOTICE, true);
			}

			if (!$passed) return self::__ERROR_VALIDATING__;
			else {

				$section = SectionManager::fetch($options['section']);

				###
				# Delegate: XMLImporterEntryPreCreate
				# Description: Just prior to creation of an Entry. Entry object provided
				Symphony::ExtensionManager()->notifyMembers(
					'XMLImporterEntryPreCreate', '/xmlimporter/importers/run/',
					array(
						'section'	=> $section,
						'fields'	=> &$values,
						'entry'		=> &$entry
					)
				);

				// $entry->commit();
				EntryManager::add($entry);
				$entry->set('importer_status', 'created');

				###
				# Delegate: XMLImporterEntryPostCreate
				# Description: Creation of an Entry. New Entry object is provided.
				Symphony::ExtensionManager()->notifyMembers(
					'XMLImporterEntryPostCreate', '/xmlimporter/importers/run/',
					array(
						'section'	=> $section,
						'entry'		=> $entry,
						'fields'	=> $values
					)
				);

				Symphony::Database()->insert( array(
					'entry_id' => $entry->get('id'),
					'guid' => $guid,
					), 'tbl_rss_import' );
			}


			return self::__OK__;
		}

		
	}

