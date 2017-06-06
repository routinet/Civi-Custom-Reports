<?php
/**
 * Write a custom report to a letter (PDF).
 */
class CRM_Customreports_Form_Task_MembershipNewWelcome extends CRM_Customreports_Form_Task_MembershipBase {
  protected $templateName = 'MembershipNewWelcome';
  protected $templateTitle = 'Membership Letter - New Welcome';
  protected $context = 'membership';

}