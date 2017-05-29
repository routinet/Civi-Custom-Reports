<?php

/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_CustomreportsLanding extends CRM_Contribute_Form_Task {

  public function __construct($state = NULL, $action = CRM_Core_Action::NONE, $method = 'post', $name = NULL) {
    parent::__construct($state, $action, $method, $name);
  }

  /**
   * Build the form object.
   *
   *
   * @return void
   */
  public function buildQuickForm() {
    H::log();

    // Set the form title.
    CRM_Utils_System::setTitle(ts('Custom Contribution Reports'));

    // Add the report selector.
    $this->addRadio(
      'report_title',
      'Please select a letter:',
      CRM_Customreports_Helper::$all_reports,
      array('required' => TRUE),
      TRUE
    );

    // Add the checkbox to force re-import of the template.
    // TODO: I don't like how this renders.  Maybe use a lower-level generation?
    $this->addCheckbox('import_flag', '', array('' => 1));

    // Add the standard buttons.
    $this->addButtons(array(
      array(
        'type'      => 'submit',
        'name'      => ts('Process Contribution Letters'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Done'),
      ),
    ));
  }

  /**
   * Process the form after the input has been submitted and validated.
   *
   *
   * @return void
   */
  public function postProcess() {
    H::log();

    // Get the form values.  Looking for a report title.
    $form_values = $this->getSubmitValues();
    $report_title = CRM_Utils_Array::value('report_title', $form_values);

    // If the requested letter is available, instantiate it and print it.
    // Printing will end the request using CRM_Utils_System::civiExit(1).
    if (array_key_exists($report_title, CRM_Customreports_Helper::$all_reports)) {
      $report_class = 'CRM_Customreports_Form_Task_' . $report_title;
      $report = new $report_class();
      $report->controller = $this->controller;
      $report->handle('submit');
    }

    // Fall through to the standard postProcess if not a custom letter.
    parent::postProcess();
  }

  public function preProcess() {
    H::log('');

    // Call the parent preProcess().
    parent::preProcess();

    // Make sure the contact IDs are populated.
    $this->setContactIDs();
  }

  /**
   * Set default values for the form.
   *
   * @return array
   *   array of default values
   */
  public function setDefaultValues() {
    $defaults = array();

    return $defaults;
  }

}