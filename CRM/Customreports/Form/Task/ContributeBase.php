<?php

/**
 * Created by PhpStorm.
 * User: sbink
 * Date: 5/13/2017
 * Time: 5:43 PM
 */
class CRM_Customreports_Form_Task_ContributeBase extends CRM_Contribute_Form_Task {

  // To be overwritten by child classes.
  // The title of the template entry.
  protected $templateTitle = '';

  // The machine name of the template entry.
  protected $templateName = '';

  // The class name of the report to use as a data source.
  protected $reportName = '';

  // The name of the PDF format to use for this letter.
  protected $pdfFormat = NULL;

  protected $context = 'contribution';

  // Raw report data
  public $report_data = [];

  // Report data, translated into token groups for Smarty.
  public $tokens = [];

  /**
   * To be overwritten by child classes for letter-specific Smarty token groups.
   *
   * @param $smarty
   * @param $row
   */
  public function assignExtraTokens(&$smarty, $row) {
  }

  /**
   * To be overwritten by child classes for letter-specific token customization.
   */
  public function customizeTokenDetails() {
    H::log();
  }

  /**
   * Macro function to grab all tokens.
   */
  public function getAllTokenDetails() {
    H::log();
    $this->getBaseTokens();
    $this->customizeTokenDetails();
  }

  /**
   * Populate the "base" tokens.  One section for context, and one section
   * for the primary contact.
   */
  public function getBaseTokens() {
    // Clean any NULL/zero entries from the contact IDs.
    $this->_contactIds = array_filter($this->_contactIds);

    // Initialize tokens.  Contact tokens are loaded from the token system,
    // since _contactIds is conveniently populated with soft contact IDs also.
    $tokenized = [
      'component' => [],
      'contact'   => CRM_Utils_Token::getTokenDetails($this->_contactIds, NULL, FALSE, FALSE)[0],
    ];

    // For each contribution row...
    foreach ($this->report_data as $contribution_key => $contribution_row) {
      H::log("one row=\n" . var_export($contribution_row, 1));
      // Get some easy references to the ID fields.
      $contact_id   = $contribution_row['civicrm_contact_id'];
      $component_id = $contribution_row['civicrm_contribution_contribution_id'];

      // For each field in the row, add the component fields to our token list.
      // We ignore the contact fields, since they have already been loaded by
      // the initialization getTokenDetails().
      foreach ($contribution_row as $field => $value) {
        $matches = [];
        preg_match('/^civicrm_([^_]+)_(.*)/', $field, $matches);
        switch ($matches[1]) {
          case 'value':
            // custom fields belong to the component
            $second_find = $matches[2];
            $matches     = [];
            preg_match('/[a-zA-Z0-9_]+_[0-9]+_([a-zA-Z0-9_]+)/', $second_find, $matches);
            $tokenized['component'][$component_id][$matches[1]] = $value;
            break;
          default:
            $tokenized['component'][$component_id][$matches[2]] = $value;
            break;
        }
      }
      // The contact ID is only found in the contact data.  Add it to component as well.
      $tokenized['component'][$component_id]['contact_id'] = $contact_id;
    }

    $this->tokens = $tokenized;
  }

  /**
   * Render final HTML output based on the configured template file, and the
   * loaded tokens.
   *
   * @return array rendered HTML documents for each component ID.
   */
  public function getHtmlFromSmarty() {
    H::log();
    $ret = [];

    if (isset($this->tokens['component']) && isset($this->tokens['contact'])) {
      // Create a smarty template.
      $smarty = CRM_Core_Smarty::singleton();

      // Prep the template for smarty parsing.
      $prep_template = preg_replace('/\\{([a-z0-9._]+)\\}/i', '{\\$$1}', $this->template['msg_html']);

      // Generate an HTML page for each contribution row.
      foreach ($this->tokens['component'] as $id => &$row) {
        // Set the electronic signature token
        $row['electronic_signature'] = CRM_Customreports_Helper::renderSignature($row);

        // Set the component tokens
        $smarty->assign($this->context, $row);

        // Set the primary contact tokens
        $smarty->assign('contact', $this->tokens['contact'][$row['contact_id']]);

        $this->assignExtraTokens($smarty, $row);

        // TODO: For debugging
        H::log("all tokens=\n" . var_export($this->tokens, 1));
        // Add the Smarty-parsed template to the return array
        $ret[] = $smarty->fetch("string:" . $prep_template);
      }
    }

    return $ret;
  }

  /**
   * Instantiates and runs the custom report specified for this letter.
   */
  public function loadReportData() {
    H::log();

    // Get the report instance
    $report_class = "CRM_Customreports_Form_Report_" . $this->reportName;
    $report       = new $report_class;

    // Set the filter to use contribution IDs as passed to this form.
    $report->modifyParams('contribution_id_op', 'in');
    $report->modifyParams('contribution_id_value', $this->_componentIds);

    // Make sure the report is not expecting a controller.
    $report->noController = TRUE;

    // Let the report do its magic.
    $report->preProcess();

    // build query
    $sql = $report->buildQuery();

    // build array of result based on column headers. This method also allows
    // modifying column headers before using it to build result set i.e $rows.
    $this->report_data = [];
    $report->buildRows($sql, $this->report_data);

    // format result set.
    $report->formatDisplay($this->report_data, FALSE);

    // Set the "Thank You" date for each contribution being processed.  Note
    // that the field is at the contribution level, not the soft credit level.
    $selected_ids = array_unique(
      array_map(
        function ($v, $k) {
          return (int) $v['civicrm_contribution_contribution_id'];
        },
        $this->report_data
      )
    );

    $sql = "UPDATE civicrm_contribution " .
      "SET thankyou_date=NOW() " .
      "WHERE id IN (" . implode(',', $selected_ids) . ") " .
      "AND thankyou_date IS NULL";
    CRM_Core_DAO::executeQuery($sql);
  }

  /**
   * Process the form after the input has been submitted and validated.
   */
  public function postProcess() {
    H::log();

    // Load the report data
    $this->loadReportData();

    // Get all the token details for the records to be printed.
    $this->getAllTokenDetails();

    // Create an array to hold all the rendered pages.  Note that this
    // could have pretty high memory requirements.  We may need to figure
    // out alternatives.
    $html = $this->getHtmlFromSmarty();

    // Write the pages to a PDF, send the PDF, and end.
    CRM_Customreports_Helper::writeToDompdf($html, $this->templateName, $this->pdfFormat);
  }

  /**
   * Build all the data structures needed to build the form.
   */
  public function preProcess() {
    H::log();

    if (empty($this->pdfFormat)) {
      $this->pdfFormat = CRM_Customreports_Helper::$pdfDefaultFormatName;
    }

    $form_values = $this->getSubmitValues();

    // This will not be set unless the "re-import" box was checked.
    $import         = isset($form_values['import_flag']) && (boolean) $form_values['import_flag'];
    $this->template = CRM_Customreports_Helper::fetchMessageTemplate(
      $this->context,
      $this->templateTitle,
      $this->templateName,
      $import
    );

    // Call the parent preProcess().
    parent::preProcess();

    // Make sure the contact IDs are populated.
    $this->setContactIDs();
  }

  /**
   * Given the contribution id, compute the contact id
   * since its used for things like send email
   *
   * Overwritten to allow for loading soft contribution contacts.
   */
  public function setContactIDs() {
    $queryParams       = $this->get('queryParams');
    $use_soft_contacts = CRM_Contribute_BAO_Query::isSoftCreditOptionEnabled($queryParams);

    // If soft contacts are used, this populates _contactIds with the actual
    // contact IDs for the contributions, as well as the contact IDs for
    // any soft credits.
    if ($use_soft_contacts) {
      $this->_contactIds = &CRM_Core_DAO::getContactIDsFromComponent(
        $this->_contributionIds,
        'contribution_search_scredit_combined'
      );
    }
    else {
      parent::setContactIDs();
    }
    H::log(__FUNCTION__ . " set contacts to=\n" . var_export($this->_contactIds, 1));
  }

  /**
   * Set default values for the form.
   *
   * @return array
   *   array of default values
   */
  public function setDefaultValues() {
    $defaults = [];

    return $defaults;
  }
}