<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Data Validation Test Class
 *
 * @category    Tests
 * @author      Chris Markle
 */

class DataValidationLibraryTest extends PHPUnit_Framework_TestCase
{

    static $CI = NULL;

	public static function setUpBeforeClass()
    {
		self::$CI =& get_instance();
    }

    // ------------------------------------------------------------------------

    public function setUp()
    {
		self::$CI->load->library('Data_validation');
		$this->reflectionClass = new ReflectionClass(self::$CI->data_validation);
    }

    // ------------------------------------------------------------------------

    public function test_if_loaded()
    {
		$this->assertInstanceOf('Data_validation', self::$CI->data_validation);
    }

    // ------------------------------------------------------------------------

    public function test_config()
    {
		$this->data_validation = new Data_validation(array('lang_file' => 'data_validation'));
    }

    // ------------------------------------------------------------------------

    public function test_set_rules()
    {
    	self::$CI->data_validation->clear();
    	self::$CI->data_validation->set_rules('name', 'Name', 'trim|required');

		$reflectionProperty = $this->reflectionClass->getProperty('_validation_field_data');
		$reflectionProperty->setAccessible(TRUE);
		$_validation_field_data = $reflectionProperty->getValue(new Data_validation);
		$rules = $_validation_field_data['name']['rules'];

		$this->assertCount(1, $_validation_field_data);
		$this->assertInternalType('array', $_validation_field_data);
		$this->assertArrayHasKey('name', $_validation_field_data);
		$this->assertContains('trim', $rules);
		$this->assertContains('required', $rules);
    }

	// ------------------------------------------------------------------------

	public function test_set_bad_rules()
    {
    	self::$CI->data_validation->clear();
    	self::$CI->data_validation->set_rules(7);

		$reflectionProperty = $this->reflectionClass->getProperty('_validation_field_data');
		$reflectionProperty->setAccessible(TRUE);
		$_validation_field_data = $reflectionProperty->getValue(new Data_validation);

		$this->assertCount(0, $_validation_field_data);
    }

    // ------------------------------------------------------------------------

    public function test_set_rules_array()
    {
		self::$CI->data_validation->clear();

		$config = array(
		   array(
		         'field'   => 'username',
		         'label'   => 'Username',
		         'rules'   => 'required'
		      ),
		   array(
		         'field'   => 'pizza[0]',
		         'label'   => 'Pizza',
		         'rules'   => 'required'
		      ),
		   array(
		         'field'   => 'email',
		         'label'   => 'Email',
		         'rules'   => 'required'
		      )
		);

    	self::$CI->data_validation->set_rules($config);

		$reflectionProperty = $this->reflectionClass->getProperty('_validation_field_data');
		$reflectionProperty->setAccessible(TRUE);
		$_validation_field_data = $reflectionProperty->getValue(new Data_validation);
		$rules = $_validation_field_data['username']['rules'];

		$this->assertCount(3, $_validation_field_data);
		$this->assertInternalType('array', $_validation_field_data);
		$this->assertArrayHasKey('username', $_validation_field_data);
		$this->assertContains('required', $rules);
    }

    // ------------------------------------------------------------------------

    public function test_set_bad_rules_array()
    {
		self::$CI->data_validation->clear();

		$config = array(
		   array(
		         'label'   => 'Username',
		         'rules'   => 'required'
		      ),
		   array(
		         'field'   => 'email',
		         'label'   => 'Email',
		         'rules'   => 'valid_email'
		      )
		);

    	self::$CI->data_validation->set_rules($config);

		$reflectionProperty = $this->reflectionClass->getProperty('_validation_field_data');
		$reflectionProperty->setAccessible(TRUE);
		$_validation_field_data = $reflectionProperty->getValue(new Data_validation);
		$rules = $_validation_field_data['email']['rules'];

		$this->assertCount(1, $_validation_field_data);
		$this->assertInternalType('array', $_validation_field_data);
		$this->assertArrayHasKey('email', $_validation_field_data);
		$this->assertContains('valid_email', $rules);
    }

    // ------------------------------------------------------------------------

    public function test_clear()
    {
    	self::$CI->data_validation->set_rules('name', 'Name', 'trim|required');

		$reflectionProperty = $this->reflectionClass->getProperty('_validation_data');
		$reflectionProperty->setAccessible(TRUE);
		$reflectionProperty->setValue(new Data_validation, array('some_data' => 'abc'));

		$reflectionProperty = $this->reflectionClass->getProperty('_validation_error_array');
		$reflectionProperty->setAccessible(TRUE);
		$reflectionProperty->setValue(new Data_validation, array('some_error' => 'xyz'));

		self::$CI->data_validation->clear();

		$reflectionProperty = $this->reflectionClass->getProperty('_validation_data');
		$reflectionProperty->setAccessible(TRUE);

		$_validation_data = $reflectionProperty->getValue(new Data_validation);

		$reflectionProperty = $this->reflectionClass->getProperty('_validation_error_array');
		$reflectionProperty->setAccessible(TRUE);

		$_validation_error_array = $reflectionProperty->getValue(new Data_validation);

		$reflectionProperty = $this->reflectionClass->getProperty('_validation_field_data');
		$reflectionProperty->setAccessible(TRUE);

		$_validation_field_data = $reflectionProperty->getValue(new Data_validation);

		$this->assertCount(0, $_validation_data);
		$this->assertCount(0, $_validation_field_data);
		$this->assertCount(0, $_validation_error_array);
    }

    // ------------------------------------------------------------------------

    public function test_excute()
    {
    	$reflectionMethod = $this->reflectionClass->getMethod('_execute');
		$reflectionMethod->setAccessible(TRUE);
		$array = array('ida'=>123,'name'=>"Ele1", 'idb'=>12233,'name'=>"Ele2", 'idc'=>1003,'name'=>"Ele4", 'idd'=>1233,'name'=>"Ele5");
		$keys = array();

		$reflectionMethod->invoke(new Data_validation, $array, $keys);
    }

    // ------------------------------------------------------------------------

    public function test_reduce_array()
    {
    	$reflectionMethod = $this->reflectionClass->getMethod('_reduce_array');
		$reflectionMethod->setAccessible(TRUE);
		$array = array('ida'=>123,'name'=>"Ele1", 'idb'=>12233,'name'=>"Ele2", 'idc'=>1003,'name'=>"Ele4", 'idd'=>1233,'name'=>"Ele5");
		$keys = array('ida', 'idb', 'idc', 'idd');

		$this->assertInternalType('int', $reflectionMethod->invoke(new Data_validation, $array, $keys));
    }

    // ------------------------------------------------------------------------

    public function test_reduce_array_badkey()
    {
    	$reflectionMethod = $this->reflectionClass->getMethod('_reduce_array');
		$reflectionMethod->setAccessible(TRUE);
		$array = array('ida'=>123,'name'=>"Ele1", 'idb'=>12233,'name'=>"Ele2", 'idc'=>1003,'name'=>"Ele4", 'idd'=>1233,'name'=>"Ele5");
		$keys = array('x', 'y', 'z');

		$this->assertNull($reflectionMethod->invoke(new Data_validation, $array, $keys));
    }

    // ------------------------------------------------------------------------

    public function test_reduce_array_nokey()
    {
    	$reflectionMethod = $this->reflectionClass->getMethod('_reduce_array');
		$reflectionMethod->setAccessible(TRUE);
		$array = array('ida'=>123,'name'=>"Ele1", 'idb'=>12233,'name'=>"Ele2", 'idc'=>1003,'name'=>"Ele4", 'idd'=>1233,'name'=>"Ele5");
		$keys = array();

		$this->assertInternalType('array', $reflectionMethod->invoke(new Data_validation, $array, $keys));
    }

    // ------------------------------------------------------------------------

    public function test_no_translate_fieldname()
    {
    	$reflectionMethod = $this->reflectionClass->getMethod('_translate_fieldname');
		$reflectionMethod->setAccessible(TRUE);

    	$fieldname = $reflectionMethod->invoke(new Data_validation, 'lang:username');
    	$this->assertEquals('username', $fieldname);
    }

    // ------------------------------------------------------------------------

    public function test_none_translate_fieldname()
    {
    	$reflectionMethod = $this->reflectionClass->getMethod('_translate_fieldname');
		$reflectionMethod->setAccessible(TRUE);

    	$fieldname = $reflectionMethod->invoke(new Data_validation, 'username');
    	$this->assertEquals('username', $fieldname);
    }

    // ------------------------------------------------------------------------

    public function test_good_translate_fieldname()
    {
		self::$CI->lang->load('general', 'en');

    	$reflectionMethod = $this->reflectionClass->getMethod('_translate_fieldname');
		$reflectionMethod->setAccessible(TRUE);

    	$fieldname = $reflectionMethod->invoke(new Data_validation, 'lang:general_help');
    	$this->assertEquals('Help', $fieldname);
    }

	// ------------------------------------------------------------------------

	public function test_reset_data_array()
	{
		self::$CI->data_validation->set_rules('some_data', 'Some Data', 'trim');

		$reflectionProperty = $this->reflectionClass->getProperty('_validation_data');
		$reflectionProperty->setAccessible(TRUE);
		$reflectionProperty->setValue(new Data_validation, array('some_data' => 'abc'));

    	$reflectionMethod = $this->reflectionClass->getMethod('_reset_data_array');
		$reflectionMethod->setAccessible(TRUE);
    	$reflectionMethod->invoke(new Data_validation);

    	var_dump($reflectionProperty->getValue(new Data_validation));
    	//...


	}

}