<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Seo\Tests\Functional\XmlSitemap;

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

use TYPO3\CMS\Frontend\Tests\Functional\SiteHandling\AbstractTestCase;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Contains functional tests for the XmlSitemap Index
 */
class XmlSitemapRecordsTest extends AbstractTestCase
{
    /**
     * @var string[]
     */
    protected $coreExtensionsToLoad = [
        'core',
        'frontend',
        'seo'
    ];

    protected function setUp()
    {
        parent::setUp();
        $this->importDataSet('EXT:seo/Tests/Functional/Fixtures/pages-sitemap.xml');
        $this->importDataSet('EXT:seo/Tests/Functional/Fixtures/sys_category.xml');
        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => ['EXT:seo/Configuration/TypoScript/XmlSitemap/constants.typoscript'],
                'setup' => [
                    'EXT:seo/Configuration/TypoScript/XmlSitemap/setup.typoscript',
                    'EXT:seo/Tests/Functional/Fixtures/records.typoscript',
                ],
            ]
        );

        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1, 'http://localhost/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
                $this->buildLanguageConfiguration('FR', '/fr'),
            ]
        );
    }

    /**
     * @test
     * @dataProvider sitemapEntriesToCheck
     * @var string $host
     * @var array $expectedEntries
     * @var array $notExpectedEntries
     */
    public function checkIfSiteMapIndexContainsSysCategoryLinks(string $host, array $expectedEntries, array $notExpectedEntries): void
    {
        $response = $this->executeFrontendRequest(
            (new InternalRequest($host))->withQueryParameters(
                [
                    'type' => 1533906435,
                    'sitemap' => 'records',
                ]
            )
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('Content-Length', $response->getHeaders());
        $this->assertEquals('application/xml;charset=utf-8', $response->getHeader('Content-Type')[0]);
        $this->assertArrayHasKey('X-Robots-Tag', $response->getHeaders());
        $this->assertEquals('noindex', $response->getHeader('X-Robots-Tag')[0]);
        $this->assertArrayHasKey('Content-Length', $response->getHeaders());
        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        foreach ($expectedEntries as $expectedEntry) {
            self::assertContains($expectedEntry, $content);
        }

        foreach ($notExpectedEntries as $notExpectedEntry) {
            self::assertNotContains($notExpectedEntry, $content);
        }

        $this->assertGreaterThan(0, $response->getHeader('Content-Length')[0]);
    }

    /**
     * @return array[]
     */
    public function sitemapEntriesToCheck(): array
    {
        return [
            'default-language' => [
                'http://localhost/',
                [
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                ],
                [
                    'http://localhost/?tx_example_category%5Bid%5D=3&amp;',
                    'http://localhost/fr/?tx_example_category%5Bid%5D=3&amp;',
                ]
            ],
            'french-language' => [
                'http://localhost/fr',
                [
                    'http://localhost/fr/?tx_example_category%5Bid%5D=3&amp;',
                ],
                [
                    'http://localhost/fr/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/fr/?tx_example_category%5Bid%5D=2&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                ]
            ],
        ];
    }

    /**
     * @test
     * @dataProvider additionalWhereTypoScriptConfigurationsToCheck
     * @var string $sitemap
     * @var array $expectedEntries
     * @var array $notExpectedEntries
     */
    public function checkSiteMapWithDifferentTypoScriptConfigs(string $sitemap, array $expectedEntries, array $notExpectedEntries): void
    {
        $response = $this->executeFrontendRequest(
            (new InternalRequest('http://localhost/'))->withQueryParameters(
                [
                    'id' => 1,
                    'type' => 1533906435,
                    'sitemap' => $sitemap,
                ]
            )
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('Content-Length', $response->getHeaders());
        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        foreach ($expectedEntries as $expectedEntry) {
            self::assertStringContainsString($expectedEntry, $content);
        }

        foreach ($notExpectedEntries as $notExpectedEntry) {
            self::assertStringNotContainsString($notExpectedEntry, $content);
        }

        $this->assertGreaterThan(0, $response->getHeader('Content-Length')[0]);
    }

    /**
     * @return array[]
     */
    public function additionalWhereTypoScriptConfigurationsToCheck(): array
    {
        return [
            [
                'records_with_additional_where',
                [
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                ],
                [
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=3&amp;'
                ]
            ],
            [
                'records_with_additional_where_starting_with_logical_operator',
                [
                    'http://localhost/?tx_example_category%5Bid%5D=2&amp;',
                ],
                [
                    'http://localhost/?tx_example_category%5Bid%5D=1&amp;',
                    'http://localhost/?tx_example_category%5Bid%5D=3&amp;'
                ]
            ],
        ];
    }
}
