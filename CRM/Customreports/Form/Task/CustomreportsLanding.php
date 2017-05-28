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
    $this->addCheckbox('import_flag', 'Re-import from HTML template?', array('' => 1));

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
   * Handles an action.
   *
   * If an Action object was not registered here, controller's handle()
   * method will be called.
   *
   * @access public
   *
   * @param  string Name of the action
   *
   * @throws PEAR_Error
   */
  /*  function handle($actionName)
    {H::log();H::log("processing handle() for $actionName, quickform_page=\n".var_export($this,1));
      if (isset($this->_actions[$actionName])) {
        $ret = $this->_actions[$actionName]->perform($this, $actionName);
      } else {
        switch ($actionName) {
          case 'customreportsimport':
            die('y');
            break;
          default:
            $ret = $this->controller->handle($this, $actionName);
            break;
        }
      }
      return $ret;
    }*/

  /**
   * Process the form after the input has been submitted and validated.
   *
   *
   * @return void
   */
  public function postProcess() {
    H::log();
    $form_values = $this->getSubmitValues();
    $report_title = CRM_Utils_Array::value('report_title', $form_values);
    if (array_key_exists($report_title, CRM_Customreports_Helper::$all_reports)) {
      $report_class = 'CRM_Customreports_Form_Task_' . $report_title;
      $report = new $report_class();
      $report->controller = $this->controller;
      $report->handle('submit');
      die('<pre>' . var_export($_POST, 1) . '</pre>');
      die('W0OT! postprocess');
    }
    parent::postProcess();
  }

  public function preProcess() {
    H::log('');
    /*$this->controller->addAction(
      'customreportsimport',
      new CRM_Customreports_QuickForm_Action_Import($this->controller->getStateMachine())
    );*/
    parent::preProcess();
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