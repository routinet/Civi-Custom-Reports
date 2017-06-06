<?php

/**
 * Custom report definition used as the datasource for Contribution
 * letters added by this extension.
 */
class CRM_Customreports_Form_Report_FullMembershipDetail extends CRM_Report_Form {
  public $_actionName = 'standard_letter';
  public $_actionLabel = 'Print Standard Letters (PDF)';
  public $_actionFilename = 'Membership-Letter-Standard';

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
    $this->_customGroupExtends = ['Contact', 'Membership'];
    $this->addCustomDataToColumns();

    // Let the parent do its thing.
    parent::__construct();
  }

  /**
   * Build the report query.
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
    // Base table is civicrm_membership
    $this->_from = "FROM civicrm_membership {$this->_aliases['civicrm_membership']} " .

      // Membership_Type
      "INNER JOIN civicrm_membership_type {$this->_aliases['civicrm_membership_type']} " .
      "ON {$this->_aliases['civicrm_membership']}.membership_type_id = {$this->_aliases['civicrm_membership_type']}.id " .

      // Contact, the contact owning the membership
      "INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact']} " .
      "ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_membership']}.contact_id " .

      // Membership_Status
      "INNER JOIN civicrm_membership_status {$this->_aliases['civicrm_membership_status']} " .
      "ON {$this->_aliases['civicrm_membership_status']}.id = {$this->_aliases['civicrm_membership']}.status_id " .

      // Membership_Payment, a many-to-many table for memberships and contributions
      "LEFT JOIN civicrm_membership_payment mem_payment " .
      "ON mem_payment.membership_id = {$this->_aliases['civicrm_membership']}.id " .

      // Contributions
      "LEFT JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']} " .
      "ON {$this->_aliases['civicrm_contribution']}.id = mem_payment.contribution_id " .

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

      // If present, tie in to the secondary member through relationship.
      // This assumes the desired relationship is "Provides Membership To"
      // TODO: make this configurable?
      "LEFT JOIN (" .
      "civicrm_relationship rel INNER JOIN civicrm_relationship_type rel_type " .
      "ON rel.relationship_type_id=rel_type.id AND rel_type.name_a_b='Provides Membership To'" .
      ") ON rel.contact_id_a = {$this->_aliases['civicrm_contact']}.id " .
      "LEFT JOIN civicrm_contact second_contact_civireport " .
      "ON second_contact_civireport.id = rel.contact_id_b";
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

    $this->_params['group_bys'] = array('membership_id' => '1');

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
      'civicrm_contact'           => [
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
      'second_contact'            => [
        'dao'      => 'CRM_Contact_DAO_Contact',
        'alias'    => 'second_contact',
        'fields'   => [
          'second_id'               => [
            'name'     => 'id',
            'required' => TRUE,
            'default'  => TRUE,
            'title'    => ts('Second Contact ID'),
          ],
          'second_sort_name'        => [
            'name'     => 'sort_name',
            'title'    => ts('Second Donor Name'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'second_first_name'       => [
            'name'     => 'first_name',
            'title'    => ts('Second First Name'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'second_last_name'        => [
            'name'     => 'last_name',
            'title'    => ts('Second Last Name'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'second_contact_type'     => [
            'name'     => 'contact_type',
            'title'    => ts('Second Contact Type'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'second_contact_sub_type' => [
            'name'     => 'contact_sub_type',
            'title'    => ts('Second Contact Subtype'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'second_do_not_email'     => [
            'name'     => 'do_not_email',
            'title'    => ts('Second Do Not Email'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
          'second_is_opt_out'       => [
            'name'     => 'is_opt_out',
            'title'    => ts('Second No Bulk Email(Is Opt Out)'),
            'required' => TRUE,
            'default'  => TRUE,
          ],
        ],
        'grouping' => 'second-contact-fields',
      ],
      'civicrm_email'             => [
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
      'civicrm_phone'             => [
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
      'civicrm_address'           => [
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
      'civicrm_membership'        => [
        'dao'       => 'CRM_Member_DAO_Membership',
        'fields'    => [
          'membership_id' => [
            'title' => ts("Membership ID"),
            'required' => TRUE,
            'name' => 'id',
          ],
          'membership_type_id'    => [
            'title'    => ts('Membership Type ID'),
            'required' => TRUE,
          ],
          'membership_start_date' => [
            'title'    => ts('Start Date'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'membership_end_date'   => [
            'title'    => ts('End Date'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'join_date'             => [
            'title'    => ts('Join Date'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'source'                => [
            'title'    => ts('Membership Source'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'filters'   => [
          'membership_id'         => [
            'operatorType' => CRM_Report_Form::OP_INT,
            'type'         => CRM_Utils_Type::T_INT,
          ],
          'join_date'             => ['operatorType' => CRM_Report_Form::OP_DATE],
          'membership_start_date' => ['operatorType' => CRM_Report_Form::OP_DATE],
          'membership_end_date'   => ['operatorType' => CRM_Report_Form::OP_DATE],
          'owner_membership_id'   => [
            'title'        => ts('Membership Owner ID'),
            'operatorType' => CRM_Report_Form::OP_INT,
          ],
          'tid'                   => [
            'name'         => 'membership_type_id',
            'title'        => ts('Membership Types'),
            'type'         => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options'      => CRM_Member_PseudoConstant::membershipType(),
          ],
        ],
        'group_bys' => [
          'membership_id' => [
            'title'   => ts('Membership ID'),
            'default' => TRUE,
          ],
        ],
        'grouping'  => 'member-fields',
      ],
      'civicrm_membership_status' => [
        'dao'      => 'CRM_Member_DAO_MembershipStatus',
        'alias'    => 'mem_status',
        'fields'   => [
          'membership_status_name' => [
            'name'     => 'name',
            'title'    => ts('Membership Status'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'filters'  => [
          'sid' => [
            'name'         => 'id',
            'title'        => ts('Membership Status'),
            'type'         => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options'      => CRM_Member_PseudoConstant::membershipStatus(NULL, NULL, 'label'),
          ],
        ],
        'grouping' => 'member-fields',
      ],
      'civicrm_membership_type'   => [
        'dao'      => 'CRM_Member_DAO_MembershipType',
        'alias'    => 'mem_type',
        'fields'   => [
          'membership_type_name' => [
            'name'     => 'name',
            'title'    => ts('Membership Type'),
            'default'  => TRUE,
            'required' => TRUE,
          ],
          'minimum_fee'          => [
            'name'     => 'minimum_fee',
            'title'    => 'Membership Cost',
            'default'  => TRUE,
            'required' => TRUE,
          ],
        ],
        'grouping' => 'member-fields',
      ],
      'civicrm_contribution'      => [
        'dao'      => 'CRM_Contribute_DAO_Contribution',
        'fields'   => [
          'sum_total_amount' => [
            'dbAlias'  => 'SUM(total_amount)',
            'title'    => ts('Sum Total Amt'),
            'required' => TRUE,
          ],
        ],
        'grouping' => 'member-fields',
      ],
    ];
  }
}
