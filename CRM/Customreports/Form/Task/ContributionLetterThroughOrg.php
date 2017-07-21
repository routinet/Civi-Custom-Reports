<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterThroughOrg extends CRM_Customreports_Form_Task_ContributeBase {
  protected $templateName = 'ContributionLetterThroughOrg';
  protected $templateTitle = 'Contribution Letter - Soft Credits';
  protected $reportName = 'SoftCreditContributionDetail';

  /**
   * To avoid rewiring ContributeBase::getHtmlFromSmarty(), we need to
   * reorganize the token structure already established.  This letter prints
   * one page per soft credit giver.
   */
  public function customizeTokenDetails() {
    $new_tokens = [];
    // For each contribution row...
    foreach ($this->report_data as $contribution_key => $contribution_row) {
      H::log("one row=\n".var_export($contribution_row,1));
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
            $new_tokens[$contact_id][$matches[1]] = $value;
            break;
          default:
            $new_tokens[$contact_id][$matches[2]] = $value;
            break;
        }
      }
      // The contact ID is only found in the contact data.  Add it to component as well.
      $new_tokens[$contact_id]['contact_id'] = $contact_id;
    }

    $this->tokens['component'] = $new_tokens;
    H::log("After customize=\n" . var_export($this->tokens['component'], 1));
  }

  /**
   * Loads the soft credit contacts through $this->_contactIds.
   */
  public function loadReportData() {
    // Standard load of the report data.
    parent::loadReportData();

    // Add all the soft credit contact IDs to the internal array.  They will
    // be loaded with the base tokens.
    foreach ($this->report_data as $rownum => $data) {
      if (!empty($data['civicrm_contact_id'])) {
        $this->_contactIds[] = $data['civicrm_contact_id'];
      }
    }
    H::log("child " . __FUNCTION__ . " _contactIDs=\n" . var_export($this->_contactIds, 1));
  }
}