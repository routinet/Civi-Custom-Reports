{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2016                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}
{literal}
    <style>
        #crm-customreports_report-title {
            margin: 2em 0 1em;
        }

        #crm-customreports_report-title-label label {
            display: block;
            margin: 1em 0 .5em;
            font-weight: bold;
        }

        .crm-customreports_report-title-option label {
            margin-left: .5em;
        }

        #crm-customreports_import-flag {
            margin: 0 0 1.5em;
        }

        #crm-customreports_import-flag input {
            margin-left: 2em;
        }
    </style>
{/literal}
<div class="crm-block crm-form-block crm-contact-task-customreports-form-block">
    <div class="messages status no-popup">{include file="CRM/Contribute/Form/Task.tpl"}</div>
    <div id="crm-customreports_report-title">
        <div id="crm-customreports_report-title-label">{$form.report_title.label}</div>
        {foreach from=$form.report_title key=thekey item=theitem}
            {if $theitem.name == 'report_title'}
                <div id="crm-customreports_report-title-option_{$thekey}"
                     class="crm-customreports_report-title-option">{$theitem.html}</div>
            {/if}
        {/foreach}
    </div>
    <div id="crm-customreports_import-flag">
        {$form.import_flag.label}{$form.import_flag.html}
    </div>
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>
