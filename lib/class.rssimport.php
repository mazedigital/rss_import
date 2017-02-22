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

		public function titleCase($string, $delimiters = array(" ", "-", ".", "‘", "O'", "Mc"), $exceptions = array("I", "II", "III", "IV", "V", "VI"))
			{
			    /*
			     * Exceptions in lower case are words you don't want converted
			     * Exceptions all in upper case are any words you don't want converted to title case
			     *   but should be converted to upper case, e.g.:
			     *   king henry viii or king henry Viii should be King Henry VIII
			     */
			    // $string = mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
			    foreach ($delimiters as $dlnr => $delimiter) {
			        $words = explode($delimiter, $string);
			        $newwords = array();
			        foreach ($words as $wordnr => $word) {
			            if (in_array(mb_strtoupper($word, "UTF-8"), $exceptions)) {
			                // check exceptions list for any words that should be in upper case
			                $word = mb_strtoupper($word, "UTF-8");
			            } elseif (in_array(mb_strtolower($word, "UTF-8"), $exceptions)) {
			                // check exceptions list for any words that should be in upper case
			                $word = mb_strtolower($word, "UTF-8");
			            } elseif (!in_array($word, $exceptions)) {
			                // convert to uppercase (non-utf8 only)
			                $word = ucfirst($word);
			            }
			            array_push($newwords, $word);
			        }
			        $string = join($delimiter, $newwords);
			   }//foreach
			   return $string;
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
					'section' => '3', //Breaking news
				);

			$entry = EntryManager::create();
			$entry->set('section_id', $options['section']);
			$entry->set('author_id', is_null(Symphony::Engine()->Author()) ? '1' : Symphony::Engine()->Author()->get('id'));
			$entry->set('modification_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
			$entry->set('modification_date', DateTimeObj::get('Y-m-d H:i:s'));
			$entry->set('creation_date_gmt', DateTimeObj::getGMT('Y-m-d H:i:s'));
			$entry->set('creation_date', DateTimeObj::get('Y-m-d H:i:s'));

			$values = array();

			//if no JTA link is found in first 40 characters, add it.

			$content = RssImportManager::markdownify($result->getChildByName('content',0)->getValue());
			$authors = $result->getChildByName('author',0)->getValue();
			$jtaAuthor = false;

			$authorArray = explode(',', $authors);

			foreach ($authorArray as $key => $value) {
				if($value == 'JTA'){
					$jtaAuthor = true;
				}
			}

			//Check if does not have credit and author is not JTA
			if(!preg_match('/^.{0,20}\\(\\[?(Reuters|JTA)\\]?/',$content) and !$jtaAuthor){
				//Add JTA link
				$values['body'] = "([JTA](http://www.jta.org '')) — ";
				$values['body'] .= $content;
			}
			else{
				$values['body'] = $content;
			}

			$values['headline'] = RssImportManager::titleCase($result->getChildByName('title',0)->getValue());
			$values['link']['handle'] = General::createHandle($result->getChildByName('title',0)->getValue());
			$values['excerpt'] = str_replace('(JTA)', '', RssImportManager::markdownify($result->getChildByName('description',0)->getValue()));
			$values['authors'] = $authors;
			$values['publish-date'] = $result->getChildByName('date',0)->getValue();
			$values['updated-date'] = $result->getChildByName('date',0)->getValue();
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

