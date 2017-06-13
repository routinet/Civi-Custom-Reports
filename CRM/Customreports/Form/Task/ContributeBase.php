<?php

/**
 * Created by PhpStorm.
 * User: sbink
 * Date: 5/13/2017
 * Time: 5:43 PM
 */
class CRM_Customreports_Form_Task_ContributeBase extends CRM_Contribute_Form_Task {

  // To be overwritten by child classes.
  protected $templateTitle = '';

  protected $templateName = '';

  protected $context = 'contribution';

  public $tokens = [];

  public $report_data = [];

  /**
   * To be overwritten by child classes for letter-specific token customization.
   */
  public function customizeTokenDetails() {
    H::log();
    $this->loadSoftCreditContacts();
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
      'contact' => CRM_Utils_Token::getTokenDetails($this->_contactIds, NULL, FALSE, FALSE)[0],
    ];

    // For each contribution row...
    foreach ($this->report_data as $contribution_key => $contribution_row) {
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
          case 'financialtype':
          case 'contribution':
          case 'note':
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
      $smarty           = CRM_Core_Smarty::singleton();

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
        if (!empty($row['primary_soft_contact'])) {
          $smarty->assign('soft_contact', $this->tokens['contact'][$row['primary_soft_contact']]);
        }

        // Add the Smarty-parsed template to the return array
        $ret[] = $smarty->fetch("string:" . $prep_template);
      }
    }

    return $ret;
  }

  public function loadReportData() {
    H::log();

    // Get the report instance
    $report = new CRM_Customreports_Form_Report_FullContributionDetail();

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
  }

  public function loadSoftCreditContacts() {

    // An array of soft credit contact IDs not already loaded.
    $extra_ids = [];

    // Get the soft credits for these contributions.
    $IDs   = implode(',', $this->_componentIds);
    $query = "SELECT contribution_id, contact_id FROM civicrm_contribution_soft WHERE contribution_id IN ( $IDs )";
    $dao = CRM_Core_DAO::executeQuery($query);

    // For each contact, see if it is loaded.  If not, add it to the list.
    // Also, note the *first* contact as the "primary".
    while ($dao->fetch()) {
      if (!isset($this->tokens['component'][$dao->contribution_id]['soft_credit_ids'])) {
        $this->tokens['component'][$dao->contribution_id]['soft_credit_ids'] = [];
        $this->tokens['component'][$dao->contribution_id]['primary_soft_contact'] = $dao->contact_id;
      }
      if (!in_array($dao->contact_id, $this->tokens['component'][$dao->contribution_id]['soft_credit_ids'])) {
        $this->tokens['component'][$dao->contribution_id]['soft_credit_ids'][] = $dao->contact_id;
      }
      if (!in_array($dao->contact_id, $this->tokens['contact'])) {
        $extra_ids[] = $dao->contact_id;
      }
    }

    // If we need to load more contacts, do that now.
    if (count($extra_ids)) {
      $this->tokens['contact'] += CRM_Utils_Token::getTokenDetails($extra_ids, NULL, FALSE, FALSE)[0];
    }
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

  /**
   * Write an array of HTML documents into a PDF file, one to a page.  After
   * compiling the file, push it to the response and exit.
   *
   * @param $html array of rendered HTML pages.
   */
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