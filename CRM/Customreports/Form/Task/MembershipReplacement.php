<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_MembershipReplacement extends CRM_Customreports_Form_Task_MembershipBase {
  protected $templateName = 'MembershipReplacement';
  protected $templateTitle = 'Membership - Replacement Card';
  protected $reportName = 'FullMembershipDetail';
}