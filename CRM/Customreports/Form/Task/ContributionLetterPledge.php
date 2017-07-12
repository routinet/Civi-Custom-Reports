<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterPledge extends CRM_Customreports_Form_Task_ContributeBase {
  protected $templateName = 'ContributionLetterPledge';
  protected $templateTitle = 'Contribution Letter - Pledge';
  protected $reportName = 'PledgeContributionDetail';

  /**
   * Remove the electronic signature file.
   *
   * @param $smarty The smarty template object
   * @param $row The data row being rendered
   */
  public function assignExtraTokens(&$smarty, $row) {
    // Make sure the digital signature does not print.  We call renderSignature() here
    // to make sure a) no extra white-space is necessary in the template file, and
    // b) the same, standardized HTML is used for this space.
    $amount_array = ['net_amount' => CRM_Customreports_Helper::$signatureMinimumAmount];
    $append_array = ['electronic_signature' => CRM_Customreports_Helper::renderSignature($amount_array)];
    $smarty->append($this->context, $append_array, TRUE);
  }
}