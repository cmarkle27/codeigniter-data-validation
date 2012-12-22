<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		Chris Markle
 * @copyright	Copyright (c) 2011
 * @license
 * @link		http://github.com/markle976
 */

// ------------------------------------------------------------------------

/**
 * Data Validation Exception Class
 *
 * @description:
 * this is the exception class thrown by the Data_validation class
 *
 */
class Validation_Exception extends Exception {};

// ------------------------------------------------------------------------

/**
 * Data Validation Class
 *
 * @description:
 * this is a modified and stripped down version of the CodeIgniter Form_validation class
 * all of the UI form related aspects have been removed and some new rules have been added
 * the main validate method now returns a sanitized (trim, etc) version of the data
 * the class has also been made to throw an exception if there were any errors
 *
 * @example:
 * // set data validation source
 * // this can be done before or after rules have been set
 * $this->data_validation->set_data($data);
 *
 * // set rules
 * $this->data_validation->set_rules('name', 'Name', 'trim|required');
 * $this->data_validation->set_rules('description', 'Description', 'trim|required');
 * $this->data_validation->set_rules('fk_customer', 'Customer Foriegn Key', 'trim|required|valid_fk');
 *
 * // validate data
 * $valid_data = $this->data_validation->validate();
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Validation
 */
class Data_validation {

	private static $_validation_data = array();
	private static $_validation_field_data = array();
	private static $_validation_error_array = array();
	private $_validation_lang_file = 'form_validation';

	// ------------------------------------------------------------------------

	public function __construct($config=array())
	{
		$this->CI =& get_instance();

		// set config options passed to constructor
		if (count($config) > 0)
		{
			foreach ($config as $key => $value)
			{
				$_key = '_validation_' . $key;

				if (property_exists($this, $_key))
				{
					$this->$_key = $value;
				}
			}
		}

		if (function_exists('mb_internal_encoding'))
		{
			mb_internal_encoding($this->CI->config->item('charset'));
		}
		log_message('debug', "Data Validation Class Initialized");
	}

	// ------------------------------------------------------------------------

	/**
	 * clear
	 * @description: to clear any existing data/rules/errors
	 *
	 */
	public function clear()
	{
		self::$_validation_data = array();
		self::$_validation_field_data = array();
		self::$_validation_error_array = array();
	}

	// ------------------------------------------------------------------------

	/**
	 * Set Rules
	 *
	 * This function takes an array of field names and validation rules
	 * as input, validates the info, and stores it
	 *
	 * @param	mixed
	 * @param	string
	 * @param	string
	 * @param	array
	 * @return	self
	 */
	public function set_rules($field, $label='', $rules='', $callback_params=array())
	{
		// If an array was passed via the first parameter instead of individual string
		// values we cycle through it and recursively call this function.
		if (is_array($field))
		{
			foreach ($field as $row)
			{
				// Houston, we have a problem...
				if ( ! isset($row['field']) OR ! isset($row['rules']))
				{
					continue;
				}

				// If the field label wasn't passed we use the field name
				$label = ( ! isset($row['label'])) ? $row['field'] : $row['label'];

				// Here we go!
				$this->set_rules($row['field'], $label, $row['rules']);
			}
			return $this;
		}

		// No fields? Nothing to do...
		if ( ! is_string($field) OR  ! is_string($rules) OR $field == '')
		{
			return $this;
		}

		// If the field label wasn't passed we use the field name
		$label = ($label == '') ? $field : $label;

		// Is the field name an array?  We test for the existence of a bracket "[" in
		// the field name to determine this.  If it is an array, we break it apart
		// into its components so that we can fetch the corresponding POST data later
		if (strpos($field, '[') !== FALSE AND preg_match_all('/\[(.*?)\]/', $field, $matches))
		{
			// Note: Due to a bug in current() that affects some versions
			// of PHP we can not pass function call directly into it
			$x = explode('[', $field);
			$indexes[] = current($x);

			for ($i = 0; $i < count($matches['0']); $i++)
			{
				if ($matches['1'][$i] != '')
				{
					$indexes[] = $matches['1'][$i];
				}
			}

			$is_array = TRUE;
		}
		else
		{
			$indexes	= array();
			$is_array	= FALSE;
		}

		// Build our master array
		self::$_validation_field_data[$field] = array(
			'field'				=> $field,
			'label'				=> $label,
			'rules'				=> $rules,
			'callback_params'	=> $callback_params,
			'is_array'			=> $is_array,
			'keys'				=> $indexes,
			'postdata'			=> NULL,
			'error'				=> ''
		);

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Run the Validator
	 *
	 * This function does all the work.
	 *
	 * @param	data
	 * @return	data
	 */
	public function validate($data=array(), $strict_mode=TRUE)
	{

		if ($strict_mode && count(self::$_validation_field_data) == 0)
		{
			throw new Validation_Exception('No data validation rules have been set. (running in strict mode)<br>');
		}

		self::$_validation_data = $data;

		// Load the language file containing error messages
		$this->CI->lang->load($this->_validation_lang_file);

		// Cycle through the rules for each field, match the
		// corresponding self::$_validation_data item and test for errors
		foreach (self::$_validation_field_data as $field => $row)
		{
			$rules = explode('|', $row['rules']);

			// this a little hack to make valid_fk automatically check for is_natural_no_zero
			if (isset(self::$_validation_data[$field]) && in_array('valid_fk', $rules))
			{
				unset($rules['valid_fk']);
				self::$_validation_data[$field] = self::valid_fk(self::$_validation_data[$field]);
				$rules[] = 'is_natural_no_zero';
			}

			// Fetch the data from the corresponding self::$_validation_data array and cache it in the _validation_field_data array.
			// Depending on whether the field name is an array or a string will determine where we get it from.
			if ($row['is_array'] == TRUE)
			{
				self::$_validation_field_data[$field]['postdata'] = $this->_reduce_array(self::$_validation_data, $row['keys']);
			}
			else
			{
				if (isset(self::$_validation_data[$field]) AND self::$_validation_data[$field] != "")
				{
					self::$_validation_field_data[$field]['postdata'] = self::$_validation_data[$field];
				}
			}

			$this->_execute($row, $rules, self::$_validation_field_data[$field]['postdata'], '', self::$_validation_field_data[$field]['callback_params']);

		}

		// Did we end up with any errors?
		if (count(self::$_validation_error_array) > 0)
		{
			// build message and throw exception
			$str = '';
			foreach (self::$_validation_error_array as $val)
			{
				if ($val != '')
				{
					$str .= $val."<br>";
				}
			}
			if ($str == '')
			{
				$str = 'Unexpected Validation Error.';
			}
			throw new Validation_Exception($str);

		}
		else
		{
			$this->_reset_data_array();
			return self::$_validation_data;
		}
	}

	// ------------------------------------------------------------------------
	// !VALIDATION RULES
	// ------------------------------------------------------------------------

	/**
	 * Required
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function required($str)
	{
		if ( ! is_array($str))
		{
			return (trim($str) == '') ? FALSE : TRUE;
		}
		else
		{
			return ( ! empty($str));
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Performs a Regular Expression match test.
	 *
	 * @access	public
	 * @param	string
	 * @param	regex
	 * @return	bool
	 */
	function regex_match($str, $regex)
	{
		if ( ! preg_match($regex, $str))
		{
			return FALSE;
		}

		return  TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Match one field to another
	 *
	 * @access	public
	 * @param	string
	 * @param	field
	 * @return	bool
	 */
	function matches($str, $field)
	{
		if ( ! isset(self::$_validation_data[$field]))
		{
			return FALSE;
		}

		$field = self::$_validation_data[$field];

		return ($str !== $field) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Minimum Length
	 *
	 * @access	public
	 * @param	string
	 * @param	value
	 * @return	bool
	 */
	function min_length($str, $val)
	{
		if (preg_match("/[^0-9]/", $val))
		{
			return FALSE;
		}

		if (function_exists('mb_strlen'))
		{
			return (mb_strlen($str) < $val) ? FALSE : TRUE;
		}

		return (strlen($str) < $val) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Max Length
	 *
	 * @access	public
	 * @param	string
	 * @param	value
	 * @return	bool
	 */
	function max_length($str, $val)
	{
		if (preg_match("/[^0-9]/", $val))
		{
			return FALSE;
		}

		if (function_exists('mb_strlen'))
		{
			return (mb_strlen($str) > $val) ? FALSE : TRUE;
		}

		return (strlen($str) > $val) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Exact Length
	 *
	 * @access	public
	 * @param	string
	 * @param	value
	 * @return	bool
	 */
	function exact_length($str, $val)
	{
		if (preg_match("/[^0-9]/", $val))
		{
			return FALSE;
		}

		if (function_exists('mb_strlen'))
		{
			return (mb_strlen($str) != $val) ? FALSE : TRUE;
		}

		return (strlen($str) != $val) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Email
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function valid_email($str)
	{
		return ( ! preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]+$/ix", $str)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Emails
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function valid_emails($str)
	{
		if (strpos($str, ',') === FALSE)
		{
			return $this->valid_email(trim($str));
		}

		foreach (explode(',', $str) as $email)
		{
			if (trim($email) != '' && $this->valid_email(trim($email)) === FALSE)
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Validate IP Address
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function valid_ip($ip)
	{
		return $this->CI->input->valid_ip($ip);
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function alpha($str)
	{
		return ( ! preg_match("/^([a-z])+$/i", $str)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha-numeric
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function alpha_numeric($str)
	{
		return ( ! preg_match("/^([a-z0-9])+$/i", $str)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Alpha-numeric with underscores and dashes
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function alpha_dash($str)
	{
		return ( ! preg_match("/^([-a-z0-9_-])+$/i", $str)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Numeric
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function numeric($str)
	{
		return (bool)preg_match( '/^[\-+]?[0-9]*\.?[0-9]+$/', $str);

	}

	// --------------------------------------------------------------------

	/**
	 * Is Numeric
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function is_numeric($str)
	{
		return ( ! is_numeric($str)) ? FALSE : TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Integer
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function integer($str)
	{
		return (bool) preg_match('/^[\-+]?[0-9]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Decimal number
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function decimal($str)
	{
		return (bool) preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Nonnegative
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function nonnegative($str, $min)
	{
		if ($str==='-0')
		{
			return FALSE;
		}
		return $this->greater_than($str, -1);
	}


	/**
	 * Greather than
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function greater_than($str, $min)
	{
		if ( ! is_numeric($str))
		{
			return FALSE;
		}
		return $str > $min;
	}

	// --------------------------------------------------------------------

	/**
	 * Less than
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function less_than($str, $max)
	{
		if ( ! is_numeric($str))
		{
			return FALSE;
		}
		return $str < $max;
	}

	// --------------------------------------------------------------------

	/**
	 * Is a Natural number  (0,1,2,3, etc.)
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function is_natural($str)
	{
		return (bool) preg_match( '/^[0-9]+$/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Is a Natural number, but not a zero  (1,2,3, etc.)
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function is_natural_no_zero($str)
	{
		if ( ! preg_match( '/^[0-9]+$/', $str))
		{
			return FALSE;
		}

		if ($str == 0)
		{
			return FALSE;
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Valid Base64
	 *
	 * Tests a string for characters outside of the Base64 alphabet
	 * as defined by RFC 2045 http://www.faqs.org/rfcs/rfc2045
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function valid_base64($str)
	{
		return (bool) ! preg_match('/[^a-zA-Z0-9\/\+=]/', $str);
	}

	// --------------------------------------------------------------------

	/**
	 * Validate Date
	 *
	 * Tests a string for a valid date in provided format
	 * Currently only accepts dates in the following formats:
	 * m/d/yy, mm/dd/yy, m/d/yyyy, mm/dd/yyyy
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */
	function date($str)
	{

		$date_array = explode("/", $str); // split the array

		if (count($date_array) == 3)
		{
			$mm = $date_array[0]; // first element of the array is month
			$dd = $date_array[1]; // second element is date
			$yy = $date_array[2]; // third element is year

			if ($yy > 3000)
			{
				return FALSE;
			}

			return checkdate($mm, $dd, $yy);
		}
		else
		{
			$date_array = explode("-", $str); // split the array

			if (count($date_array) == 3)
			{
				$yy = $date_array[0];
				$mm = $date_array[1];
				$dd = $date_array[2];

				if ($yy > 3000)
				{
					return FALSE;
				}

				return checkdate($mm, $dd, $yy);
			}
		}

		return FALSE;

	}

	// ------------------------------------------------------------------------

	// return NULL for 0, < 0, '', and false. This will make the db use the default value.
	// this will also check is_natural_no_zero
	function valid_fk($fk)
	{
		if ($fk == FALSE || $fk < 0)
		{
			return NULL;
		}
	    return trim($fk);
	}

	// ------------------------------------------------------------------------
	// !UTILITIES
	// ------------------------------------------------------------------------

	// ------------------------------------------------------------------------

	/**
	 * Executes the Validation routines
	 *
	 * @param	array
	 * @param	array
	 * @param	mixed
	 * @param	integer
	 * @param	array
	 * @return	void
	 */
	private function _execute($row, $rules, $postdata=NULL, $cycles=0, $callback_params=array())
	{

		// If the self::$_validation_data data is an array we will run a recursive call
		if (is_array($postdata))
		{
			foreach ($postdata as $key => $val)
			{
				$this->_execute($row, $rules, $val, $cycles, $callback_params);
				$cycles++;
			}

			return;
		}

		// If the field is blank, but NOT required, no further tests are necessary
		$callback = FALSE;
		if ( ! in_array('required', $rules) AND is_null($postdata))
		{
			// Before we bail out, does the rule contain a callback?
			if (preg_match("/(callback_\w+)/", implode(' ', $rules), $match))
			{
				$callback = TRUE;
				$rules = (array('1' => $match[1]));
			}
			else
			{
				return;
			}
		}

		// Isset Test. Typically this rule will only apply to checkboxes.
		if (is_null($postdata) AND $callback == FALSE)
		{
			if (in_array('isset', $rules, TRUE) OR in_array('required', $rules))
			{
				// Set the message type
				$type = (in_array('required', $rules)) ? 'required' : 'isset';

				if (FALSE === ($line = $this->CI->lang->line($type)))
				{
					$line = 'The field was not set';
				}

				// Build the error message
				$message = sprintf($line, $this->_translate_fieldname($row['label']));

				// Save the error message
				self::$_validation_field_data[$row['field']]['error'] = $message;

				if ( ! isset(self::$_validation_error_array[$row['field']]))
				{
					self::$_validation_error_array[$row['field']] = $message;
				}
			}

			return;
		}

		// --------------------------------------------------------------------

		// Cycle through each rule and run it
		foreach ($rules As $rule)
		{
			$_in_array = FALSE;

			// We set the $postdata variable with the current data in our master array so that
			// each cycle of the loop is dealing with the processed data from the last cycle
			if ($row['is_array'] == TRUE AND is_array(self::$_validation_field_data[$row['field']]['postdata']))
			{
				// We shouldn't need this safety, but just in case there isn't an array index
				// associated with this cycle we'll bail out
				if ( ! isset(self::$_validation_field_data[$row['field']]['postdata'][$cycles]))
				{
					continue;
				}

				$postdata = self::$_validation_field_data[$row['field']]['postdata'][$cycles];
				$_in_array = TRUE;
			}
			else
			{
				$postdata = self::$_validation_field_data[$row['field']]['postdata'];
			}

			// --------------------------------------------------------------------

			// Is the rule a callback?
			$callback = FALSE;
			if (substr($rule, 0, 9) == 'callback_')
			{
				$rule = substr($rule, 9);
				$callback = TRUE;
			}

			// Strip the parameter (if exists) from the rule
			// Rules can contain a parameter: max_length[5]
			$param = FALSE;
			if (preg_match("/(.*?)\[(.*)\]/", $rule, $match))
			{
				$rule	= $match[1];
				$param	= $match[2];
			}

			// Call the function that corresponds to the rule
			if ($callback === TRUE)
			{
				if ( ! method_exists($this->CI, $rule))
				{
					continue;
				}

				// Run the function and grab the result
				if (isset($callback_params) && count($callback_params) > 0)
				{
					$result = $this->CI->$rule($postdata, $callback_params);
				}
				else
				{
					$result = $this->CI->$rule($postdata, $param);
				}

				// Re-assign the result to the master data array
				if ($_in_array == TRUE)
				{
					self::$_validation_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
				}
				else
				{
					self::$_validation_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
				}

				// If the field isn't required and we just processed a callback we'll move on...
				if ( ! in_array('required', $rules, TRUE) AND $result !== FALSE)
				{
					continue;
				}
			}
			else
			{
				if ( ! method_exists($this, $rule))
				{
					// If our own wrapper function doesn't exist we see if a native PHP function does.
					// Users can use any native PHP function call that has one param.
					if (function_exists($rule))
					{
						$result = $rule($postdata);

						if ($_in_array == TRUE)
						{
							self::$_validation_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
						}
						else
						{
							self::$_validation_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
						}
					}

					continue;
				}

				$result = $this->$rule($postdata, $param);

				if ($_in_array == TRUE)
				{
					self::$_validation_field_data[$row['field']]['postdata'][$cycles] = (is_bool($result)) ? $postdata : $result;
				}
				else
				{
					self::$_validation_field_data[$row['field']]['postdata'] = (is_bool($result)) ? $postdata : $result;
				}
			}

			// Did the rule test negatively?  If so, grab the error.
			if ($result === FALSE)
			{
				if (FALSE === ($line = $this->CI->lang->line($rule)))
				{
					$line = 'Unable to access an error message corresponding to your field name.';
				}

				// Is the parameter we are inserting into the error message the name
				// of another field?  If so we need to grab its "field label"
				if (isset(self::$_validation_field_data[$param]) AND isset(self::$_validation_field_data[$param]['label']))
				{
					$param = $this->_translate_fieldname(self::$_validation_field_data[$param]['label']);
				}

				// Build the error message
				$message = sprintf($line, $this->_translate_fieldname($row['label']), $param);

				// Save the error message
				self::$_validation_field_data[$row['field']]['error'] = $message;

				if ( ! isset(self::$_validation_error_array[$row['field']]))
				{
					self::$_validation_error_array[$row['field']] = $message;
				}

				return;
			}

		}

	}

	// ------------------------------------------------------------------------

	private function _reset_data_array()
	{
		foreach (self::$_validation_field_data as $field => $row)
		{
			if ( ! is_null($row['postdata']))
			{
				if ($row['is_array'] == FALSE)
				{
					if (isset(self::$_validation_data[$row['field']]))
					{
						self::$_validation_data[$row['field']] = $row['postdata'];
					}
				}
				else
				{
					// start with a reference
					$post_ref =& self::$_validation_data;

					// before we assign values, make a reference to the right key
					if (count($row['keys']) == 1)
					{
						$post_ref =& $post_ref[current($row['keys'])];
					}
					else
					{
						foreach ($row['keys'] as $val)
						{
							$post_ref =& $post_ref[$val];
						}
					}

					if (is_array($row['postdata']))
					{
						$array = array();
						foreach ($row['postdata'] as $k => $v)
						{
							$array[$k] = $v;
						}

						$post_ref = $array;
					}
					else
					{
						$post_ref = $row['postdata'];
					}
				}
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Translate a field name
	 *
	 * @param	string
	 * @return	string
	 */
	private function _translate_fieldname($fieldname)
	{
		// Do we need to translate the field name?
		// We look for the prefix lang: to determine this
		if (substr($fieldname, 0, 5) == 'lang:')
		{
			// Grab the variable
			$line = substr($fieldname, 5);

			// Were we able to translate the field name?  If not we use $line
			if (FALSE === ($fieldname = $this->CI->lang->line($line)))
			{
				return $line;
			}
		}

		return $fieldname;
	}

	// ------------------------------------------------------------------------

	/**
	 * Traverse a multidimensional data array index until the data is found
	 *
	 * @param	array
	 * @param	array
	 * @param	integer
	 * @return	mixed
	 */
	private function _reduce_array($array, $keys, $i=0)
	{
		if (is_array($array))
		{

			if (isset($keys[$i]))
			{
				if (isset($array[$keys[$i]]))
				{
					$array = $this->_reduce_array($array[$keys[$i]], $keys, ($i+1));
				}
				else
				{
					return NULL;
				}
			}
			else
			{
				return $array;
			}
		}

		return $array;
	}

	// clear method???

	// ------------------------------------------------------------------------

}
/* end of file */