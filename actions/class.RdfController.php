<?php
/**  
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * 
 * Copyright (c) 2002-2008 (original work) Public Research Centre Henri Tudor & University of Luxembourg (under the project TAO & TAO2);
 *               2008-2010 (update and modification) Deutsche Institut für Internationale Pädagogische Forschung (under the project TAO-TRANSFER);
 *               2009-2012 (update and modification) Public Research Centre Henri Tudor (under the project TAO-SUSTAIN & TAO-DEV);
 *               2013-2014 (update and modification) Open Assessment Technologies SA;
 * 
 */

use oat\tao\model\accessControl\AclProxy;
use oat\tao\model\accessControl\ActionResolver;
use oat\tao\model\menu\MenuService;
use oat\tao\model\accessControl\data\DataAccessControl;
use oat\tao\model\lock\LockManager;
use oat\tao\helpers\ControllerHelper;

/**
 * The TaoModule is an abstract controller, 
 * the tao children extensions Modules should extends the TaoModule to beneficiate the shared methods.
 * It regroups the methods that can be applied on any extension (the rdf:Class managment for example)
 *
 * @author CRP Henri Tudor - TAO Team - {@link http://www.tao.lu}
 * @license GPLv2  http://www.opensource.org/licenses/gpl-2.0.php
 * @package tao
 
 */
abstract class tao_actions_RdfController extends tao_actions_CommonModule {
    
    /**
     * The Modules access the models throught the service instance
     *
     * @var tao_models_classes_Service
     */
    protected $service = null;
    
    /**
     * @return tao_models_classes_ClassService
     */
    protected function getClassService()
    {
        if (is_null($this->service)) {
            throw new common_exception_Error('No service defined for '.get_called_class());
        }
        return $this->service;
    }
    
	/**
	 * If you want strictly to check if the resource is locked,
	 * you should use LockManager::getImplementation()->isLocked($resource)
	 * Controller level convenience method to check if @resource is being locked, prepare data ans sets view,
	 *
	 * @param core_kernel_classes_Resource $resource
	 * @param $view
	 *
	 * @return boolean
	 */
    protected function isLocked($resource, $view = null){
        
        $lock = LockManager::getImplementation()->getLockData($resource);
        if (!is_null($lock) && $lock->getOwnerId() != common_session_SessionManager::getSession()->getUser()->getIdentifier()) {
         //if (LockManager::getImplementation()->isLocked($resource)) {
             $params = array(
                'id' => $resource->getUri(),
                'topclass-label' => $this->getRootClass()->getLabel()
             );
             if (!is_null($view)) {
                 $params['view'] = $view;
                 $params['ext'] = Context::getInstance()->getExtensionName();
             }
             $this->forward('locked', 'Lock', 'tao', $params);
         }
         return false;
    }

    /**
	 * get the current item class regarding the classUri' request parameter
	 * @return core_kernel_classes_Class the item class
	 */
	protected function getCurrentClass()
	{
		$classUri = tao_helpers_Uri::decode($this->getRequestParameter('classUri'));
		if(is_null($classUri) || empty($classUri)){
			
			$clazz = null;
			$resource = $this->getCurrentInstance();
			foreach($resource->getTypes() as $type){
				$clazz = $type;
				break;
			}
			if(is_null($clazz)){
				throw new Exception("No valid class uri found");
			}
			$returnValue = $clazz;
		}
		else{
			$returnValue = new core_kernel_classes_Class($classUri);
		}
		
		return $returnValue;
	}
	
	/**
	 *  ! Please override me !
	 * get the current instance regarding the uri and classUri in parameter
	 * @return core_kernel_classes_Resource
	 */
	protected function getCurrentInstance()
	{
		$uri = tao_helpers_Uri::decode($this->getRequestParameter('uri'));
		if(is_null($uri) || empty($uri)){
			throw new tao_models_classes_MissingRequestParameterException("uri");
		}
		return new core_kernel_classes_Resource($uri);
	}

	/**
	 * get the main class
	 * @return core_kernel_classes_Classes
	 */
	protected abstract function getRootClass();
	
	public function editClassProperties()
	{
	    $this->defaultData();
	    $clazz = $this->getCurrentClass();
	    
	    if ($this->hasRequestParameter('property_mode')) {
	        $this->setSessionAttribute('property_mode', $this->getRequestParameter('property_mode'));
	    }
	    
	    $myForm = $this->getClassForm($clazz, $this->getRootClass());
	    if ($myForm->isSubmited()) {
	        if ($myForm->isValid()) {
	            if ($clazz instanceof core_kernel_classes_Resource) {
	                $this->setData("selectNode", tao_helpers_Uri::encode($clazz->getUri()));
	            }
	            $this->setData('message', __('%s Class saved', _dh($this->getRootClass()->getLabel())));
	            $this->setData('reload', true);
	        }
	    }
	    $this->setData('formTitle', __('Edit class %s', _dh($clazz->getLabel())));
	    $this->setData('myForm', $myForm->render());
	    $this->setView('form.tpl', 'tao');
	}
	
	/**
	 * Deprecated alias for getClassForm
	 * 
	 * @deprecated
	 */
	protected function editClass(core_kernel_classes_Class $clazz, core_kernel_classes_Resource $resource, core_kernel_classes_Class $topclass = null)
	{
	    return $this->getClassForm($clazz, $resource, $topclass);
	}

	/**
	 * Create an edit form for a class and its property
	 * and handle the submited data on save
	 * 
	 * @param core_kernel_classes_Class    $clazz
	 * @param core_kernel_classes_Resource $resource
	 * @return tao_helpers_form_Form the generated form
	 */
	protected function getClassForm(core_kernel_classes_Class $clazz, core_kernel_classes_Resource $resource, core_kernel_classes_Class $topclass = null)
	{
	
		$propMode = 'simple';
		if($this->hasSessionAttribute('property_mode')){
			$propMode = $this->getSessionAttribute('property_mode');
		}
		
		$options = array('property_mode' => $propMode);
		if(!is_null($topclass)){
			$options['topClazz'] = $topclass->getUri();
		}
		$formContainer = new tao_actions_form_Clazz($clazz, $resource, $options);
		$myForm = $formContainer->getForm();

		if($myForm->isSubmited()){
			if($myForm->isValid()){
                //get the data from parameters
                $data = $this->getRequestParameters();

                // get class data and save them
                if(isset($data['class'])){
                    $classValues = array();
                    foreach($data['class'] as $key => $value){
                        $classKey =  tao_helpers_Uri::decode($key);
                        $classValues[$classKey] =  tao_helpers_Uri::decode($value);
                    }

                    $clazz = $this->service->bindProperties($clazz, $classValues);
                }

                //save all properties values
                if(isset($data['properties'])){
                    foreach($data['properties'] as $i => $propertyValues){
                        $values = array();

                        //save property
                        if($propMode === 'simple') {
                            $propertyMap = tao_helpers_form_GenerisFormFactory::getPropertyMap();
                            $type = $propertyValues['type'];
                            $range = (isset($propertyValues['range']) ? tao_helpers_Uri::decode(trim($propertyValues['range'])) : null);
                            unset($propertyValues['type']);
                            unset($propertyValues['range']);

                            if (isset($propertyMap[$type])) {
                                $values[PROPERTY_WIDGET] = $propertyMap[$type]['widget'];
                            }

                            foreach($propertyValues as $key => $value){
                                $values[tao_helpers_Uri::decode($key)] = tao_helpers_Uri::decode($value);

                            }
                            $property = new core_kernel_classes_Property($values['uri']);
                            unset($values['uri']);
                            $this->service->bindProperties($property, $values);

                            // set the range
                            $property->removePropertyValues(new core_kernel_classes_Property(RDFS_RANGE));
                            if(!empty($range)) {
                                $property->setRange(new core_kernel_classes_Class($range));
                            } elseif (isset($propertyMap[$type]) && !empty($propertyMap[$type]['range'])) {
                                $property->setRange(new core_kernel_classes_Class($propertyMap[$type]['range']));
                            }

                            // set cardinality
                            if(isset($propertyMap[$type]['multiple'])) {
                                $property->setMultiple($propertyMap[$type]['multiple'] == GENERIS_TRUE);
                            }
                        } else {
                            // might break using hard
                            $range = array();
                            foreach($propertyValues as $key => $value){
                                if(is_array($value)){
                                    // set the range
                                    foreach($value as $v){
                                        $range[] = new core_kernel_classes_Class(tao_helpers_Uri::decode($v));
                                    }
                                }
                                else{
                                    $values[tao_helpers_Uri::decode($key)] = tao_helpers_Uri::decode($value);
                                }

                            }
                            $property = new core_kernel_classes_Property($values['uri']);
                            unset($values['uri']);
                            $property->removePropertyValues(new core_kernel_classes_Property(RDFS_RANGE));
                            if(!empty($range)){
                                foreach($range as $r){
                                    $property->setRange($r);
                                }
                            }
                            $this->service->bindProperties($property, $values);
                        }

                        $myForm->removeGroup("property_".tao_helpers_Uri::encode($property->getUri()));

                        //instanciate a property form
                        $propFormClass = 'tao_actions_form_'.ucfirst(strtolower($propMode)).'Property';
                        if(!class_exists($propFormClass)){
                            $propFormClass = 'tao_actions_form_SimpleProperty';
                        }

                        $propFormContainer = new $propFormClass($clazz, $property, array('index' => $i));
                        $propForm = $propFormContainer->getForm();

                        //and get its elements and groups
                        $myForm->setElements(array_merge($myForm->getElements(), $propForm->getElements()));
                        $myForm->setGroups(array_merge($myForm->getGroups(), $propForm->getGroups()));

                        unset($propForm);
                        unset($propFormContainer);

                    }
                }

            }
        }
        return $myForm;
    }
	
	
	
/*
 * Actions
 */
 
	
	/**
	 * Main action
	 * @return void
	 */
	public function index()
	{
		/*
		if($this->getData('reload') == true){
			$this->removeSessionAttribute('uri');
			$this->removeSessionAttribute('classUri');
		}
		*/
		$this->setView('index.tpl');
	}
	
	/**
	 * Renders json data from the current ontology root class.
	 * 
	 * The possible request parameters are the following:
	 * 
	 * * labelFilter: A filter string to be used. The returned hierarchy will be a single root class, with children without class hierarchy.
	 * * uniqueNode: A URI indicating the returned hiearchy will be a single class, with a single children corresponding to the URI.
	 * * browse:
	 * * hideInstances:
	 * * chunk:
	 * * offset:
	 * * limit:
	 * * subclasses:
	 * * classUri:
	 * 
	 * @return void
	 */
	public function getOntologyData()
	{
		if (!tao_helpers_Request::isAjax()) {
            throw new common_exception_IsAjaxAction(__FUNCTION__); 
		}
	
		$options = array(
			'subclasses' => true, 
			'instances' => true, 
			'highlightUri' => '', 
			'labelFilter' => '', 
			'chunk' => false,
			'offset' => 0,
			'limit' => 0
		);
		
		if ($this->hasRequestParameter('filter')) {
			$options['labelFilter'] = $this->getRequestParameter('filter');
		}
		
		if ($this->hasRequestParameter('loadNode')) {
		    $options['uniqueNode'] = $this->getRequestParameter('loadNode');
		}
		
        if ($this->hasRequestParameter("selected")) {
			$options['browse'] = array($this->getRequestParameter("selected"));
		}
		
		if ($this->hasRequestParameter('hideInstances')) {
			if((bool) $this->getRequestParameter('hideInstances')) {
				$options['instances'] = false;
			}
		}
		if ($this->hasRequestParameter('classUri')) {
			$clazz = $this->getCurrentClass();
			$options['chunk'] = !$clazz->equals($this->getRootClass());
		} else {
			$clazz = $this->getRootClass();
		}
		
		if ($this->hasRequestParameter('offset')) {
			$options['offset'] = $this->getRequestParameter('offset');
		}
		
		if ($this->hasRequestParameter('limit')) {
			$options['limit'] = $this->getRequestParameter('limit');
		}
		
		if ($this->hasRequestParameter('subclasses')) {
			$options['subclasses'] = $this->getRequestParameter('subclasses');
		}
		
        //generate the tree from the given parameters	
        $tree = $this->service->toTree($clazz, $options);

        //load the user URI from the session
        $user = common_Session_SessionManager::getSession()->getUser();
 
        //Get the requested section
        $section = MenuService::getSection(
            $this->getRequestParameter('extension'), 
            $this->getRequestParameter('perspective'), 
            $this->getRequestParameter('section')
        );

        //Get the actions from the section and bind them an ActionResolver that helps getting controller/action from action URL.
        $actions = array();
        foreach ($section->getActions() as $index => $action) {
            try{
                $actions[$index] = array(
                    'resolver'  => new ActionResolver($action->getUrl()),
                    'id'      => $action->getId(),
                    'context'   => $action->getContext()
                );
            } catch(\ResolverException $re) {
                common_Logger::d('do not handle permissions for action : ' . $action->getName() . ' ' . $action->getUrl());
            }
        }

        //then compute ACL for each node of the tree
        $treeKeys = array_keys($tree);
        if (is_int($treeKeys[0])) {
            foreach ($tree as $index => $treeNode) {
                $tree[$index] = $this->computePermissions($actions, $user, $treeNode);
            }
        } else { 
            $tree = $this->computePermissions($actions, $user, $tree);
        }

        //expose the tree
        $this->returnJson($tree);
	}

    /**
     * compulte permissions for a node against actions
     * @param array[] $actions the actions data with context, name and the resolver
     * @param User $user the user 
     * @param array $node a tree node
     * @return array the node augmented with permissions
     */
    private function computePermissions($actions, $user, $node){
        if(isset($node['_data'])){
            foreach($actions as $action){
                if($node['type'] == $action['context'] || $action['context'] == 'resource') {
                    $resolver = $action['resolver'];
                    try{
                        if($node['type'] == 'class'){
                            $data = array('classUri' => $node['_data']['uri']);
                        } else {
                            $data = $node['_data'];
                        }
                        $data['id'] = $node['attributes']['data-uri'];
                        $required = array_keys(ControllerHelper::getRequiredRights($resolver->getController(), $resolver->getAction()));
                        if (count(array_diff($required, array_keys($data))) == 0) {
                            $node['permissions'][$action['id']] = AclProxy::hasAccess($user, $resolver->getController(), $resolver->getAction(), $data);
                        } else {
                            common_Logger::d('Unable to determine access to '.$action['id'], 'ACL');
                        }

                    //@todo should be a checked exception!
                    } catch(Exception $e){
                        common_Logger::w('Unable to resolve permission for action ' . $action['id'] . ' : ' . $e->getMessage() );
                    }
                }
            }
        }
        if(isset($node['children'])){
            foreach($node['children'] as $index => $child){
                $node['children'][$index] = $this->computePermissions($actions, $user, $child);    
            }
        }
        return $node;
    }
	
	/**
	 * Add an instance of the selected class
	 * @return void
	 */
	public function addInstance()
	{
		if(!tao_helpers_Request::isAjax()){
			throw new Exception("wrong request mode");
		}
		
		$response = array();
		
		$clazz = new core_kernel_classes_Class($this->getRequestParameter('id'));
		$label = $this->service->createUniqueLabel($clazz);
		
		$instance = $this->service->createInstance($clazz, $label);
		
		if(!is_null($instance) && $instance instanceof core_kernel_classes_Resource){
			$response = array(
				'label'	=> $instance->getLabel(),
				'uri' 	=> $instance->getUri()
			);
		}
		$this->returnJson($response);
	}
	
	/**
	 * Add a subclass to the currently selected class
	 * @throws Exception
	 */
	public function addSubClass()
	{
	    if(!tao_helpers_Request::isAjax()){
	        throw new Exception("wrong request mode");
	    }
	    $parent = new core_kernel_classes_Class($this->getRequestParameter('id'));
	    $clazz = $this->service->createSubClass($parent);
	    if(!is_null($clazz) && $clazz instanceof core_kernel_classes_Class){
	        echo json_encode(array(
	            'label'	=> $clazz->getLabel(),
	            'uri' 	=> tao_helpers_Uri::encode($clazz->getUri())
	        ));
	    }
	}
	
	/**
	 * Add an instance of the selected class
	 * @return void
	 */
	public function addInstanceForm()
	{
		if(!tao_helpers_Request::isAjax()){
			throw new Exception("wrong request mode");
		}
		
		$clazz = $this->getCurrentClass();
		$formContainer = new tao_actions_form_CreateInstance(array($clazz), array());
		$myForm = $formContainer->getForm();
		
		if($myForm->isSubmited()){
			if($myForm->isValid()){
				
				$properties = $myForm->getValues();
				$instance = $this->createInstance(array($clazz), $properties);
				
				$this->setData('message', __($instance->getLabel().' created'));
				$this->setData('reload', true);
				//return $this->redirect(_url('editInstance', null, null, array('uri' => $instance)));
			}
		}
		
		$this->setData('formTitle', __('Create instance of ').$clazz->getLabel());
		$this->setData('myForm', $myForm->render());
	
		$this->setView('form.tpl', 'tao');
	}
	
	/**
	 * creates the instance
	 * 
	 * @param array $classes
	 * @param array $properties
	 * @return core_kernel_classes_Resource
	 */
	protected function createInstance($classes, $properties) {
		$first = array_shift($classes);
		$instance = $first->createInstanceWithProperties($properties);
		foreach ($classes as $class) {
			$instance = new core_kernel_classes_Resource('');
			$instance->setType($class);
		}
		return $instance;
	}
	
	/**
	 * Edit property instance
	 * @return void
	 */
	public function editPropertyInstance()
	{
		if(!$this->hasRequestParameter('ownerUri') || !$this->hasRequestParameter('ownerClassUri')
			|| !$this->hasRequestParameter('propertyUri')){
			var_dump('variables missing');
		} 
		else{
			
			$ownerClassUri = tao_helpers_Uri::decode($this->getRequestParameter('ownerClassUri'));
			$ownerUri = tao_helpers_Uri::decode($this->getRequestParameter('ownerUri'));
			$propertyUri = tao_helpers_Uri::decode($this->getRequestParameter('propertyUri'));
			
			$ownerInstance = new core_kernel_classes_Resource($ownerUri);
			$ownerClass = new core_kernel_classes_Class($ownerClassUri);
			$property = new core_kernel_classes_Property($propertyUri);
			$propertyRange = $property->getRange();
			
			// If the file does not exist, create it
			$instance = $ownerInstance->getOnePropertyValue($property);
			if(is_null($instance)){
				$instance = $propertyRange->createInstance();
				$ownerInstance->setPropertyValue($property, $instance->getUri());
			}
			
			$formContainer = new tao_actions_form_Instance($propertyRange, $instance);
			$myForm = $formContainer->getForm();
			
			// Add hidden elements to the form
			$ownerClassUriElt = tao_helpers_form_FormFactory::getElement("ownerClassUri", "Hidden");
			$ownerClassUriElt->setValue(tao_helpers_Uri::encode($ownerClassUri));
			$myForm->addElement($ownerClassUriElt);
			
			$ownerUriElt = tao_helpers_form_FormFactory::getElement("ownerUri", "Hidden");
			$ownerUriElt->setValue(tao_helpers_Uri::encode($ownerUri));
			$myForm->addElement($ownerUriElt);
			
			$propertyUriElt = tao_helpers_form_FormFactory::getElement("propertyUri", "Hidden");
			$propertyUriElt->setValue(tao_helpers_Uri::encode($propertyUri));
			$myForm->addElement($propertyUriElt);
			
			//add an hidden elt for the instance Uri
			//usefull to render the revert action
			$instanceUriElt = tao_helpers_form_FormFactory::getElement('uri', 'Hidden');
			$instanceUriElt->setValue(tao_helpers_Uri::encode($ownerInstance->getUri()));
			$myForm->addElement($instanceUriElt);
			
			if($myForm->isSubmited()){
				if($myForm->isValid()){
					
					$properties = $myForm->getValues();
					$versionedContentInstance = $this->service->bindProperties($instance, $properties);
					
					$this->setData('message', __($propertyRange->getLabel().' saved'));
					$this->setData('reload', true);
				}
			}
			
			$this->setData('formTitle', __('Manage content of the property ').$property->getLabel().__(' of the instance ').$ownerInstance->getLabel());
			$this->setData('myForm', $myForm->render());
		
			$this->setView('form.tpl');
		}
		
	}
	
	public function editInstance() {
		$clazz = $this->getCurrentClass();
		$instance = $this->getCurrentInstance();
		$myFormContainer = new tao_actions_form_Instance($clazz, $instance);
		
		$myForm = $myFormContainer->getForm();
		if($myForm->isSubmited()){
			if($myForm->isValid()){
				
				$values = $myForm->getValues();
				// save properties
				$binder = new tao_models_classes_dataBinding_GenerisFormDataBinder($instance);
				$instance = $binder->bind($values);
				$message = __('Instance saved');
				
				$this->setData('message',$message);
				$this->setData('reload', true);
			}
		}

		$this->setData('formTitle', __('Edit Instance'));
		$this->setData('myForm', $myForm->render());
		$this->setView('form.tpl', 'tao');
	}
	
	/**
	 * Edit a versioned file
	 * @todo refactor
	 */
	public function editVersionedFile()
	{
		// in need of refactoring
		throw new common_exception_Error('Functionality currently disabled');
		if(!$this->hasRequestParameter('uri') || !$this->hasRequestParameter('propertyUri')){
			
			throw new Exception('Required variables missing');
			
		}else{
			
			$ownerUri = tao_helpers_Uri::decode($this->getRequestParameter('uri'));
			$propertyUri = tao_helpers_Uri::decode($this->getRequestParameter('propertyUri'));
			
			$ownerInstance = new core_kernel_classes_Resource($ownerUri);
			$property = new core_kernel_classes_Property($propertyUri);
			$propertyRange = $property->getRange();
			
			//get the versioned file resource
			$versionedFileResource = $ownerInstance->getOnePropertyValue($property);
			
			//if it does not exist already, create a new versioned file resource
			if(is_null($versionedFileResource)){
				//if the file resource does not exist, create it
				$versionedFileResource = $propertyRange->createInstance();
				$ownerInstance->setPropertyValue($property, $versionedFileResource->getUri());
			}
			$versionedFile = new core_kernel_versioning_File($versionedFileResource->getUri());
			
			//create the form
			$formContainer = new tao_actions_form_VersionedFile(null
				, array(
					'instanceUri' => $versionedFile->getUri(),
					'ownerUri' => $ownerInstance->getUri(),
					'propertyUri' => $propertyUri
				)
			);
			$myForm = $formContainer->getForm();
			
			//if the form was sent successfully
			if($myForm->isSubmited()){
				
				if($myForm->isValid()){
					
					// Extract data from form
					$data = $myForm->getValues();
					
					// Extracted values
					$content = '';
					$delete = isset($data['file_delete']) && $data['file_delete'] == '1'?true:false;
					$message = isset($data['commit_message'])?$data['commit_message']:'';
					$fileName = $data[PROPERTY_FILE_FILENAME];
					$filePath = $data[PROPERTY_FILE_FILEPATH];
					$repositoryUri = $data[PROPERTY_FILE_FILESYSTEM];
					$version = isset($data['file_version']) ? $data['file_version'] : 0;
					
					//get the content
					if(isset($data['file_import']['uploaded_file'])){
						if(file_exists($data['file_import']['uploaded_file'])){
							$content = file_get_contents($data['file_import']['uploaded_file']);
						}
						else{
							throw new Exception(__('the file was not uploaded successfully'));
						}
					}
					
					//the file is already versioned
					if($versionedFile->isVersioned()){
						
						if($delete){
							
							$versionedFile->delete();//no need to commit here (already done in the funciton implementation
							$ownerInstance->removePropertyValues($property);
							
						}else{
							
							if ($version) {//version = [1..n]
								//revert to a version
								$topRevision = count($myForm->getElement('file_version')->getOptions());
								if ($version < $topRevision) {
									$versionedFile->revert($version, empty($message)?'Revert to TAO version '.$version : $message);
								}
							}

							//a new content was sent
							if (!empty($content)) {
								$versionedFile->setContent($content);
							}
							
							//commit the file
							$versionedFile->commit($message);
						}
						
					} 
					//the file is already versioned
					else{
						//create the versioned file
						$versionedFile = core_kernel_versioning_File::createVersioned(
							$fileName,
							$filePath,
							new core_kernel_versioning_Repository($repositoryUri),
							$versionedFile->getUri()
					    );
					    					    
						//a content was sent
						if(!empty($content)){
							$versionedFile->setContent($content);
						}
						
						//add the file to the repository
						$versionedFile->add();
						
						//commit the file
						$versionedFile->commit($message);
					}
					
					$this->setData('message', __($propertyRange->getLabel().' saved'));
					$this->setData('reload', true);
					
					//reload the form to take in account the changes
					$ctx = Context::getInstance();
					$this->redirect(_url($ctx->getActionName(), $ctx->getModuleName(), $ctx->getExtensionName(), array(
						'uri'			=> tao_helpers_Uri::encode($ownerUri),
						'propertyUri'	=> tao_helpers_Uri::encode($propertyUri)
					)));
				}
			}
			
			$this->setData('formTitle', __('Manage the versioned content : ').$ownerInstance->getLabel().' > '.$property->getLabel());
			$this->setData('myForm', $myForm->render());
			
			$this->setView('form/versioned_file.tpl', 'tao');
		}
		
	}
	
	/**
	 * Duplicate the current instance
	 * render a JSON response
	 * @return void
	 */
	public function cloneInstance()
	{
		if(!tao_helpers_Request::isAjax()){
			throw new Exception("wrong request mode");
		}
		
		$clone = $this->service->cloneInstance($this->getCurrentInstance(), $this->getCurrentClass());
		if(!is_null($clone)){
			echo json_encode(array(
				'label'	=> $clone->getLabel(),
				'uri' 	=> tao_helpers_Uri::encode($clone->getUri())
			));
		}
	}
	
	/**
	 * Move an instance from a class to another
	 * @return void
	 */
	public function moveInstance()
	{
	    $response = array();	
		if($this->hasRequestParameter('destinationClassUri') && $this->hasRequestParameter('uri')){
            $instance = new core_kernel_classes_Resource(tao_helpers_Uri::decode($this->getRequestParameter('uri')));
            $clazz = $this->service->getClass($instance);
			$destinationUri = tao_helpers_Uri::decode($this->getRequestParameter('destinationClassUri'));

			if(!empty($destinationUri) && $destinationUri != $clazz->getUri()){
				$destinationClass = new core_kernel_classes_Class($destinationUri);
				
				$confirmed = $this->getRequestParameter('confirmed');
				if(empty($confirmed) || $confirmed == 'false' || $confirmed ===  false){
					
					$diff = $this->service->getPropertyDiff($clazz, $destinationClass);
					if(count($diff) > 0){
					    return $this->returnJson(array(
							'status'	=> 'diff',
							'data'		=> $diff
						));
					}
				}  
				
                $status = $this->service->changeClass($instance, $destinationClass);
                $response = array('status'	=> $status);
			}
		}
        $this->returnJson($response);
	}
	
	/**
	 * Render the  form to translate a Resource instance
	 * @return void
	 */
	public function translateInstance()
	{
		
		$instance = $this->getCurrentInstance();
		
		$formContainer = new tao_actions_form_Translate($this->getCurrentClass(), $instance);
		$myForm = $formContainer->getForm();
		
		if($this->hasRequestParameter('target_lang')){
			
			$targetLang = $this->getRequestParameter('target_lang');
		
			if(in_array($targetLang, tao_helpers_I18n::getAvailableLangsByUsage(new core_kernel_classes_Resource(INSTANCE_LANGUAGE_USAGE_DATA)))){
				$langElt = $myForm->getElement('translate_lang');
				$langElt->setValue($targetLang);
				$langElt->setAttribute('readonly', 'true');
				
				$trData = $this->service->getTranslatedProperties($instance, $targetLang);
				foreach($trData as $key => $value){
					$element = $myForm->getElement(tao_helpers_Uri::encode($key));
					if(!is_null($element)){
						$element->setValue($value);
					}
				}
			}
		}
		
        if($myForm->isSubmited()){
            if($myForm->isValid()){

                $values = $myForm->getValues();
                if(isset($values['translate_lang'])){
                    $datalang = common_session_SessionManager::getSession()->getDataLanguage();
                    $lang = $values['translate_lang'];

                    $translated = 0;
                    foreach($values as $key => $value){
						if(preg_match("/^http/", $key)){
							$value = trim($value);
							$property = new core_kernel_classes_Property($key);
							if(empty($value)){
								if($datalang != $lang && $lang != ''){
									$instance->removePropertyValueByLg($property, $lang);
								}
							}
							else if($instance->editPropertyValueByLg($property, $value, $lang)){
								$translated++;
							}
						}
					}
					if($translated > 0){
						$this->setData('message', __('Translation saved'));
					}
				}
			}
		}
		
		$this->setData('myForm', $myForm->render());
		$this->setData('formTitle', __('Translate'));
		$this->setView('form.tpl', 'tao');
	}
	
	/**
	 * load the translated data of an instance regarding the given lang 
	 * @return void
	 */
	public function getTranslatedData()
	{
		if(!tao_helpers_Request::isAjax()){
			throw new Exception("wrong request mode");
		}
		$data = array();
		if($this->hasRequestParameter('lang')){
			$data = tao_helpers_Uri::encodeArray(
				$this->service->getTranslatedProperties(
					$this->getCurrentInstance(),
					$this->getRequestParameter('lang') 
				), 
				tao_helpers_Uri::ENCODE_ARRAY_KEYS);
			}
		echo json_encode($data);
	}
	
	/**
	 * 
	 * Search form may be extends by extension to modify search form
	 * 
	 * @author Lionel Lecaque, lionel@taotesting.com
	 * @param core_kernel_classes_Class $clazz
	 * @return tao_actions_form_Search
	 */
	protected function getSearchForm($clazz){
	    return new tao_actions_form_Search($clazz, null, array('recursive' => true));
	}
	
	
	/**
	 * search the instances of an ontology
	 * @return 
	 */
	public function search()
	{
		$found = false;
		
		try{
			$clazz = $this->getCurrentClass();
		}
		catch(Exception $e){
		    common_Logger::i('Search : could not find current class switch to root class');
			$clazz = $this->getRootClass();
		}
        
		$formContainer = $this->getSearchForm($clazz);
		$myForm = $formContainer->getForm();
		if (tao_helpers_Context::check('STANDALONE_MODE')) {
			$standAloneElt = tao_helpers_form_FormFactory::getElement('standalone', 'Hidden');
			$standAloneElt->setValue(true);
			$myForm->addElement($standAloneElt);
		}
		
		
		if($myForm->isSubmited()){
			if($myForm->isValid()){

				$filters = $myForm->getValues('filters');
                $model = array();
				foreach($filters as $propUri => $filter){
					if(preg_match("/^http/", $propUri) && !empty($filter)){
						$property = new core_kernel_classes_Property($propUri);
                        $model[$property->getUri()] = array(
                            'id' => $property->getUri(),
                            'label' => $property->getLabel(),
                            'sortable' => true
                        );
					}
					else{
						unset($filters[$propUri]);
					}
				}
				$clazz = new core_kernel_classes_Class($myForm->getValue('clazzUri'));
				if(!array_key_exists(RDFS_LABEL, $model)){
                    $labelProp = new core_kernel_classes_Property(RDFS_LABEL);
					$model = array_merge(array( 
                        $labelProp->getUri() => array(
                            'id' => $labelProp->getUri(),
                            'label' => $labelProp->getLabel(),
                            'sortable' => true
                    )), $model);
				}


  				$params = $myForm->getValues('params');
                if(!isset($params['recursive'])){
                    // 0 => Current class + sub-classes, 10 => Current class only
                    $params['recursive'] = true;
                } else {
                    $params['recursive'] = false;
                }
				$params['like'] = false;
                
                $this->returnJson(array(
                    'url'  => _url('searchResults', null, null, array('classUri'  => $clazz->getUri())),
                    'params'    => $params,
				    'model'     => $model,
				    'filters'   => $filters,
                    'result'    => true
                ));
                return;
				//$instances = $this->service->searchInstances($filters, $clazz, $params);
				
				//if(count($instances) > 0 ){
					//$found = array();
					//$index = 1;
					//foreach($instances as $instance){
						
						//$instanceProperties = array();
						//foreach($properties as $i => $property){
							//$value = '';
							//$propertyValues = $instance->getPropertyValuesCollection($property);
							//foreach($propertyValues->getIterator() as $j => $propertyValue){
								//if($propertyValue instanceof core_kernel_classes_Literal){
									//$value .= (string) $propertyValue;
								//}
								//if($propertyValue instanceof core_kernel_classes_Resource){
									//$value .= $propertyValue->getLabel();
								//}
								//if($j < $propertyValues->count()){
									//$value .= "<br />";
								//}
							//}
							//$instanceProperties[$i] = $value;
						//}
						//$found[$index]['uri'] = tao_helpers_Uri::encode($instance->getUri());
						//$found[$index]['properties'] = $instanceProperties;
						//$index++;
					//}
				//}
			}
			//if(tao_helpers_Context::check('STANDALONE_MODE')){
				//$this->setData('openAction', 'alert');
			//}
			//$this->setData('foundNumber', count($found));
			//$this->setData('found', $found);
		}
		
		
		$this->setData('myForm', $myForm->render());
		$this->setData('formTitle', __('Search'));
		$this->setView('form/search.tpl', 'tao');
	}

    public function searchResults(){
        
		$page =  (int)$this->getRequestParameter('page');
		$limit = (int)$this->getRequestParameter('rows');
		$order = $this->getRequestParameter('sortby');
		$sord = $this->getRequestParameter('sortorder');
		$start = $limit * $page - $limit;

        $params = $this->hasRequestParameter('params') ? $this->getRequestParameter('params') : array();
        $filters = $this->hasRequestParameter('filters') ? $this->getRequestParameter('filters') : array();
        
	    if($order == 'id'){
            $order = RDFS_LABEL;
        }	
		$options = array_merge(array(
            'order' 	=> $order,
            'orderdir'	=> strtoupper($sord),
            'offset'    => $start,
            'limit'		=> $limit
		), $params);
	
        $clazz = $this->getCurrentClass();
        $instances = $clazz->searchInstances($filters, $options);
        $counti = count($clazz->searchInstances($filters, $params));

        $response = new StdClass();
        if(count($instances) > 0 ){
            $properties = array();
            foreach(array_keys($filters) as $propUri){
                $properties[$propUri] = new core_kernel_classes_Property($propUri);
            }

            if(array_key_exists(RDFS_LABEL, $properties)){
                unset($instanceProperties[RDFS_LABEL]);
            }

            foreach($instances as $instance){
                
                $instanceProperties = array(
                    'id' => $instance->getUri(),
                    RDFS_LABEL => $instance->getLabel() 

                );
                foreach($properties as $i => $property){
                    $value = '';
                    $propertyValues = $instance->getPropertyValuesCollection($property);
                    foreach($propertyValues->getIterator() as $propertyValue){
                        if($propertyValue instanceof core_kernel_classes_Literal){
                            $value .= (string) $propertyValue;
                        }
                        if($propertyValue instanceof core_kernel_classes_Resource){
                            $value .= $propertyValue->getLabel();
                        }
                    }
                    $instanceProperties[$i] = $value;
                }

                $response->data[] = $instanceProperties; 
            }
        }
		$response->page = floor($start / $limit) + 1;
		$response->total = ceil($counti / $limit);
		$response->records = count($instances);

		$this->returnJson($response, 200);

    }

	/**
	 * filter class' instances
	 */
	public function filter()
	{
		//get class to filter
		try{
			$clazz = $this->getCurrentClass();
		}
		catch(Exception $e){
			$clazz = $this->getRootClass();
		}
		$this->setData('clazz', $clazz);
		
		//get properties to filter on
		if($this->hasRequestParameter('properties')){
			$properties = $this->getRequestParameter('properties');
		}
		else{
			$properties = tao_helpers_form_GenerisFormFactory::getClassProperties($clazz);
		}
		// Remove item content property
		// Specific case
		if (array_key_exists(TAO_ITEM_CONTENT_PROPERTY, $properties)){
			unset ($properties[TAO_ITEM_CONTENT_PROPERTY]);
		}
		$this->setData('properties', $properties);
		$this->setData('formTitle', __('Filter'));
		$this->setView('form/filter.tpl', 'tao');
	}
	
	/**
	 * Generis API searchInstances function as an action
	 * Developed for the facet based filter ...
	 * @todo Is it a dangerous action ?
	 */
	public function searchInstances()
	{
		$returnValue = array ();
		$filter = array ();
		$properties = array ();
		
		if(!tao_helpers_Request::isAjax()){
			//throw new Exception("wrong request mode");
		}
		
		// Get the class paramater
		if($this->hasRequestParameter('classUri')){
			$clazz = $this->getCurrentClass();
		} else {
			$clazz = $this->getRootClass();
		}
		
		// Get filter parameter
		if ($this->hasRequestParameter('filter')) {
			$filter = $this->getFilterState('filter');
		}
		
		$properties = tao_helpers_form_GenerisFormFactory::getClassProperties($clazz);
		// ADD Label property
		if (!array_key_exists(RDFS_LABEL, $properties)){
			$new_properties = array();
			$new_properties[RDFS_LABEL] = new core_kernel_classes_Property(RDFS_LABEL);
			$properties = array_merge($new_properties, $properties);
		}
		// Remove item content property
		if (array_key_exists(TAO_ITEM_CONTENT_PROPERTY, $properties)){
			unset ($properties[TAO_ITEM_CONTENT_PROPERTY]);
		}
		
		$instances = $this->service->searchInstances($filter, $clazz, array ('recursive'=>true));
		$index = 0;
		foreach ($instances as $instance){
			$returnValue [$index]['uri'] = $instance->getUri();
			$formatedProperties = array ();
			foreach ($properties as $property){
				//$formatedProperties[] = (string)$instance->getOnePropertyValue (new core_kernel_classes_Property($property));
				$value = $instance->getOnePropertyValue($property);
				if ($value instanceof core_kernel_classes_Resource) {
					$value = $value->getLabel();
				}else{
					$value = (string) $value;
				}
				$formatedProperties[] = $value;
			}
			$returnValue [$index]['properties'] = (Object) $formatedProperties;
			$index++;
		}
		
		echo json_encode ($returnValue);
	}

	/**
	 * Get property values for a sub set of filtered instances
	 * @param {RequestParameter|string} propertyUri Uri of the target property
	 * @param {RequestParameter|string} classUri Uri of the target class
	 * @param {RequestParameter|array} filter Array of propertyUri/propertyValue used to filter instances of the target class
	 * @param {RequestParameter|array} filterNodesOptions Array of options used by other filter nodes
	 * @return {array} formated for tree
	 */
	public function getFilteredInstancesPropertiesValues()
	{
		$data = array();
		// The filter nodes options
		$filterNodesOptions = array();
		// The filter
		$filter = array();
        // Filter itself ?
        $filterItself = $this->hasRequestParameter('filterItself') ? ($this->getRequestParameter('filterItself')=='false'?false:true) : false;
        
		if(!tao_helpers_Request::isAjax()){
			throw new Exception("wrong request mode");
		}
		
		
		// Get the target property
		if($this->hasRequestParameter('propertyUri')){
            $propertyUri = $this->getRequestParameter('propertyUri');
		} else {
            $propertyUri = RDFS_LABEL;
		}
		$property = new core_kernel_classes_Property($propertyUri);
		
		// Get the class paramater
		if($this->hasRequestParameter('classUri')){
			$clazz = $this->getCurrentClass();
		}
		else{
			$clazz = $this->getRootClass();
		}
		
		// Get filter nodes parameters
		if($this->hasRequestParameter('filterNodesOptions')){
			$filterNodesOptions = $this->getRequestParameter('filterNodesOptions');
		}
		// Get filter parameter
		if($this->hasRequestParameter('filter')){
			$filter = $this->getFilterState('filter');
		}
		
		// Get used property values for a class functions of the given filter
		$propertyValues = $clazz->getInstancesPropertyValues($property, $filter, array("distinct"=>true, "recursive"=>true));
		
		$propertyValuesFormated = array ();
		foreach($propertyValues as $propertyValue){
			$value = "";
			$id = "";
			if ($propertyValue instanceof core_kernel_classes_Resource){
				$value = $propertyValue->getLabel();
				$id = tao_helpers_Uri::encode($propertyValue->getUri());
			} else {
				$value = (string) $propertyValue;
				$id = $value;
			}
			$propertyValueFormated = array(
				'data' 	=> $value,
				'type'	=> 'instance',
				'attributes' => array(
					'id' => $id,
					'class' => 'node-instance'
				)
			);
			$propertyValuesFormated[] = $propertyValueFormated;
		}
		
		$data = array(
			'data' 	=> $this->hasRequestParameter('rootNodeName') ? $this->getRequestParameter('rootNodeName') : tao_helpers_Display::textCutter($property->getLabel(), 16),
			'type'	=> 'class',
			'count' => count($propertyValuesFormated),
			'attributes' => array(
				'id' => tao_helpers_Uri::encode($property->getUri()),
				'class' => 'node-class'
			),
			'children' => $propertyValuesFormated
 		);
		
		echo json_encode($data);
	}

	/**
	 * returns a FilterState object from the parameters
	 *
	 * @param string $identifier
	 * @throws common_Exception
	 * @return \FilterState
	 */
	protected function getFilterState($identifier) {
		if (!$this->hasRequestParameter($identifier)) {
			throw new common_Exception('Missing parameter "'.$identifier.'" for getFilterState()');
		}
		$coded = $this->getRequestParameter($identifier);
		$state = array();
		if (is_array($coded)) {
	    	foreach ($coded as $key => $values) {
	    		foreach ($values as $k => $v) {
	    			$state[tao_helpers_Uri::decode($key)][$k] = tao_helpers_Uri::decode($v);
	    		}
	    	}
		}
		return $state;
	}

	/**
	 * Render the add property sub form.
	 * @throws Exception
	 * @return void
	 */
	public function addClassProperty()
	{
		if(!tao_helpers_Request::isAjax()){
			throw new Exception("wrong request mode");
		}
		
		$clazz = $this->getCurrentClass();
		
		if($this->hasRequestParameter('index')){
			$index = $this->getRequestParameter('index');
		}
		else{
			$index = count($clazz->getProperties(false)) + 1;
		}
		
		$propMode = 'simple';
		if($this->hasSessionAttribute('property_mode')){
			$propMode = $this->getSessionAttribute('property_mode');
		}
		
		//instanciate a property form
		$propFormClass = 'tao_actions_form_'.ucfirst(strtolower($propMode)).'Property';
		if(!class_exists($propFormClass)){
			$propFormClass = 'tao_actions_form_SimpleProperty';
		}
		
		$propFormContainer = new $propFormClass($clazz, $clazz->createProperty('Property_'.$index), array('index' => $index));
		$myForm = $propFormContainer->getForm();
		
		$this->setData('data', $myForm->renderElements());
		$this->setView('blank.tpl', 'tao');
	}


	/**
	 * Render the add property sub form.
	 * @throws Exception
	 * @return void
	 */
	public function removeClassProperty()
	{
		$success = false;
		if(!tao_helpers_Request::isAjax()){
			throw new Exception("wrong request mode");
		}

		//delete property mode
		foreach($this->getCurrentClass()->getProperties() as $classProperty){
			if($classProperty->getUri() == tao_helpers_Uri::decode($this->getRequestParameter('uri'))){

				//delete property and the existing values of this property
				if($classProperty->delete(true)){
					$success = true;
					break;
				}
			}
		}

		if(!$success){
			throw new Exception("Unable to find property");
		}
	}

	/**
	 * delete an instance or a class
	 * called via ajax
	 */
	public function delete()
	{
		if(!tao_helpers_Request::isAjax()){
			throw new Exception("wrong request mode");
		}
		
        if($this->hasRequestParameter('uri')) {
            return $this->forward('deleteResource', null, null, (array('id' => tao_helpers_Uri::decode($this->getRequestParameter('uri')))));
        } elseif ($this->hasRequestParameter('classUri')) {
            return $this->forward('deleteClass', null, null, (array('id' => tao_helpers_Uri::decode($this->getRequestParameter('classUri')))));
        } else {
            throw new common_exception_MissingParameter();
        }
	}
	
    /**
     * Generic class deletion action
     * 
     * @throws Exception
     */
    public function deleteResource()
    {
        if(!tao_helpers_Request::isAjax() || !$this->hasRequestParameter('id')){
            throw new Exception("wrong request mode");
        }
        $resource = new core_kernel_classes_Resource($this->getRequestParameter('id'));
        $deleted = $this->getClassService()->deleteResource($resource);
        return $this->returnJson(array(
            'deleted' => $deleted
        ));
    }

	/**
	 * Generic class deletion action
	 * 
	 * @throws Exception
	 */
	public function deleteClass()
	{
	    if(!tao_helpers_Request::isAjax() || !$this->hasRequestParameter('id')){
	        throw new Exception("wrong request mode");
	    }
	    $clazz = new core_kernel_classes_Class($this->getRequestParameter('id'));
	    if ($this->getRootClass()->equals($clazz)) {
	        $success = false;
	        $msg = __('You cannot delete the root node');
	    } else {
	        $label = $clazz->getLabel();
            $success = $this->getClassService()->deleteClass($clazz);
            $msg = $success ? __('%s has been deleted', $label) : __('Unable to delete %s', $label);
	    }
	    return $this->returnJson(array(
	        'deleted' => $success,
	        'msg' => $msg
	    ));
	}

	/**
	 * Test whenever the current user has "WRITE" access to the specified id
	 *
	 * @param string $resourceId
	 * @return boolean
	 */
	protected function hasWriteAccess($resourceId) {
	    $user = common_session_SessionManager::getSession()->getUser();
	    return DataAccessControl::hasPrivileges($user, array($resourceId => 'WRITE'));
	}
}