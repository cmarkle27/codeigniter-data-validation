codeigniter-data-validation
===========================

#CodeIgniter Data Validation class

This is a modified and stripped down version of the CodeIgniter Form_validation class. All of the UI form related aspects have been removed and some new rules have been added. The main validate method now returns a sanitized (trim, etc) version of the data. The class has also been made to throw an exception if there were any errors.

###Example
    // set data validation source
    // this can be done before or after rules have been set
    $this->data_validation->set_data($data);

    // set rules
    $this->data_validation->set_rules('name', 'Name', 'trim|required');
    $this->data_validation->set_rules('description', 'Description', 'trim|required');
    $this->data_validation->set_rules('fk_customer', 'Customer Foriegn Key', 'trim|required|valid_fk');

    // validate data
    $valid_data = $this->data_validation->validate();