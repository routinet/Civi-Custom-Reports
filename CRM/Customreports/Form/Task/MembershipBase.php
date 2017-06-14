<?php

/**
 * Created by PhpStorm.
 * User: sbink
 * Date: 5/13/2017
 * Time: 5:43 PM
 */
class CRM_Customreports_Form_Task_MembershipBase extends CRM_Member_Form_Task {

  // To be overwritten by child classes.
  protected $templateTitle = '';

  protected $templateName = '';

  protected $context = 'membership';

  public $tokens = [];

  public $report_data = [];

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
    // Initialize tokens.  Contact tokens are loaded from the token system,
    // since _contactIds is conveniently populated with soft contact IDs also.
    $tokenized = [
      'component' => [],
      'contact'   => CRM_Utils_Token::getTokenDetails($this->_contactIds, NULL, FALSE, FALSE)[0],
    ];

    // For each contribution row...
    foreach ($this->report_data as $membership_key => $membership_row) {
      // Get some easy references to the ID fields.
      $contact_id        = $membership_row['civicrm_contact_id'];
      $second_contact_id = $membership_row['civicrm_secondcontact_second_id'];
      $component_id      = $membership_row['civicrm_membership_membership_id'];

      // For each field in the row, add the component fields to our token list.
      // We ignore the contact fields, since they have already been loaded by
      // the initialization getTokenDetails().
      foreach ($membership_row as $field => $value) {
        $matches = [];
        preg_match('/^civicrm_([^_]+)_(.*)/', $field, $matches);
        switch ($matches[1]) {
          case 'membership':
          case 'contribution':
            $tokenized['component'][$component_id][$matches[2]] = $value;
            break;
          case 'value':
            // custom fields belong to the component
            $second_find = $matches[2];
            $matches     = [];
            preg_match('/[a-zA-Z0-9_]+_[0-9]+_([a-zA-Z0-9_]+)/', $second_find, $matches);
            $tokenized['component'][$component_id][$matches[1]] = $value;
            break;
        }

      }
      // The contact ID is only found in the contact data.  Add it to component as well.
      $tokenized['component'][$component_id]['contact_id']        = $contact_id;
      $tokenized['component'][$component_id]['second_contact_id'] = $second_contact_id;

      // Add the secondary contact, if present

    }
    $this->tokens = $tokenized;
  }

  /**
   * Render final HTML output based on the configured template file, and the
   * loaded tokens.
   *
   * @return array rendered HTML documents for each component ID.
   */
  public function getHtmlFromSmarty($tokens) {
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

        // If a soft credit contact is available, set it also
        if (!empty($row['second_contact_id'])) {
          $smarty->assign('second_contact', $this->tokens['contact'][$row['second_contact_id']]);
        }

        // TODO: For debugging
        //H::log("all tokens=\n".var_export($this->tokens,1));
        // Add the Smarty-parsed template to the return array
        $ret[] = $smarty->fetch("string:" . $prep_template);
      }
    }

    return $ret;
  }

  public function loadReportData() {
    H::log();

    // Get the report instance
    $report = new CRM_Customreports_Form_Report_FullMembershipDetail();

    // Set the filter to use contribution IDs as passed to this form.
    $report->modifyParams('membership_id_op', 'in');
    $report->modifyParams('membership_id_value', $this->_componentIds);

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
  }

  /**
   * Process the form after the input has been submitted and validated.
   *
   *
   * @return void
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
    $this->writePDF($html);

  }

  /**
   * Build all the data structures needed to build the form.
   */
  public function preProcess() {
    H::log();

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
   * Overridden in order to add secondary (dependent) membership recipients
   * to the report's list of contacts to be loaded.  This is adapted from
   * CRM_Contribute_BAO_Query::isSoftCreditOptionEnabled().
   */
  public function setContactIDs() {
    // We only want to build this table once.
    static $tempTableFilled = FALSE;

    // Let the parent handle the "core" contact IDs.
    parent::setContactIDs();

    // Build the temp table, if necessary.
    if (!$tempTableFilled) {
      // This table will find all contact IDs for people receiving a
      // membership through a relationship with a member.  This is looking
      // specifically for relationships of "Provides Membership To".
      $tempQuery = "CREATE TEMPORARY TABLE IF NOT EXISTS " .
        "membership_search_related_contacts AS " .
        "SELECT member.id as id, member.contact_id as primary_contact_id, " .
        "contact.id as contact_id FROM civicrm_membership member " .
        "LEFT JOIN (civicrm_relationship rel INNER JOIN " .
        "civicrm_relationship_type rel_type " .
        "ON rel.relationship_type_id=rel_type.id " .
        "AND rel_type.name_a_b='Provides Membership To') " .
        "ON rel.contact_id_a = member.contact_id " .
        "LEFT JOIN civicrm_contact contact " .
        "ON contact.id = rel.contact_id_b ";
      CRM_Core_DAO::executeQuery($tempQuery);
      $tempTableFilled = TRUE;
    }

    // Use the DAO short-cut method to get the results.
    $ret = CRM_Core_DAO::getContactIDsFromComponent(
      $this->_componentIds,
      'membership_search_related_contacts'
    );

    // Merge them into the list of contact IDs.
    $this->_contactIds = array_merge($this->_contactIds, $ret);
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

  public function writePDF($html) {
    // Add all the HTML pages to a PDF.
    // Get the "standard" PDF format.
    $format        = CRM_Core_BAO_PdfFormat::getPdfFormat('label', CRM_Customreports_Helper::$pdfFormatName);
    $layout_format = json_decode($format['value']);

    // Set the proper orientation expected by TCPDF based on the format.
    $layout_format->tcpdf_orient = strtoupper(substr($layout_format->orientation, 0, 1));

    // Set the custom margins.
    // TODO: This should be in the loaded PDF format.  Can worry about it later.
    $layout_format->margin_left  = '.75';
    $layout_format->margin_right = '.75';
    $layout_format->margin_top   = '.75';

    // Create the PDF object and set up the base style.
    $pdf = new TCPDF($layout_format->tcpdf_orient, $layout_format->metric, $layout_format->paper_size);
    $pdf->Open();
    $pdf->SetMargins($layout_format->margin_left, $layout_format->margin_top, $layout_format->margin_right);
    $pdf->setPrintHeader(FALSE);
    $pdf->setPrintFooter(FALSE);
    // TODO: font selection.
    $pdf->setFont('interstate-light');

    // Write the pages to the PDF.
    foreach ($html as $key => $one_page) {
      $pdf->AddPage();
      $pdf->writeHTML($one_page);
    }

    // Push the PDF and exit.
    $pdf->Close();
    $pdf_file = $this->templateName . '-' . date("YmdHis") . '.pdf';
    $pdf->Output($pdf_file, 'D');
    CRM_Utils_System::civiExit(1);
  }
}