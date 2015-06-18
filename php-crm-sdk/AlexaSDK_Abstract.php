<?php
/**
 * AlexaSDK_Abstract.php
 * 
 * @author alexacrm.com.au
 * @version 1.0
 * @package AlexaSDK
 */

/**
 * This interface that contains constants for new entity record creation and max record to retrieve
 */
interface AlexaSDK_Interface {
	/** 
         * Default GUID for "not known" or new Entities 
         * 
         * @var String Parameter based on Dynamics CRM guid format
         */
	const EmptyGUID = '00000000-0000-0000-0000-000000000000';
	/** 
         * Maximum number of records in a single RetrieveMultiple 
         * 
         * @var Integer
         */
	const MAX_CRM_RECORDS = 5000;
}


/**
 * Base class for most SDK classes, contains common methods and subsclasses includes
 */
abstract class AlexaSDK_Abstract implements AlexaSDK_Interface {
        /**
         * Internal details 
         * 
         * @var Boolean $debugMode if TRUE will outputs debug information, default FALSE
         */
	protected static $debugMode = FALSE;
        
        /**
         * Limits the maximum execution time
         * 
         * @var Integer 
         */
        protected static $timeLimit = 240;
        
        /**
         * Classes prefix for autoload
         * 
         * @var String
         */
        protected static $classPrefix = "AlexaSDK";
        
         /** 
          * List of recognised SOAP Faults that can be returned by MS Dynamics CRM 
          * 
          * @var Array $SOAPFaultActions List of SOAP Fault actions that returned from Dyanmics CRM
          */
	public static $SOAPFaultActions = Array(
                    'http://www.w3.org/2005/08/addressing/soap/fault',
                    'http://schemas.microsoft.com/net/2005/12/windowscommunicationfoundation/dispatcher/fault',
                    'http://schemas.microsoft.com/xrm/2011/Contracts/Services/IOrganizationService/ExecuteOrganizationServiceFaultFault',
                    'http://schemas.microsoft.com/xrm/2011/Contracts/Services/IOrganizationService/CreateOrganizationServiceFaultFault',
                    'http://schemas.microsoft.com/xrm/2011/Contracts/Services/IOrganizationService/RetrieveOrganizationServiceFaultFault',
                    'http://schemas.microsoft.com/xrm/2011/Contracts/Services/IOrganizationService/UpdateOrganizationServiceFaultFault',
                    'http://schemas.microsoft.com/xrm/2011/Contracts/Services/IOrganizationService/DeleteOrganizationServiceFaultFault',
                    'http://schemas.microsoft.com/xrm/2011/Contracts/Services/IOrganizationService/RetrieveMultipleOrganizationServiceFaultFault',
                    );

        
        /**
	 * Implementation of Class Autoloader
	 * See http://www.php.net/manual/en/function.spl-autoload-register.php
	 *
	 * @param String $className the name of the Class to load
	 */
	public static function loadClass($className){
		/* Only load classes that don't exist, and are part of DynamicsCRM2011 */
		if ((class_exists($className)) || (strpos($className, self::$classPrefix) === false)) {
			return false;
		}
	
		/* Work out the filename of the Class to be loaded. */
		$classFilePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . $className . '.class.php';
	
		/* Only try to load files that actually exist and can be read */
		if ((file_exists($classFilePath) === false) || (is_readable($classFilePath) === false)) {
			return false;
		}
	
		/* Don't load it if it's already been loaded */
		require_once $classFilePath;
	}
        
        
        /**
	 * Utility function to strip any Namespace from an XML attribute value
         * 
	 * @param String $attributeValue attribute value that contains namespace attribute
         * 
	 * @return String Attribute Value without the Namespace
         * 
         * @ignore
	 */
	protected static function stripNS($attributeValue) {
		return preg_replace('/[a-zA-Z]+:([a-zA-Z]+)/', '$1', $attributeValue);
	}
	
	/**
	 * Get the current time, as required in XML format
         * 
	 * @ignore
	 */
	protected static function getCurrentTime() {
		return substr(gmdate('c'),0,-6) . ".00";
	}
	
	/**
	 * Get an appropriate expiry time for the XML requests, as required in XML format
         * 
	 * @ignore
	 */
	protected static function getExpiryTime() {
		return substr(gmdate('c', strtotime('+5 minutes')),0,-6) . ".00";
	}
        
        /**
	 * Get an uuid for the XML requests message id, as required in XML format
         * 
	 * @ignore
	 */
        protected static function getUuid($namespace = '') {
            static $guid = '';
            $uid = uniqid("", true);
            $data = $namespace;
            $data .= $_SERVER['REQUEST_TIME'];
            $data .= $_SERVER['HTTP_USER_AGENT'];
            $data .= $_SERVER['REMOTE_ADDR'];
            $data .= $_SERVER['REMOTE_PORT'];
            $hash = strtoupper(hash('ripemd128', $uid . $guid . md5($data)));
            $guid = substr($hash, 0, 8) .
                    '-' .
                    substr($hash, 8, 4) .
                    '-' .
                    substr($hash, 12, 4) .
                    '-' .
                    substr($hash, 16, 4) .
                    '-' .
                    substr($hash, 20, 12);
            return $guid;
        }
	
	/**
	 * Enable or Disable DEBUG for the Class
         * 
         * @param Boolean $_debugMode
         * 
	 */
	public static function setDebug($_debugMode) {
		self::$debugMode = $_debugMode;
	}
        
        /**
	 * Set the maximum script execution time
         * 
         * @param Integer $_timeLimit
         * 
	 */
	public static function setTimeLimit($_timeLimit) {
		self::$timeLimit = $_timeLimit;
	}
        
        /**
	 * Utility function to get the appropriate Class name for a particular Entity.
	 * Note that the class may not actually exist - this function just returns
	 * the name of the class, which can then be used in a class_exists test.
	 * 
	 * The class name is normally AlexaSDK_Entity_Name_Capitalised,
	 * e.g. AlexaSDK_Incident, or AlexaSDK_Account
	 * 
	 * @param  String $entityLogicalName
	 * @return String the name of the class
         * 
         * @ignore
	 */
	public static function getClassName($entityLogicalName) {
		/* Since EntityLogicalNames are usually in lowercase, we captialise each word */
		$capitalisedEntityName = self::capitaliseEntityName($entityLogicalName);
		$className = 'AlexaSDK_'.$capitalisedEntityName."_class";
		/* Return the generated class name */
		return $className;
	}
        
        /**
	 * Utility function to captialise the Entity Name according to the following rules:
	 * 1. The first letter of each word in the Entity Name is capitalised
	 * 2. Words are separated by underscores only
	 * 
	 * @param String $entityLogicalName as it is stored in the CRM
	 * @return String the Entity Name as it would be in a PHP Class name
         * 
         * @ignore
	 */
	private static function capitaliseEntityName($entityLogicalName) {
		/* User-defined Entities generally have underscore separated names 
		 * e.g. mycompany_special_item
		 * We capitalise this as Mycompany_Special_Item
		 */
		$words = explode('_', $entityLogicalName);
		foreach($words as $key => $word) $words[$key] = ucwords(strtolower($word));
		$capitalisedEntityName = implode('_', $words);
		/* Return the capitalised name */
		return $capitalisedEntityName;
	}
        
	
	/**
	 * Utility function to parse time from XML - includes handling Windows systems with no strptime
         * 
	 * @param String $timestamp
	 * @param String $formatString
         * 
	 * @return integer PHP Timestamp
         * 
	 * @ignore
	 */
	protected static function parseTime($timestamp, $formatString) {
		/* Quick solution: use strptime */
		if(function_exists("strptime") == true) {
			$time_array = strptime($timestamp, $formatString);
		} else {
			$masks = Array(
					'%d' => '(?P<d>[0-9]{2})',
					'%m' => '(?P<m>[0-9]{2})',
					'%Y' => '(?P<Y>[0-9]{4})',
					'%H' => '(?P<H>[0-9]{2})',
					'%M' => '(?P<M>[0-9]{2})',
					'%S' => '(?P<S>[0-9]{2})',
					// usw..
			);
			$rexep = "#".strtr(preg_quote($formatString), $masks)."#";
			if(!preg_match($rexep, $timestamp, $out)) return false;
			$time_array = Array(
					"tm_sec"  => (int) $out['S'],
					"tm_min"  => (int) $out['M'],
					"tm_hour" => (int) $out['H'],
					"tm_mday" => (int) $out['d'],
					"tm_mon"  => $out['m']?$out['m']-1:0,
					"tm_year" => $out['Y'] > 1900 ? $out['Y'] - 1900 : 0,
			);
	
	
		}
		$phpTimestamp = gmmktime($time_array['tm_hour'], $time_array['tm_min'], $time_array['tm_sec'],
				$time_array['tm_mon']+1, $time_array['tm_mday'], 1900+$time_array['tm_year']);
		return $phpTimestamp;
	
	}
	
	/**
	 * Add a list of Formatted Values to an Array of Attributes, using appropriate handling
	 * avoiding over-writing existing attributes already in the array
	 *
	 * Optionally specify an Array of sub-keys, and a particular sub-key
	 * - If provided, each sub-key in the Array will be created as an Object attribute,
	 *   and the value will be set on the specified sub-key only (e.g. (New, Old) / New)
	 *
	 * @ignore
	 */
	protected static function addFormattedValues(Array &$targetArray, DOMNodeList $keyValueNodes, Array $keys = NULL, $key1 = NULL) {
		foreach ($keyValueNodes as $keyValueNode) {
			/* Get the Attribute name (key) */
			$attributeKey = $keyValueNode->getElementsByTagName('key')->item(0)->textContent;
			$attributeValue = $keyValueNode->getElementsByTagName('value')->item(0)->textContent;
			/* If we are working normally, just store the data in the array */
			if ($keys == NULL) {
				/* Assume that if there is a duplicate, it's an un-formatted version of this */
				if (array_key_exists($attributeKey, $targetArray)) {
					$targetArray[$attributeKey] = (Object)Array(
							'Value' => $targetArray[$attributeKey],
							'FormattedValue' => $attributeValue
					);
				} else {
					$targetArray[$attributeKey] = $attributeValue;
				}
			} else {
				/* Store the data in the array for this AuditRecord's properties */
				if (array_key_exists($attributeKey, $targetArray)) {
					/* We assume it's already a "good" Object, and just set this key */
					if (isset($targetArray[$attributeKey]->$key1)) {
						/* It's already set, so add the Formatted version */
						$targetArray[$attributeKey]->$key1 = (Object)Array(
								'Value' => $targetArray[$attributeKey]->$key1,
								'FormattedValue' => $attributeValue);
					} else {
						/* It's not already set, so just set this as a value */
						$targetArray[$attributeKey]->$key1 = $attributeValue;
					}
				} else {
					/* We need to create the Object */
					$obj = (Object)Array();
					foreach ($keys as $k) {
						$obj->$k = NULL;
					}
					/* And set the particular property */
					$obj->$key1 = $attributeValue;
					/* And store the Object in the target Array */
					$targetArray[$attributeKey] = $obj;
				}
			}
		}
	}
        
        /**
         * Debug function. Outputs variable wrapped in html "pre" tags
         * 
         * @param Mixed $variable Variable to be outputed using var_dump function
         */
        protected static function vardump($variable){
                echo "<pre>";
                var_dump($variable);
                echo "</pre>";
        }
    
}


/* Register the Class Loader */
spl_autoload_register(Array('AlexaSDK_Abstract', 'loadClass'));
