<?php

/**
 * Custom report definition used as the datasource for Contribution
 * letters added by this extension.
 */
class CRM_Customreports_Form_Report_SoftCreditContributionDetail extends CRM_Report_Form {

  public $_actionName = 'standard_letter';

  public $_actionLabel = 'Print Standard Letters (PDF)';

  public $_actionFilename = 'Contribution-Letter-Standard';

  /**
   * Class constructor.
   */
  public function __construct() {
    // Turn off the auto-added contact.id dupe.
    $this->_exposeContactID = FALSE;

    // Set the column information
    $this->setReportColumns();

    // Include any custom data points for contact and membership.
    // We're leaving out contribution since those fields are aggregated.
    $this->_customGroupExtends = ['Contact', 'Contribution'];
    $this->addCustomDataToColumns();

    // Let the parent do its thing.
    parent::__construct();
  }

  /**
   * Build the report query.  We force the limit to FALSE.  This report is
   * meant to be all-inclusive based on search terms or ID list.
   *
   * @param bool $applyLimit
   *
   * @return string
   */
  public function buildQuery($applyLimit = FALSE) {
    // Put the custom report parameters in place.
    $this->setCustomReportParams();
    return parent::buildQuery(FALSE);
  }

  /**
   * Compile the report content.
   *
   * Although this function is super-short it is useful to keep separate so it
   * can be over-ridden by report classes.
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
    // TODO: should the raw report have an action? yes, but it should to the landing.
    // getting rid of this also removes the report TPL file.  This
    // should forward to CRM_Customreports_Form_Task_CustomreportsLanding
    // for proper PDF printing of a selected letter.
    switch ($this->_outputMode) {
      case $this->_actionName:
        H::log("Execute action={$this->_actionName}");
        $content  = $this->compileContent();
        $filename = $this->_actionFilename . "_" . date("YmdHis") . ".pdf";
        CRM_Utils_PDF_Utils::html2pdf($content, $filename, FALSE, ['orientation' => 'landscape']);
        CRM_Utils_System::civiExit();
        break;
      default:
        parent::endPostProcess($rows);
        break;
    }
  }

  public function from() {
    // Base table is civicrm_contribution
    $this->_from = "FROM civicrm_contribution {$this->_aliases['civicrm_contribution']} " .

      // Financial Type
      "INNER JOIN civicrm_financial_type {$this->_aliases['civicrm_financialtype']} " .
      "ON {$this->_aliases['civicrm_contribution']}.financial_type_id = {$this->_aliases['civicrm_financialtype']}.id " .

      // Soft credit table, used to find the contacts
      "INNER JOIN civicrm_contribution_soft {$this->_aliases['civicrm_soft']} " .
      "ON {$this->_aliases['civicrm_soft']}.contribution_id = {$this->_aliases['civicrm_contribution']}.id " .

      // Soft credit type lookup
      "INNER JOIN civicrm_option_value {$this->_aliases['civicrm_option']} " .
      "ON {$this->_aliases['civicrm_option']}.value = {$this->_aliases['civicrm_soft']}.soft_credit_type_id " .
      "INNER JOIN civicrm_option_group optgroup " .
      "ON {$this->_aliases['civicrm_option']}.option_group_id = optgroup.id AND optgroup.name = 'soft_credit_type' " .

      // Contact, the organization through which the contribution is being soft credited
      "INNER JOIN civicrm_contact {$this->_aliases['civicrm_fundcontact']} " .
      "ON {$this->_aliases['civicrm_fundcontact']}.id = {$this->_aliases['civicrm_contribution']}.contact_id " .

      // Contact, the contact soft credited with the contribution
      "INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']} " .
      "ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_soft']}.contact_id " .

      // Phone, primary only
      "LEFT JOIN civicrm_phone {$this->_aliases['civicrm_phone']} " .
      "ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_phone']}.contact_id " .
      "AND {$this->_aliases['civicrm_phone']}.is_primary = 1 " .

      // Address, primary only
      "LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']} " .
      "ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_address']}.contact_id " .
      "AND {$this->_aliases['civicrm_address']}.is_primary = 1 " .

      // Email, primary only
      "LEFT JOIN civicrm_email {$this->_aliases['civicrm_email']} " .
      "ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_email']}.contact_id " .
      "AND {$this->_aliases['civicrm_email']}.is_primary = 1 " .

      // Contribution Note
      "LEFT JOIN civicrm_note {$this->_aliases['civicrm_note']} " .
      "ON {$this->_aliases['civicrm_contribution']}.id = {$this->_aliases['civicrm_note']}.entity_id " .
      "AND {$this->_aliases['civicrm_note']}.entity_table = 'civicrm_contribution' " .

      // Premiums
      "LEFT JOIN civicrm_contribution_product premiums " .
      "ON premiums.contribution_id = {$this->_aliases['civicrm_contribution']}.id " .
      "LEFT JOIN civicrm_product {$this->_aliases['civicrm_product']} " .
      "ON premiums.product_id = {$this->_aliases['civicrm_product']}.id ";

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
    $actions['report_instance.' . $this->_actionName] = [
      'title' => ts($this->_actionLabel),
    ];

    return $actions;
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
   * Create fake parameters for free-instanced report (i.e., not pulled from db)
   */
  public function setCustomReportParams() {
    // Go through the configured columns and ensure they are all selected.
    foreach ($this->_columns as $tableName => $table) {
      foreach ($table['fields'] as $fieldName => $field) {
        $this->_params['fields'][$fieldName] = 1;
      }
    }

    // Make sure soft contributions are included.
    $this->_params['contribution_or_soft_op']    = 'eq';
    $this->_params['contribution_or_soft_value'] = 'both';

    // One row per contribution.
    //$this->_params['group_bys'] = ['contribution_id' => '1'];

    // Set the "order by" options.
    $this->_params['order_bys'] = [
      1 =>
        [
          'column' => 'sort_name',
          'order'  => 'ASC',
        ],
      2 =>
        [
          'column' => 'id',
          'order'  => 'ASC',
        ],
    ];
  }

  public function setReportColumns() {
    $this->_columns = [
      'civicrm_contact'       => [
        'dao'      => 'CRM_Contact_DAO_Contact',
        'fields'   => [
          'id'               => [
            'required' => TRUE,
            'default'  => TRUE,
            'title'    => ts('Contact ID'),
          ],
          'sort_name'        => [
            'title'    => ts('Donor Name'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'first_name'       => [
            'title'    => ts('First Name'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'last_name'        => [
            'title'    => ts('Last Name'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'contact_type'     => [
            'title'    => ts('Contact Type'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'contact_sub_type' => [
            'title'    => ts('Contact Subtype'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'do_not_email'     => [
            'title'    => ts('Do Not Email'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'is_opt_out'       => [
            'title'    => ts('No Bulk Email(Is Opt Out)'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
        ],
        'grouping' => 'contact-fields',
      ],
      'civicrm_email'         => [
        'dao'      => 'CRM_Core_DAO_Email',
        'fields'   => [
          'email' => [
            'title'    => ts('Donor Email'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'grouping' => 'contact-fields',
      ],
      'civicrm_phone'         => [
        'dao'      => 'CRM_Core_DAO_Phone',
        'fields'   => [
          'phone' => [
            'title'    => ts('Donor Phone'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'grouping' => 'contact-fields',
      ],
      'civicrm_address'       => [
        'dao'      => 'CRM_Core_DAO_Address',
        'fields'   => [
          'address_name'           => [
            'title'    => ts('Address Name'),
            'default'  => TRUE,
            'required' => TRUE,
            'name'     => 'name',
          ],
          'street_address'         => [
            'title'    => ts('Street Address'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'supplemental_address_1' => [
            'title'    => ts('Supplementary Address Field 1'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'supplemental_address_2' => [
            'title'    => ts('Supplementary Address Field 2'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'street_number'          => [
            'name'     => 'street_number',
            'title'    => ts('Street Number'),
            'type'     => 1,
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'street_name'            => [
            'name'     => 'street_name',
            'title'    => ts('Street Name'),
            'type'     => 1,
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'street_unit'            => [
            'name'     => 'street_unit',
            'title'    => ts('Street Unit'),
            'type'     => 1,
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'city'                   => [
            'title'    => ts('City'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'postal_code'            => [
            'title'    => ts('Postal Code'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'postal_code_suffix'     => [
            'title'    => ts('Postal Code Suffix'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'country_id'             => [
            'title'    => ts('Country'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'state_province_id'      => [
            'title'    => ts('State/Province'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'county_id'              => [
            'title'    => ts('County'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'grouping' => 'location-fields',
      ],
      'civicrm_fundcontact' => [
        'dao'      => 'CRM_Contact_DAO_Contact',
        'table_name' => 'civicrm_contact',
        'dbAlias' => 'civicrm_fundcontact',
        'fields' => [
          'receiver_id'        => [
            'title'    => ts('Donor ID'),
            'name' => 'id',
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'receiver_name'        => [
            'title'    => ts('Donor Name'),
            'name' => 'sort_name',
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'receiver_display_name'        => [
            'title'    => ts('Donor Display Name'),
            'name' => 'display_name',
            'required' => TRUE,
            'default'  => TRUE,
          ],
        ],
        'grouping' => 'contribution-fields',
      ],
      'civicrm_soft'          => [
        'dao'        => 'CRM_Contribute_DAO_ContributionSoft',
        'table_name' => 'civicrm_contribution_soft',
        'dbAlias'    => 'civicrm_soft',
        'fields'     => [
          'soft_amount' => [
            'title'    => ts('Soft Credit Amount'),
            'name'     => 'amount',
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'grouping'   => 'contribution-fields',
        'filters'    => [
          'soft_credit_type_id' => [
            'operatorType' => CRM_Report_Form::OP_INT,
            'type'         => CRM_Utils_Type::T_INT,
          ],
        ],
      ],
      'civicrm_option'        => [
        'dao'        => 'CRM_Core_DAO_OptionValue',
        'table_name' => 'civicrm_option_value',
        'dbAlias'    => 'civicrm_option',
        'fields'     => [
          'soft_credit_type_name' => [
            'title'    => ts('Soft Credit Type'),
            'name'     => 'label',
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'grouping'   => 'contribution-fields',
      ],
      'civicrm_contribution'  => [
        'dao'      => 'CRM_Contribute_DAO_Contribution',
        'fields'   => [
          'contribution_id'          => [
            'title'    => ts('id'),
            'name'     => 'id',
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'financial_type_id'        => [
            'title'    => ts('Financial Type ID'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'contribution_page_id'     => [
            'title'    => ts('Contribution Page ID'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'payment_instrument_id'    => [
            'title'    => ts('Payment Instrument ID'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'receive_date'             => [
            'title'    => ts('Receive Date'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'non_deductible_amount'    => [
            'title'    => ts('Non-Deductible Amount'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'total_amount'             => [
            'title'    => ts('Total Amount'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'fee_amount'               => [
            'title'    => ts('Fee Amount'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'net_amount'               => [
            'title'    => ts('Net Amount'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'transaction_id'           => [
            'title'    => ts('Transaction ID'),
            'name'     => 'trxn_id',
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'invoice_id'               => [
            'title'    => ts('Invoice ID'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'currency'                 => [
            'title'    => ts('Currency'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'cancel_date'              => [
            'title'    => ts('Cancel Date'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'cancel_reason'            => [
            'title'    => ts('Cancel Reason'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'receipt_date'             => [
            'title'    => ts('Receipt Date'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'thankyou_date'            => [
            'title'    => ts('Thank You Date'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'source'                   => [
            'title'    => ts('Source'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'amount_level'             => [
            'title'    => ts('Amount Level'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'contribution_recur_id'    => [
            'title'    => ts('Recurring ID'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'is_test'                  => [
            'title'    => ts('Is Test?'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'is_pay_later'             => [
            'title'    => ts('Is Pay Later?'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'contribution_status_id'   => [
            'title'    => ts('Status ID'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'address_id'               => [
            'title'    => ts('Address ID'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'check_number'             => [
            'title'    => ts('Check Number'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'campaign_id'              => [
            'title'    => ts('Campaign ID'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'creditnote_id'            => [
            'title'    => ts('Credit Note ID'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'tax_amount'               => [
            'title'    => ts('Tax Amount'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'revenue_recognition_date' => [
            'title'    => ts('Revenue Post Date'),
            'default'  => TRUE,
            'required' => TRUE,
          ],

        ],
        'grouping' => 'contribution-fields',
        'filters'  => [
          'contribution_id' => [
            'operatorType' => CRM_Report_Form::OP_INT,
            'type'         => CRM_Utils_Type::T_INT,
          ],
          'receive_date'    => [
            'operatorType' => CRM_Report_Form::OP_DATE
          ],
        ],

      ],
      'civicrm_product'       => [
        'dao'      => 'CRM_Contribute_DAO_Product',
        'fields'   => [
          'product_name'        => [
            'title'    => 'Premium Name',
            'name'     => 'name',
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'product_description' => [
            'title'    => 'Premium Description',
            'name'     => 'description',
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'grouping' => 'contribution-fields',
      ],
      'civicrm_financialtype' => [
        'dao'        => 'CRM_Financial_DAO_FinancialType',
        'table_name' => 'civicrm_financial_type',
        'dbAlias'    => 'civicrm_financialtype',
        'fields'     => [
          'financial_type_name'        => [
            'title'    => ts('Financial Type'),
            'name'     => 'name',
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'financial_type_description' => [
            'title'    => ts('Financial Type Description'),
            'name'     => 'description',
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'is_deductible'              => [
            'title'    => ts('Is Deductible?'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'is_reserved'                => [
            'title'    => ts('Is Reserved?'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'is_active'                  => [
            'title'    => ts('Is Active?'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'grouping'   => 'contribution-fields',

      ],
      'civicrm_note'          => [
        'dao'      => 'CRM_Core_DAO_Note',
        'fields'   => [
          'contribution_note' => [
            'name'     => 'note',
            'title'    => ts('Contribution Note'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'grouping' => 'contribution-fields',
      ],
    ];
  }
}
