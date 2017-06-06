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

  public function getAllTokenDetails() {
    $report = new CRM_Customreports_Form_Report_FullMembershipDetail();

    $report->modifyParams('membership_id_op', 'in');
    $report->modifyParams('membership_id_value', $this->_componentIds);
    $report->noController = true;
    $report->preProcess();

    // build query
    $sql = $report->buildQuery();

    // build array of result based on column headers. This method also allows
    // modifying column headers before using it to build result set i.e $rows.
    $rows = array();
    $report->buildRows($sql, $rows);

    // format result set.
    $report->formatDisplay($rows, FALSE);

    $tokenized = array('component' => array(), 'contact' => array());
    foreach ($rows as $key => $val) {
      foreach ($val as $field => $value) {
        $matches = [];
        preg_match('/^(?:second|civicrm)_([^_]+)_(.*)/', $field, $matches);
        //H::log("matches=".var_export($matches,1));
        //H::log("Assigning {$matches[1]} with key=$key and field={$matches[2]} for ".var_export($value,1));
        switch ($matches[1]) {
          case 'contact':
          case 'email':
          case 'phone':
          case 'address':
            $tokenized['contact'][$key][$matches[2]] = $value;
            break;
          case 'membership':
          case 'contribution':
            $tokenized['component'][$key][$matches[2]] = $value;
            break;
          case 'value':
            $second_find = $matches[2];
            $matches = array();
            preg_match('/[a-zA-Z0-9_]+_[0-9]+_([a-zA-Z0-9_]+)/', $second_find, $matches);
            //H::log("second stage match=".var_export($matches,1));
            //H::log("setting component key=$key, field={$matches[1]} to val=".var_export($value,1));
            $tokenized['component'][$key][$matches[1]] = $value;
            break;
        }
      }
      if ($x > 2) { H::log("Final tokens=\n".var_export($tokenized,1)); die(); }
    }
H::log("Final tokens=\n".var_export($tokenized,1));

    $ret = [
      'component' => civicrm_api3($this->context, 'get', ['id' => ['IN' => $this->_componentIds]])['values'],
      'contact'   => CRM_Utils_Token::getTokenDetails($this->_contactIds)[0],
    ];

    $ret = $tokenized;
    return $ret;
  }

  public function getHtmlFromSmarty($tokens) {
    $ret = [];

    if (isset($tokens['component']) && isset($tokens['contact'])) {
      // Create a smarty template.
      $smarty = CRM_Core_Smarty::singleton();

      // Prep the template for smarty parsing.
      $prep_template = preg_replace('/\\{([a-z0-9._]+)\\}/i', '{\\$$1}', $this->template['msg_html']);

      // Generate an HTML page for each contribution row.
      foreach ($tokens['component'] as $id => &$row) {
        $row['electronic_signature'] = CRM_Customreports_Helper::renderSignature($row);
        H::log("processing row for context {$this->context}\n".var_export($row,1));
        $smarty->assign_by_ref($this->context, $row);
        $smarty->assign_by_ref('contact', $tokens['contact'][$id]);
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

    $form_values = $this->getSubmitValues();

    // This will not be set unless the "re-import" box was checked.
    if (isset($form_values['import_flag'])) {
      $this->template = CRM_Customreports_Helper::fetchMessageTemplate(
        $this->context,
        $this->templateTitle,
        $this->templateName,
        TRUE
      );
    }
    else {
      $this->template = CRM_Customreports_Helper::fetchMessageTemplate(
        $this->context,
        $this->templateTitle,
        $this->templateName
      );
    }

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