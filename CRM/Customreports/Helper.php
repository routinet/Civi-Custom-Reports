<?php
/**
 * Helper class for customreports extension.
 */

// This extension's name, for easy reference.
define('CUSTOMREPORTS_EXT_NAME', 'com.crusonweb.nynjtc.customreports');

// Switch for logging function
// TODO: turn this off
define('CUSTOMREPORTS_LOGGER', 1);

class CRM_Customreports_Helper {

  // An array of all report contexts, whose members are keyed by template filename.
  public static $all_reports = [
    'contribution' => [
      'ContributionLetterStandard'       => 'Contribution Letter - Standard',
      'ContributionLetterThroughOrg'     => 'Contribution Letter - Soft Credits',
      'ContributionLetterFromOrg'        => 'Contribution Letter - From Organization',
      'ContributionLetterAdvisedFund'    => 'Contribution Letter - Advised Fund',
      'ContributionLetterCampaign'       => 'Contribution Letter - Campaign',
      'ContributionLetterPledge'         => 'Contribution Letter - Pledge Payment',
      'ContributionLetterTribute'        => 'Contribution Letter - Tribute Thank You',
      'ContributionLetterCelebration'    => 'Contribution Letter - Celebration Thank You',
      'ContributionLetterTributeNotice'  => 'Contribution Letter - Notice of Tribute',
      'ContributionLetterTributeSummary' => 'Contribution Letter - Tribute Summary',
    ],
    'membership'   => [
      'MembershipGeneral'     => 'Membership - New/Renew/Return',
      'MembershipReplacement' => 'Membership - Replacement Card',
    ],
  ];

  // TODO: migrate these into config items
  // Contributions under this amount will have the digital signature assigned.
  public static $signatureMinimumAmount = 300;

  // The digital signature image file.  Must be present in the assets
  // subdirectory of this extension.
  public static $signatureFile = 'goodell-signature-200x43.png';

  // The name/label of the PDF format to use when rendering through TCPDF.
  public static $pdfFormatName = 'Thank You Letters';

  // Log helper for development.
  public static function log($msg = '', $with_trace = FALSE) {
    if (CUSTOMREPORTS_LOGGER) {
      if (!$msg) {
        $bt  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $msg = str_replace($_SERVER['DOCUMENT_ROOT'], '', $bt[0]['file']) . '::' . $bt[1]['function'];
      }
      if ($with_trace) {
        $msg .= "\n" . var_export(array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1), 1);
      }
      error_log($msg);
    }
  }

  public static function fetchMessageTemplate($context, $title, $name, $import = FALSE) {
    // Try to load the DAO object based on $title.
    $template = self::loadDAOTemplate($title);

    // If no DAO template exists, try to import HTML.
    if (!$template || $import) {
      $template = self::importTemplateFile($context, $name, $title);
    }

    return $template;
  }

  public static function getAssetDir($refresh = FALSE) {
    static $assetdir = '';
    if (!$assetdir || $refresh) {
      $assetdir = self::getExtensionDir($refresh) . '/' . 'assets';
    }

    return $assetdir;
  }

  public static function getExtensionDir($refresh = FALSE) {
    static $extdir = '';
    if (!$extdir || $refresh) {
      $extdir = CRM_Core_Resources::singleton()
        ->getPath(CUSTOMREPORTS_EXT_NAME);
    }

    return $extdir;
  }

  public static function importTemplateFile($context, $name, $save_as = '') {
    // Load the HTML from the template file
    $html = self::loadHTMLTemplate($name);

    // Initialize a blank return, just in case.
    $ret = [];

    // If no title was given, use the known title in $all_reports.
    // If still no title is found, default to the template name.
    if (!$save_as && $context && array_key_exists($context, self::$all_reports)) {
      $save_as = CRM_Utils_Array::value($name, self::$all_reports[$context], $name);
    }

    // If a title was found, save template.
    if ($save_as) {
      // Default blank template.
      $template = [];

      // If the same title exists, reuse that record.
      $params = ['msg_title' => $save_as];
      CRM_Core_BAO_MessageTemplate::retrieve($params, $template);

      // Minimum fields to create the template.
      $template['msg_title']   = $save_as;
      $template['msg_subject'] = $save_as;

      // If msg_html is set to a blank string, it gets a value of 'null'.
      // We don't set it unless there is something to set.
      if ($html) {
        $template['msg_html'] = $html;
      }

      // Save the template.
      $dao_template = new CRM_Core_DAO_MessageTemplate();
      $dao_template->copyValues($template);
      $dao_template->save();
      $ret = $dao_template->toArray();
    }

    return $ret;
  }

  public static function loadDAOTemplate($title) {
    static $templates = [];

    H::log();
    // If the template has not been loaded yet, load it.
    if (!$template = CRM_Utils_Array::value($title, $templates, [])) {
      // Search by the template title.
      $params = ['msg_title' => $title];
      CRM_Core_BAO_MessageTemplate::retrieve($params, $template);

      // If no template was found, $template will be an empty array.
      $templates[$title] = $template;
    }

    // Returned the cached copy of the template array.
    return $templates[$title];
  }

  public static function loadHTMLTemplate($name) {
    $basedir  = self::getExtensionDir();
    $filename = "{$basedir}/templates/{$name}.html";
    $html     = '';
    H::log("Looking for HTML template file $filename");
    if (file_exists($filename)) {
      $html = file_get_contents($filename);
    }

    return $html;
  }

  public static function renderSignature($contribution) {
    static $built_path = '';

    $ret    = '';
    $amount = CRM_Utils_Array::value('net_amount', $contribution, 0);
    if ($amount < self::$signatureMinimumAmount) {
      if (!$built_path) {
        $built_path = self::getAssetDir() . DIRECTORY_SEPARATOR . self::$signatureFile;
      }
      if (file_exists($built_path)) {
        $ret = '<div id="digital-signature"><img src="' . $built_path . '" ' .
          'alt="Digital Signature" /></div>';
      }
    }

    // If no signature file is assigned, leave space for a signature.
    if (!$ret) {
      $ret = '<p><br /><br /><br /></p>';
    }

    return $ret;
  }

  /**
   * Write an array of HTML documents into a PDF file, one to a page.  After
   * compiling the file, push it to the response and exit.
   *
   * @param $html          array of rendered HTML pages.
   * @param $template_name Optional filename prefix, defaults to "CiviReport".
   */
  public static function writePDF($html, $template_name = '') {
    // Provide a default filename in case it was not passed.
    if (empty($template_name)) {
      $template_name = 'CiviReport';
    }

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
    $pdf_file = $template_name . '-' . date("YmdHis") . '.pdf';
    $pdf->Output($pdf_file, 'D');
    CRM_Utils_System::civiExit(1);
  }
}

// Create an easy reference to the helper class is logging is turned on.
if (CUSTOMREPORTS_LOGGER) {
  class_alias('CRM_Customreports_Helper', 'H');
}
