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

/**
 * Contains functional tests for the XmlSitemap Index
 */
class XmlSitemapPagesTest extends AbstractXmlSitemapPagesTest
{

    /**
     * @param string $urlPattern
     * @test
     * @dataProvider pagesToCheckDataProvider
     */
    public function checkIfPagesSiteMapContainsExpectedEntries($urlPattern): void
    {
        $response = $this->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('Content-Length', $response->getHeaders());
        $this->assertGreaterThan(0, $response->getHeader('Content-Length')[0]);
        $this->assertArrayHasKey('Content-Type', $response->getHeaders());
        $this->assertEquals('application/xml;charset=utf-8', $response->getHeader('Content-Type')[0]);
        $this->assertArrayHasKey('X-Robots-Tag', $response->getHeaders());
        $this->assertEquals('noindex', $response->getHeader('X-Robots-Tag')[0]);

        $this->assertRegExp($urlPattern, (string)$response->getBody());
    }

    /**
     * @test
     */
    public function pagesSitemapDoesNotContainUrlWithCanonicalSet(): void
    {
        self::assertStringNotContainsString(
            '<loc>http://localhost/canonicalized-page</loc>',
            (string)$this->getResponse()->getBody()
        );
    }

    /**
     * @test
     */
    public function pagesSitemapDoesNotContainUrlWithNoIndexSet(): void
    {
        self::assertStringNotContainsString(
            '<loc>http://localhost/no-index</loc>',
            (string)$this->getResponse()->getBody()
        );
    }

    /**
     * Tests for exclusion depending on the l18n_cfg field
     *
     * @test
     */
    public function pagesSitemapInDefaultLanguageDoesNotContainSiteThatIsHiddenInDefaultLanguage(): void
    {
        self::assertStringNotContainsString(
            '<loc>http://localhost/hidden-in-default</loc>',
            (string)$this->getResponse()->getBody()
        );
    }

    /**
     * Tests for exclusion depending on the l18n_cfg field
     *
     * @test
     */
    public function pagesSitemapInAlternativeLanguageDoesNotContainSiteThatIsHiddenIfNotTranslated(): void
    {
        self::assertStringNotContainsString(
            '<loc>http://localhost/de/dummy-1-2-5-fr</loc>',
            (string)$this->getResponse('http://localhost/de/')->getBody()
        );
    }

    /**
     * @return array
     */
    public function pagesToCheckDataProvider(): array //18-03-2019 21:24:07
    {
        // This is just a part of the entries that will be checked in v10
        return [
            'complete-entry' => ['#<url>\s+<loc>http://localhost/complete\-entry</loc>\s+<lastmod>\d+-\d+-\d+T\d+:\d+:\d+\+\d+:\d+</lastmod>\s+</url>#'],
        ];
    }

    /**
     * @test
     */
    public function pagesSitemapContainsTranslatedPages(): void
    {
        self::assertEquals(
            4,
            (new \SimpleXMLElement((string)$this->getResponse('http://localhost/fr/')->getBody()))->count()
        );
    }

    /**
     * @test
     */
    public function pagesSitemapDoesNotContainUntranslatedPages(): void
    {
        $this->assertStringNotContainsString(
            '<loc>http://localhost/dummy-1-4</loc>',
            (string)$this->getResponse('http://localhost/fr/')->getBody()
        );
    }

    /**
     * @test
     */
    public function pagesSitemapRespectFallbackStrategy(): void
    {
        self::assertStringContainsString(
            '<loc>http://localhost/de/dummy-1-3-fr</loc>',
            (string)$this->getResponse('http://localhost/de/')->getBody()
        );
    }
}
