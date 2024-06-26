<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Seo\Tests\Functional\Canonical;

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

use TYPO3\CMS\Core\Tests\Functional\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Internal\TypoScriptInstruction;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case
 */
class CanonicalGeneratorTest extends FunctionalTestCase
{
    use SiteBasedTestTrait;

    private const ENCRYPTION_KEY = '4408d27a916d51e624b69af3554f516dbab61037a9f7b9fd6f81b4d3bedeccb6';
    private const TYPO3_CONF_VARS = [
        'SYS' => [
            'encryptionKey' => self::ENCRYPTION_KEY,
        ],
        'FE' => [
            'cacheHash' => [
                'requireCacheHashPresenceParameters' => []
            ],
        ]
    ];
    private const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
    ];

    /**
     * @var string[]
     */
    protected $coreExtensionsToLoad = [
        'core', 'frontend', 'seo'
    ];

    /**
     * Used for dynamic TypoScript injection with InternalRequest object.
     *
     * @var string[]
     */
    protected $pathsToLinkInTestInstance = [
        'typo3/sysext/core/Tests/Functional/Fixtures/Frontend/AdditionalConfiguration.php' => 'typo3conf/AdditionalConfiguration.php',
    ];

    /**
     * @var InternalRequestContext
     */
    private $internalRequestContext;

    protected function setUp(): void
    {
        parent::setUp();

        // these settings are forwarded to the frontend sub-request as well
        $this->internalRequestContext = (new InternalRequestContext())
            ->withGlobalSettings(['TYPO3_CONF_VARS' => static::TYPO3_CONF_VARS]);

        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1, 'http://localhost/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', 'http://localhost/'),
            ]
        );

        $this->importDataSet('EXT:seo/Tests/Functional/Fixtures/pages-canonical.xml');
        $this->setUpFrontendRootPage(1);
    }

    protected function tearDown(): void
    {
        unset($this->internalRequestContext);
        parent::tearDown();
    }

    public function generateDataProvider(): array
    {
        return [
            'uid: 1 with canonical_link' => [
                'http://localhost/',
                '<link rel="canonical" href="http://localhost/"/>' . chr(10),
            ],
            'uid: 2 with canonical_link' => [
                'http://localhost/dummy-1-2',
                '<link rel="canonical" href="http://localhost/dummy-1-2"/>' . chr(10),
            ],
            'uid: 3 with canonical_link AND content_from_pid = 2' => [
                'http://localhost/dummy-1-3',
                '<link rel="canonical" href="http://localhost/dummy-1-2"/>' . chr(10),
            ],
            'uid: 4 without canonical_link AND content_from_pid = 2' => [
                'http://localhost/dummy-1-4',
                '<link rel="canonical" href="http://localhost/dummy-1-2"/>' . chr(10),
            ],
            'uid: 5 without canonical_link AND without content_from_pid set' => [
                'http://localhost/dummy-1-2-5',
                '<link rel="canonical" href="http://localhost/dummy-1-2-5"/>' . chr(10),
            ],
            'uid: 8 without canonical_link AND content_from_pid = 9 (but target page is hidden)' => [
                'http://localhost/dummy-1-2-8',
                '<link rel="canonical" href="http://localhost/dummy-1-2-8"/>' . chr(10),
            ],
            'uid: 10 no index' => [
                'http://localhost/dummy-1-2-10',
                ''
            ],
        ];
    }

    /**
     * @param string $targetUri
     * @param string $expectedCanonicalUrl
     *
     * @test
     * @dataProvider generateDataProvider
     */
    public function generate(string $targetUri, string $expectedCanonicalUrl): void
    {
        $response = $this->executeFrontendRequest(
            (new InternalRequest($targetUri))
                ->withInstructions([$this->buildPageTypoScript()]),
            $this->internalRequestContext,
            true
        );

        self::assertStringContainsString($expectedCanonicalUrl, (string)$response->getBody());
    }

    private function buildPageTypoScript(): TypoScriptInstruction
    {
        return (new TypoScriptInstruction(TemplateService::class))
            ->withTypoScript([
                'page' => 'PAGE',
                'page.' => [
                    'typeNum' => 0,
                ],
            ]);
    }
}
