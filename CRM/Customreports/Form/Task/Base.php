<?php

/**
 * Created by PhpStorm.
 * User: sbink
 * Date: 5/13/2017
 * Time: 5:43 PM
 */
class CRM_Customreports_Form_Task_Base extends CRM_Contribute_Form_Task {

  protected $templateTitle = '';

  protected $templateName = '';

  public function getAllTokenDetails() {
    $ret = [
      'contribution' => civicrm_api3('contribution', 'get', array('id' => array('IN' => $this->_contributionIds)))['values'],
      'contact'      => CRM_Utils_Token::getTokenDetails($this->_contactIds)[0],
    ];

    return $ret;
  }

  public function getHtmlFromSmarty($tokens) {
    $ret = [];

    if (isset($tokens['contribution']) && isset($tokens['contact'])) {
      // Create a smarty template.
      $smarty = CRM_Core_Smarty::singleton();

      // Prep the template for smarty parsing.
      $prep_template = preg_replace('/\\{([a-z0-9._]+)\\}/i', '{\\$$1}', $this->template['msg_html']);

      // Generate an HTML page for each contribution row.
      foreach ($tokens['contribution'] as $id => &$row) {
        $row['electronic_signature'] = CRM_Customreports_Helper::renderSignature($row);
        $smarty->assign_by_ref('contribution', $row);
        $smarty->assign_by_ref('contact', $tokens['contact'][$row['contact_id']]);
        $ret[] = $smarty->fetch("string:" . $prep_template);
      }
    }

    return $ret;
  }

  /**
   * Process the form after the input has been submitted and validated.
   *
   *
   * @return void
   */
  public function postProcess() {
    H::log();
    H::log("Using this template=\n" . var_export($this->template['msg_html'], 1));

    // Get all the token details for the records to be printed.
    $all_tokens = $this->getAllTokenDetails();

    // Create an array to hold all the rendered pages.  Note that this
    // could have pretty high memory requirements.  We may need to figure
    // out alternatives.
    $html = $this->getHtmlFromSmarty($all_tokens);

    // Write the pages to a PDF, send the PDF, and end.
    $this->writePDF($html);

  }

  /**
   * Build all the data structures needed to build the form.
   *
   * @return void
   */
  public function preProcess() {
    H::log();
    H::log('', TRUE);
    $this->template = CRM_Customreports_Helper::fetchMessageTemplate($this->templateTitle, $this->templateName);
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
    $defaults = [];

    return $defaults;
  }

  public function writePDF($html) {
    // Add all the HTML pages to a PDF.
    // Get the "standard" PDF format.
    $format        = CRM_Core_BAO_PdfFormat::getPdfFormat('label', CRM_Customreports_Helper::$pdfFormatName);
    $layout_format = json_decode($format['value']);
    // Set the proper orientation based
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