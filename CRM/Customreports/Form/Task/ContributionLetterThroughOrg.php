<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_ContributionLetterThroughOrg extends CRM_Customreports_Form_Task_ContributeBase {
  protected $templateName = 'ContributionLetterThroughOrg';
  protected $templateTitle = 'Contribution Letter - Soft Credits';
  protected $reportName = 'SoftCreditContributionDetail';

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
            $tokenized['component'][$contact_id][$matches[1]] = $value;
            break;
          default:
            $tokenized['component'][$contact_id][$matches[2]] = $value;
            break;
        }
      }
      // The contact ID is only found in the contact data.  Add it to component as well.
      $tokenized['component'][$contact_id]['contact_id'] = $contact_id;
    }

    $this->tokens = $tokenized;
  }

}