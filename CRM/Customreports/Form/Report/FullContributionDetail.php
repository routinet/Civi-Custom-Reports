<?php
/**
 * Custom report definition used as the datasource for Contribution
 * letters added by this extension.
 */
class CRM_Customreports_Form_Report_FullContributionDetail extends CRM_Report_Form_Contribute_Detail {
  public $_actionName = 'standard_letter';
  public $_actionLabel = 'Print Standard Letters (PDF)';
  public $_actionFilename = 'Contribution-Letter-Standard';

  /**
   * Create fake parameters for free-instanced report (i.e., not pulled from db)
   *
   * @return array
   */
  public function fetchCustomReportParams() {
    return array (
      'fields' =>
        array (
          'sort_name' => '1',
          'prefix_id' => '1',
          'first_name' => '1',
          'nick_name' => '1',
          'middle_name' => '1',
          'last_name' => '1',
          'suffix_id' => '1',
          'postal_greeting_display' => '1',
          'email_greeting_display' => '1',
          'addressee_display' => '1',
          'contact_type' => '1',
          'contact_sub_type' => '1',
          'gender_id' => '1',
          'birth_date' => '1',
          'age' => '1',
          'job_title' => '1',
          'organization_name' => '1',
          'external_identifier' => '1',
          'do_not_email' => '1',
          'do_not_phone' => '1',
          'do_not_mail' => '1',
          'do_not_sms' => '1',
          'is_opt_out' => '1',
          'is_deceased' => '1',
          'preferred_language' => '1',
          'exposed_id' => '1',
          'email' => '1',
          'phone' => '1',
          'list_contri_id' => '1',
          'financial_type_id' => '1',
          'contribution_status_id' => '1',
          'contribution_page_id' => '1',
          'source' => '1',
          'payment_instrument_id' => '1',
          'check_number' => '1',
          'trxn_id' => '1',
          'receive_date' => '1',
          'receipt_date' => '1',
          'total_amount' => '1',
          'non_deductible_amount' => '1',
          'fee_amount' => '1',
          'net_amount' => '1',
          'contribution_or_soft' => '1',
          'soft_credits' => '1',
          'soft_credit_for' => '1',
          'soft_credit_type_id' => '1',
          'batch_id' => '1',
          'contribution_note' => '1',
          'address_name' => '1',
          'street_address' => '1',
          'supplemental_address_1' => '1',
          'supplemental_address_2' => '1',
          'street_number' => '1',
          'street_name' => '1',
          'street_unit' => '1',
          'city' => '1',
          'postal_code' => '1',
          'postal_code_suffix' => '1',
          'country_id' => '1',
          'state_province_id' => '1',
          'county_id' => '1',
          'custom_97' => '1',
          'custom_32' => '1',
          'custom_33' => '1',
          'custom_34' => '1',
          'custom_35' => '1',
          'custom_36' => '1',
          'custom_37' => '1',
          'custom_38' => '1',
          'custom_39' => '1',
          'custom_40' => '1',
          'custom_41' => '1',
          'custom_42' => '1',
          'custom_43' => '1',
          'custom_44' => '1',
          'custom_45' => '1',
          'custom_52' => '1',
          'custom_48' => '1',
          'custom_49' => '1',
          'custom_50' => '1',
          'custom_51' => '1',
          'custom_77' => '1',
          'custom_78' => '1',
          'custom_79' => '1',
          'custom_80' => '1',
          'custom_81' => '1',
          'custom_82' => '1',
          'custom_83' => '1',
          'custom_84' => '1',
          'custom_86' => '1',
        ),
      'contribution_or_soft_op' => 'eq',
      'contribution_or_soft_value' => 'both',
      'contribution_id_op' => 'in',
      'contribution_id_value' => '',
      'order_bys' =>
        array (
          1 =>
            array (
              'column' => 'sort_name',
              'order' => 'ASC',
            ),
          2 =>
            array (
              'column' => 'id',
              'order' => 'ASC',
            ),
        ),
    );
  }

  /**
   * Class constructor.
   */
  public function __construct() {
    parent::__construct();

    // Put the custom report parameters in place.
    $this->_params = $this->fetchCustomReportParams();

    // Add a filter for contribution_id.
    $this->_columns['civicrm_contribution']['filters']['contribution_id'] = array(
      'operatorType' => CRM_Report_Form::OP_INT,
      'type' => CRM_Utils_Type::T_INT,
    );
  }

  /**
   * Modify the reports internal $_params.  Allows for manipulating the
   * report structure and context when it is not instanced via the normal report
   * path (e.g., new CRM_Customreports_Form_Report_FullContributionDetail)
   *
   * @param $values string|array
   */
  public function modifyParams($key, $value) {
    $this->_params[$key] = $value;
  }

  /**
   * Compile the report content.
   *
   * Although this function is super-short it is useful to keep separate so it can be over-ridden by report classes.
   *
   * @return string
   */
  public function compileContent() {
    $templateFile = $this->getHookedTemplateFileName();
    return CRM_Core_Form::$_template->fetch($templateFile);
  }

  /**
   * End post processing.
   *
   * @param array|null $rows
   */
  public function endPostProcess(&$rows = NULL) {
    H::log();
    // TODO: should the raw report have an action?
    // getting rid of this also removes the report TPL file.  This
    // should forward to CRM_Customreports_Form_Task_CustomreportsLanding
    // for proper PDF printing of a selected letter.
    switch ($this->_outputMode) {
      case $this->_actionName:
        H::log("Execute action={$this->_actionName}");
        $content = $this->compileContent();
        $filename = $this->_actionFilename . "_" . date("YmdHis") . ".pdf";
        CRM_Utils_PDF_Utils::html2pdf($content, $filename, FALSE, array('orientation' => 'landscape'));
        CRM_Utils_System::civiExit();
        break;
      default:
        parent::endPostProcess($rows);
        break;
    }
  }

  /**
   * Get the actions for this report instance.
   * TODO: exists only assuming the report needs an action.
   *
   * @param int $instanceId
   *
   * @return array
   */
  protected function getActions($instanceId) {
    // Get the standard actions.
    $actions = parent::getActions($instanceId);

    // Add the custom action.
    $actions['report_instance.' . $this->_actionName] = array(
      'title' => ts($this->_actionLabel),
    );

    return $actions;
  }

}
