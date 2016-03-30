<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.eventmanager.php');
	require_once CONTENT . '/class.sortable.php';
	require_once(EXTENSIONS . '/rss_import/lib/class.rssimport.php');

	Class contentExtensionRss_ImportImport extends AdministrationPage {

		private $datasource = array();

		public function build(array $context = array()) {

			Administration::instance()->Page->addStylesheetToHead('//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css', 'screen', 221);

			if (isset($context[0])) {
				$this->datasource = $context[0];
				unset($context[0]);
			}

			parent::build($context);

		}

		// for the time being allow every author to access this page - eventually might need to add some permissions
		public function canAccessPage(){
			return true;
		}

		public function __viewIndex() {

			if ($this->datasource){
				return $this->__viewImport();
			}

			$datasources = DatasourceManager::listAll();
			$datasources = array_filter($datasources,function($var){
				if ($var['source'] == 'RemoteDatasource')
					return 1;
				else return 0;
			});
			
			$tbody = new XMLElement('tbody');

			foreach ($datasources as $key => $datasource) {
				$row = new XMLElement('tr',new XMLElement('td',new XMLElement('a',$datasource['name'],array('href'=> extension_RSS_Import::baseURL() . 'import/'.$datasource['handle']))));
				$tbody->appendChild($row);
			}

			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('RSS Import'))));

			//if developer/manager ? 
			$this->appendSubheading(__('Select Datasource'));

			$columns[] = array(
				'label' => __('Datasource'),
				// 'sortable' => true,
				'handle' => 'datasource',
				'attrs' => array(
					'id' => 'field-name'
				)
			);

			$aTableHead = Sortable::buildTableHeaders($columns, $_GET['sort'], $_GET['order'], ($filter_querystring) ? "&amp;" . $filter_querystring : '');

			$table = Widget::Table(
				Widget::TableHead($aTableHead),
				NULL,
				$tbody
			);

			$this->Form->appendChild($table);
		}


		public function __viewImport() {

			$datasource = DatasourceManager::create($this->datasource);

			$result = $datasource->execute();

			$dom = new DOMDocument();
			$dom->loadXML($result->generate(true));

			// Load an XSL template to convert the data to the required output
			$xsl = new DOMDocument;
			$xsl->load(EXTENSIONS.'/rss_import/templates/rss.xsl');

			$proc = new XSLTProcessor;
			$proc->importStyleSheet($xsl);

			$html = trim(substr($proc->transformToXML($dom), strlen('<?xml version="1.0"?>')));

			
			$result = XMLElement::convertFromXMLString('tbody',$html);

			$guids = array();
			$rows = $result->getChildrenByName('tr');
			foreach ($rows as $key => $row) {
				$guids[$key] = "'" . $row->getAttribute('guid') . "'";
			}

			$guidString = implode(',',$guids); 

			$entries = Symphony::Database()->fetch(' SELECT entry_id,guid FROM tbl_rss_import WHERE `guid` in ('. implode(',',$guids) .')' );

			$guidIds = array();

			foreach ($entries as $value) {
				$guidIds[$value['guid']] = $value['entry_id'];
			}

			foreach ($rows as $key => $row) {

				// set the date according to Symphony Timestamp
				$td = $row->getChildByName('td',2);
				$span = $td->getChild(0);

				$date = $span->getAttribute('date');
				$time = strtotime($date);
				$date = date('d M Y H:i',$time);
				$span->setValue($date);
				// var_dump($date);die;

				if (isset($guidIds[$row->getAttribute('guid')])){
					$row->setAttribute('disabled','disabled');

					$td = $row->getChildByName('td',0);
					$link = '/symphony/publish/articles/edit/' . $guidIds[$row->getAttribute('guid')] . '/';
					$a = new XMLElement('a',$td->getValue(),array('href'=>$link));
					$td->replaceValue($a);

					//remove checkbox so cannot be seleted
					$td = $row->getChildByName('td',2);
					$td->removeChildAt(1);
				}
			}


			$this->setPageType('table');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Import'))));

			//if developer/manager ? 
			$this->appendSubheading(__('Import' . ' - ' . $datasource->about()['name'] ));

			$columns[] = array(
				'label' => __('Title'),
				'handle' => 'title',
				'attrs' => array(
					'id' => 'field-name'
				)
			);
			$columns[] = array(
				'label' => __('Description'),
				'handle' => 'description',
				'attrs' => array(
					'id' => 'field-description'
				)
			);
			$columns[] = array(
				'label' => __('Publish Date'),
				'handle' => 'publish-date',
				'attrs' => array(
					'id' => 'field-publish-date'
				)
			);

			$aTableHead = Sortable::buildTableHeaders($columns, $_GET['sort'], $_GET['order'], ($filter_querystring) ? "&amp;" . $filter_querystring : '');

			$table = Widget::Table(
				Widget::TableHead($aTableHead),
				NULL,
				$result,
				'selectable'
			);

			$scriptContent =	"jQuery(document).ready(function(){
									jQuery('table').symphonySelectable();
								});";
			$script = new XMLElement('script',$scriptContent);
			$this->addElementToHead($script);

			$this->Form->appendChild($table);

			$tableActions = new XMLElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				0 => array(null, false, __('With Selected...')),
				1 => array('create', false, __('Create'), 'confirm'),
			);

			$tableActions->appendChild(Widget::Apply($options));
			$this->Form->appendChild($tableActions);
		}

		public function __actionIndex() {

			$checked = (is_array($_POST['items'])) ? array_keys($_POST['items']) : null;

			if(is_array($checked) && !empty($checked)) {

				switch ($_POST['with-selected']) {
					case 'create':
						foreach($checked as $guid) {
							//only if user or has Permissions
							RssImportManager::create($guid);
						}

						break;
				}
			}
		}
	}
