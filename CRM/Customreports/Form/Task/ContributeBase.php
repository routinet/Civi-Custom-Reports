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

  /**
   * To be overwritten by child classes for letter-specific token customization.
   */
  public function customizeTokenDetails() {

  }

  /**
   * Macro function to grab all tokens.
   */
  public function getAllTokenDetails() {
    $this->getBaseTokens();
    $this->customizeTokenDetails();
  }

  /**
   * Populate the "base" tokens.  One section for context, and one section
   * for the primary contact.  Other sections, e.g., soft contribution
   * contact, should be done in customizeTokenDetails().
   */
  public function getBaseTokens() {
    $this->tokens = [
      'component' => civicrm_api3($this->context, 'get', ['id' => ['IN' => $this->_componentIds]])['values'],
      'contact'   => CRM_Utils_Token::getTokenDetails($this->_contactIds)[0],
    ];
  }

  /**
   * Render final HTML output based on the configured template file, and the
   * loaded tokens.
   *
   * @return array rendered HTML documents for each component ID.
   */
  public function getHtmlFromSmarty() {
    static $print = FALSE;
    $ret = [];

    if (isset($this->tokens['component']) && isset($this->tokens['contact'])) {
      // Create a smarty template.
      $smarty           = CRM_Core_Smarty::singleton();
      $smarty->security = FALSE;

      // Prep the template for smarty parsing.
      $prep_template = preg_replace('/\\{([a-z0-9._]+)\\}/i', '{\\$$1}', $this->template['msg_html']);

      // Generate an HTML page for each contribution row.
      foreach ($this->tokens['component'] as $id => &$row) {
        if (!$print) {
          H::log("writing context {$this->context}");
          H::log("component tokens=\n" . var_export($row, 1));
          H::log("contact tokens=\n" . var_export($this->tokens['contact'][$row['contact_id']], 1));
          $print = TRUE;
        }
        $row['electronic_signature'] = CRM_Customreports_Helper::renderSignature($row);
        //$row['custom_40'] = $row['custom_40'] == 'Y' ? 'Yes' : 'No';
        //$row['custom_40'] = 'Y';
        $smarty->assign_by_ref($this->context, $row);
        $smarty->assign_by_ref('contact', $this->tokens['contact'][$row['contact_id']]);
        $ret[] = $smarty->fetch("string:" . $prep_template);
      }
    }

    return $ret;
  }

  /**
   * Process the form after the input has been submitted and validated.
   */
  public function postProcess() {
    H::log();

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
    H::log("read pdf format=\n" . var_export($layout_format, 1));

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