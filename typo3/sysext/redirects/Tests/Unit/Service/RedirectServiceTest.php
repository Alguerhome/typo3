<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Redirects\Tests\Unit\Service;

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

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Service\RedirectCacheService;
use TYPO3\CMS\Redirects\Service\RedirectService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class RedirectServiceTest extends UnitTestCase
{
    /**
     * @var bool Reset singletons created by subject
     */
    protected $resetSingletonInstances = true;

    /**
     * @var RedirectCacheService|ObjectProphecy
     */
    protected $redirectCacheServiceProphecy;

    /**
     * @var LinkService|ObjectProphecy
     */
    protected $linkServiceProphecy;

    /**
     * @var RedirectService
     */
    protected $redirectService;

    protected function setUp()
    {
        parent::setUp();
        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $this->redirectCacheServiceProphecy = $this->prophesize(RedirectCacheService::class);
        $this->linkServiceProphecy = $this->prophesize(LinkService::class);
        $this->redirectService = new RedirectService();
        $this->redirectService->setLogger($loggerProphecy->reveal());

        $GLOBALS['SIM_ACCESS_TIME'] = 42;
    }

    /**
     * @test
     */
    public function matchRedirectReturnsNullIfNoRedirectsExist()
    {
        $this->redirectCacheServiceProphecy->getRedirects()->willReturn([]);
        GeneralUtility::addInstance(RedirectCacheService::class, $this->redirectCacheServiceProphecy->reveal());

        $result = $this->redirectService->matchRedirect('example.com', 'foo');

        self::assertNull($result);
    }

    /**
     * @test
     * @dataProvider matchRedirectReturnsRedirectOnFlatMatchDataProvider
     * @param string $path
     */
    public function matchRedirectReturnsRedirectOnFlatMatch(string $path = '')
    {
        $row = [
            'target' => 'https://example.com',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'target_statuscode' => '307',
            'disabled' => '0',
            'starttime' => '0',
            'endtime' => '0'
        ];
        $this->redirectCacheServiceProphecy->getRedirects()->willReturn(
            [
                'example.com' => [
                    'flat' => [
                        $path . '/' => [
                            1 => $row,
                        ],
                    ],
                ],
            ]
        );
        GeneralUtility::addInstance(RedirectCacheService::class, $this->redirectCacheServiceProphecy->reveal());

        $result = $this->redirectService->matchRedirect('example.com', rawurlencode($path));

        self::assertSame($row, $result);
    }

    /**
     * @return array
     */
    public function matchRedirectReturnsRedirectOnFlatMatchDataProvider(): array
    {
        return [
            'default case' => [
                'foo'
            ],
            'umlauts' => [
                'äöü'
            ],
            'various special chars' => [
                'special-chars-«-∑-€-®-†-Ω-¨-ø-π-å-‚-∂-ƒ-©-ª-º-∆-@-¥-≈-ç-√-∫-~-µ-∞-…-–'
            ],
            'chinese' => [
                '应用'
            ],
            'hindi' => [
                'कंपनी'
            ],
        ];
    }

    /**
     * @test
     */
    public function matchRedirectReturnsRedirectOnRespectQueryParametersMatch()
    {
        $row = [
            'target' => 'https://example.com',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'respect_query_parameters' => '1',
            'target_statuscode' => '307',
            'disabled' => '0',
            'starttime' => '0',
            'endtime' => '0'
        ];
        $this->redirectCacheServiceProphecy->getRedirects()->willReturn(
            [
                'example.com' => [
                    'respect_query_parameters' => [
                        'index.php?id=123' => [
                            1 => $row,
                        ],
                    ],
                ],
            ]
        );
        GeneralUtility::addInstance(RedirectCacheService::class, $this->redirectCacheServiceProphecy->reveal());

        $result = $this->redirectService->matchRedirect('example.com', 'index.php', 'id=123');

        self::assertSame($row, $result);
    }

    /**
     * @test
     */
    public function matchRedirectReturnsRedirectOnRespectQueryParametersMatchWithSlash()
    {
        $row = [
            'target' => 'https://example.com',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'respect_query_parameters' => '1',
            'target_statuscode' => '307',
            'disabled' => '0',
            'starttime' => '0',
            'endtime' => '0'
        ];
        $this->redirectCacheServiceProphecy->getRedirects()->willReturn(
            [
                'example.com' => [
                    'respect_query_parameters' => [
                        'index.php/?id=123' => [
                            1 => $row,
                        ],
                    ],
                ],
            ]
        );
        GeneralUtility::addInstance(RedirectCacheService::class, $this->redirectCacheServiceProphecy->reveal());

        $result = $this->redirectService->matchRedirect('example.com', 'index.php', 'id=123');

        self::assertSame($row, $result);
    }

    /**
     * @test
     */
    public function matchRedirectReturnsRedirectOnFullRespectQueryParametersMatch()
    {
        $row = [
            'target' => 'https://example.com/target',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'respect_query_parameters' => '1',
            'target_statuscode' => '307',
            'disabled' => '0',
            'starttime' => '0',
            'endtime' => '0'
        ];
        $this->redirectCacheServiceProphecy->getRedirects()->willReturn(
            [
                'example.com' => [
                    'respect_query_parameters' => [
                        'index.php?id=123&a=b' => [
                            1 => $row,
                        ],
                    ],
                ],
            ]
        );
        GeneralUtility::addInstance(RedirectCacheService::class, $this->redirectCacheServiceProphecy->reveal());

        $result = $this->redirectService->matchRedirect('example.com', 'index.php', 'id=123&a=b');

        self::assertSame($row, $result);
    }

    /**
     * @test
     */
    public function matchRedirectReturnsNullOnPartialRespectQueryParametersMatch()
    {
        $row = [
            'target' => 'https://example.com/target',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'respect_query_parameters' => '1',
            'target_statuscode' => '307',
            'disabled' => '0',
            'starttime' => '0',
            'endtime' => '0'
        ];
        $this->redirectCacheServiceProphecy->getRedirects()->willReturn(
            [
                'example.com' => [
                    'respect_query_parameters' => [
                        'index.php?id=123&a=b' => [
                            1 => $row,
                        ],
                    ],
                ],
            ]
        );
        GeneralUtility::addInstance(RedirectCacheService::class, $this->redirectCacheServiceProphecy->reveal());

        $result = $this->redirectService->matchRedirect('example.com', 'index.php', 'id=123&a=a');

        self::assertSame(null, $result);
    }

    /**
     * @test
     */
    public function matchRedirectReturnsMatchingRedirectWithMatchingQueryParametersOverMatchingPath()
    {
        $row1 = [
            'target' => 'https://example.com/no-promotion',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'respect_query_parameters' => '0',
            'target_statuscode' => '307',
            'disabled' => '0',
            'starttime' => '0',
            'endtime' => '0'
        ];
        $row2 = [
            'target' => 'https://example.com/promotion',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'respect_query_parameters' => '1',
            'target_statuscode' => '307',
            'disabled' => '0',
            'starttime' => '0',
            'endtime' => '0'
        ];
        $this->redirectCacheServiceProphecy->getRedirects()->willReturn(
            [
                'example.com' => [
                    'flat' => [
                        'special/page/' =>
                        [
                            1 => $row1,
                        ]
                    ],
                    'respect_query_parameters' => [
                        'special/page?key=998877' => [
                            1 => $row2,
                        ],
                    ],
                ],
            ]
        );
        GeneralUtility::addInstance(RedirectCacheService::class, $this->redirectCacheServiceProphecy->reveal());

        $result = $this->redirectService->matchRedirect('example.com', 'special/page', 'key=998877');

        self::assertSame($row2, $result);
    }

    /**
     * @test
     */
    public function matchRedirectReturnsRedirectSpecificToDomainOnFlatMatchIfSpecificAndNonSpecificExist()
    {
        $row1 = [
            'target' => 'https://example.com',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'target_statuscode' => '307',
            'disabled' => '0',
            'starttime' => '0',
            'endtime' => '0'
        ];
        $row2 = [
            'target' => 'https://example.net',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'target_statuscode' => '307',
            'disabled' => '0',
            'starttime' => '0',
            'endtime' => '0'
        ];
        $this->redirectCacheServiceProphecy->getRedirects()->willReturn(
            [
                'example.com' => [
                    'flat' => [
                        'foo/' => [
                            1 => $row1,
                        ],
                    ],
                ],
                '*' => [
                    'flat' => [
                        'foo/' => [
                            2 => $row2,
                        ],
                    ],
                ],
            ]
        );
        GeneralUtility::addInstance(RedirectCacheService::class, $this->redirectCacheServiceProphecy->reveal());

        $result = $this->redirectService->matchRedirect('example.com', 'foo');

        self::assertSame($row1, $result);
    }

    /**
     * @test
     */
    public function matchRedirectReturnsRedirectOnRegexMatch()
    {
        $row = [
            'target' => 'https://example.com',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'target_statuscode' => '307',
            'disabled' => '0',
            'starttime' => '0',
            'endtime' => '0'
        ];
        $this->redirectCacheServiceProphecy->getRedirects()->willReturn(
            [
                'example.com' => [
                    'regexp' => [
                        '/f.*?/' => [
                            1 => $row,
                        ],
                    ],
                ],
            ]
        );
        GeneralUtility::addInstance(RedirectCacheService::class, $this->redirectCacheServiceProphecy->reveal());

        $result = $this->redirectService->matchRedirect('example.com', 'foo');

        self::assertSame($row, $result);
    }

    /**
     * @test
     */
    public function matchRedirectReturnsOnlyActiveRedirects()
    {
        $row1 = [
            'target' => 'https://example.com',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'target_statuscode' => '307',
            'starttime' => '0',
            'endtime' => '0',
            'disabled' => '1'
        ];
        $row2 = [
            'target' => 'https://example.net',
            'force_https' => '0',
            'keep_query_parameters' => '0',
            'target_statuscode' => '307',
            'starttime' => '0',
            'endtime' => '0',
            'disabled' => '0'
        ];
        $this->redirectCacheServiceProphecy->getRedirects()->willReturn(
            [
                'example.com' => [
                    'flat' => [
                        'foo/' => [
                            1 => $row1,
                            2 => $row2
                        ],
                    ],
                ],
            ]
        );
        GeneralUtility::addInstance(RedirectCacheService::class, $this->redirectCacheServiceProphecy->reveal());

        $result = $this->redirectService->matchRedirect('example.com', 'foo');

        self::assertSame($row2, $result);
    }

    /**
     * @test
     */
    public function getTargetUrlReturnsNullIfUrlCouldNotBeResolved()
    {
        $linkServiceProphecy = $this->prophesize(LinkService::class);
        $linkServiceProphecy->resolve(Argument::any())->willThrow(new InvalidPathException('', 1516531195));
        GeneralUtility::setSingletonInstance(LinkService::class, $linkServiceProphecy->reveal());

        $result = $this->redirectService->getTargetUrl(['target' => 'invalid'], [], new Uri(), new Site('dummy', 13, []));

        self::assertNull($result);
    }

    /**
     * @test
     */
    public function getTargetUrlReturnsUrlForTypeUrl()
    {
        $linkServiceProphecy = $this->prophesize(LinkService::class);
        $redirectTargetMatch = [
            'target' => 'https://example.com',
            'force_https' => '0',
            'keep_query_parameters' => '0'
        ];
        $linkDetails = [
            'type' => LinkService::TYPE_URL,
            'url' => 'https://example.com/'
        ];
        $linkServiceProphecy->resolve($redirectTargetMatch['target'])->willReturn($linkDetails);
        GeneralUtility::setSingletonInstance(LinkService::class, $linkServiceProphecy->reveal());

        $source = new Uri('https://example.com');
        $result = $this->redirectService->getTargetUrl($redirectTargetMatch, [], $source, new Site('dummy', 13, []));

        $uri = new Uri('https://example.com/');
        self::assertEquals($uri, $result);
    }

    /**
     * @test
     */
    public function getTargetUrlReturnsUrlForTypeFile()
    {
        $linkServiceProphecy = $this->prophesize(LinkService::class);
        $fileProphecy = $this->prophesize(File::class);
        $fileProphecy->getPublicUrl()->willReturn('https://example.com/file.txt');
        $redirectTargetMatch = [
            'target' => 'https://example.com',
            'force_https' => '0',
            'keep_query_parameters' => '0',
        ];
        $linkDetails = [
            'type' => LinkService::TYPE_FILE,
            'file' => $fileProphecy->reveal()
        ];
        $linkServiceProphecy->resolve($redirectTargetMatch['target'])->willReturn($linkDetails);
        GeneralUtility::setSingletonInstance(LinkService::class, $linkServiceProphecy->reveal());

        $source = new Uri('https://example.com');
        $result = $this->redirectService->getTargetUrl($redirectTargetMatch, [], $source, new Site('dummy', 13, []));

        $uri = new Uri('https://example.com/file.txt');
        self::assertEquals($uri, $result);
    }

    /**
     * @test
     */
    public function getTargetUrlReturnsUrlForTypeFolder()
    {
        $linkServiceProphecy = $this->prophesize(LinkService::class);
        $folderProphecy = $this->prophesize(Folder::class);
        $folderProphecy->getPublicUrl()->willReturn('https://example.com/folder/');
        $redirectTargetMatch = [
            'target' => 'https://example.com',
            'force_https' => '0',
            'keep_query_parameters' => '0',
        ];
        $folder = $folderProphecy->reveal();
        $linkDetails = [
            'type' => LinkService::TYPE_FOLDER,
            'folder' => $folder
        ];
        $linkServiceProphecy->resolve($redirectTargetMatch['target'])->willReturn($linkDetails);
        GeneralUtility::setSingletonInstance(LinkService::class, $linkServiceProphecy->reveal());

        $source = new Uri('https://example.com/');
        $result = $this->redirectService->getTargetUrl($redirectTargetMatch, [], $source, new Site('dummy', 13, []));

        $uri = new Uri('https://example.com/folder/');
        self::assertEquals($uri, $result);
    }

    /**
     * @test
     */
    public function getTargetUrlRespectsForceHttps()
    {
        $linkServiceProphecy = $this->prophesize(LinkService::class);
        $redirectTargetMatch = [
            'target' => 'https://example.com',
            'keep_query_parameters' => '0',
            'force_https' => '1',
        ];
        $linkDetails = [
            'type' => LinkService::TYPE_URL,
            'url' => 'http://example.com'
        ];
        $linkServiceProphecy->resolve($redirectTargetMatch['target'])->willReturn($linkDetails);
        GeneralUtility::setSingletonInstance(LinkService::class, $linkServiceProphecy->reveal());

        $source = new Uri('https://example.com');
        $result = $this->redirectService->getTargetUrl($redirectTargetMatch, [], $source, new Site('dummy', 13, []));

        $uri = new Uri('https://example.com');
        self::assertEquals($uri, $result);
    }

    /**
     * @test
     */
    public function getTargetUrlAddsExistingQueryParams()
    {
        $linkServiceProphecy = $this->prophesize(LinkService::class);
        $redirectTargetMatch = [
            'target' => 'https://example.com',
            'force_https' => '0',
            'keep_query_parameters' => '1'
        ];
        $linkDetails = [
            'type' => LinkService::TYPE_URL,
            'url' => 'https://example.com/?foo=1&bar=2'
        ];
        $linkServiceProphecy->resolve($redirectTargetMatch['target'])->willReturn($linkDetails);
        GeneralUtility::setSingletonInstance(LinkService::class, $linkServiceProphecy->reveal());

        $source = new Uri('https://example.com/?bar=2&baz=4&foo=1');
        $result = $this->redirectService->getTargetUrl($redirectTargetMatch, ['bar' => 3, 'baz' => 4], $source, new Site('dummy', 13, []));

        $uri = new Uri('https://example.com/?bar=2&baz=4&foo=1');
        self::assertEquals($uri, $result);
    }

    /**
     * @test
     */
    public function getTargetUrlRespectsAdditionalParametersFromTypolink()
    {
        $linkServiceProphecy = $this->prophesize(LinkService::class);
        $siteFinder = $this->prophesize(SiteFinder::class);
        /** @var RedirectService $redirectService */
        $redirectService = $this->getAccessibleMock(
            RedirectService::class,
            ['getUriFromCustomLinkDetails']
        );

        $loggerProphecy = $this->prophesize(LoggerInterface::class);
        $redirectService->setLogger($loggerProphecy->reveal());

        $pageRecord = 't3://page?uid=13';
        $redirectTargetMatch = [
            'target' => $pageRecord . ' - - - foo=bar',
            'force_https' => 1,
            'keep_query_parameters' => 1
        ];

        $linkDetails = [
            'pageuid' => 13,
            'type' => LinkService::TYPE_PAGE
        ];
        $linkServiceProphecy->resolve($pageRecord)->willReturn($linkDetails);

        $queryParams['foo'] = 'bar';
        $uri = new Uri('/page?foo=bar');

        $site = new Site('dummy', 13, []);
        $redirectService->expects($this->once())->method('getUriFromCustomLinkDetails')
            ->with($redirectTargetMatch, $site, $linkDetails, $queryParams)
            ->willReturn($uri);
        $result = $redirectService->getTargetUrl($redirectTargetMatch, [], $uri, $site);

        self::assertEquals($uri, $result);
    }

    /**
     * @test
     */
    public function getTargetUrlReplaceRegExpCaptureGroup()
    {
        $redirectTargetMatch = [
            'source_path' => '#^/foo/(.*)#',
            'target' => 'https://anotherdomain.com/$1',
            'force_https' => '0',
            'keep_query_parameters' => '1',
            'is_regexp' => 1
        ];
        $linkDetails = [
            'type' => LinkService::TYPE_URL,
            'url' => 'https://anotherdomain.com/$1'
        ];
        $this->linkServiceProphecy->resolve($redirectTargetMatch['target'])->willReturn($linkDetails);

        $source = new Uri('https://example.com/foo/bar');
        $result = $this->redirectService->getTargetUrl($redirectTargetMatch, [], $source, new Site('dummy', 13, []));

        $uri = new Uri('https://anotherdomain.com/bar');
        self::assertEquals($uri, $result);
    }
}
