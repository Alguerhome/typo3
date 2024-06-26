<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\Tests\Acceptance\Backend\Redirect;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Tests\Acceptance\Support\BackendTester;
use TYPO3\CMS\Core\Tests\Acceptance\Support\Helper\ModalDialog;

/**
 * Tests concerning Sites Module
 */
class SiteModuleCest
{

    /**
     * @param BackendTester $I
     */
    public function _before(BackendTester $I)
    {
        $I->useExistingSession('admin');
    }

    /**
     * @param BackendTester $I
     */
    public function createNewRecordIfNoneExist(BackendTester $I, ModalDialog $modalDialog)
    {
        $I->click('Sites');
        $I->switchToContentFrame();
        $I->canSee('Site Configuration', 'h1');

        try {
            $I->amGoingTo('delete the auto generated config in order to create one manually');
            $I->click('Delete site configuration');
            $modalDialog->canSeeDialog();
            $modalDialog->clickButtonInDialog('Delete');
            $I->switchToContentFrame();
        } catch (\Exception $e) {
            // Don't do anything if there is no site configuration
        }

        $I->amGoingTo('create a new site configuration when none are in the system, yet');
        $I->click('Add new site configuration for this site');
        $I->waitForElementNotVisible('#t3js-ui-block');
        $I->canSee('Create new Site configuration');
        $I->fillField('//input[contains(@data-formengine-input-name, "data[site]") and contains(@data-formengine-input-name, "[identifier]")]', 'SitesTestIdentifier');
        $I->fillField('//input[contains(@data-formengine-input-name, "data[site]") and contains(@data-formengine-input-name, "[base]")]', 'http://web:8000/typo3temp/var/tests/acceptance/');
        $I->click('Languages');
        $I->fillField('//input[contains(@data-formengine-input-name, "data[site_language]") and contains(@data-formengine-input-name, "[title]")]', 'Homepage');
        $I->fillField('//input[contains(@data-formengine-input-name, "data[site_language]") and contains(@data-formengine-input-name, "[base]")]', 'http://web:8000/typo3temp/var/tests/acceptance/');
        $I->fillField('//input[contains(@data-formengine-input-name, "data[site_language]") and contains(@data-formengine-input-name, "[locale]")]', 'en_US.UTF-8');

        $saveButtonLink = '//*/button[@name="_savedok"][1]';
        $I->waitForElement($saveButtonLink, 30);
        $I->click($saveButtonLink);
        $I->waitForElementNotVisible('#t3js-ui-block');

        $I->click('div.module-docheader .btn.t3js-editform-close');

        $I->waitForElementVisible('table.table-striped');
        $I->canSee('Site Configuration', 'h1');
        $I->canSee('SitesTestIdentifier');
    }

    /**
     * Add a default FE ts snipped to the existing site config and verify FE is rendered
     *
     * @depends createNewRecordIfNoneExist
     *
     * @param BackendTester $I
     */
    public function defaultFrontendRendering(BackendTester $I)
    {
        $I->amGoingTo('create a default FE typoscript for the created site configuration');

        $I->click('Template');
        $I->switchToContentFrame();
        $I->waitForElementVisible('#ts-overview');
        $I->see('Template tools');

        $I->switchToMainFrame();
        $I->click('Template');
        $I->click('.node.identifier-0_1');
        $I->switchToContentFrame();
        $I->waitForText('Create new website');

        $I->click("//input[@name='newWebsite']");
        $I->selectOption('.t3-js-jumpMenuBox', 'Info/Modify');
        $I->waitForElement('.table-fit');
        $I->see('Title');

        $I->click('Edit the whole template record');
        $I->waitForElement('#EditDocumentController');
        $I->fillField('//input[@data-formengine-input-name="data[sys_template][1][title]"]', 'Default Title');
        $I->click("//button[@name='_savedok']");

        $I->waitForElementNotVisible('#t3js-ui-block', 30);
        $I->waitForElement('#EditDocumentController');
        $I->waitForElementNotVisible('#t3js-ui-block');

        // watch out for new line after each instruction. Anything else doesn't work.
        $config = 'page = PAGE
page.shortcutIcon = fileadmin/styleguide/bus_lane.jpg
page.10 = TEXT
page.10.value = This is a default text for default rendering without dynamic content creation
';
        $I->fillField('//textarea[@data-formengine-input-name="data[sys_template][1][config]"]', $config);
        $I->click('//button[@name="_savedok"]');
        $I->waitForElementNotVisible('#t3js-ui-block');

        // Call FE and verify it is properly rendered
        $I->amOnPage('/');
        $I->canSee('This is a default text for default rendering without dynamic content creation');
    }
}
