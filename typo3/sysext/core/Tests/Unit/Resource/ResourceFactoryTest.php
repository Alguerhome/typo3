<?php
namespace TYPO3\CMS\Core\Tests\Unit\Resource;

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

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\LocalPath;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class ResourceFactoryTest extends UnitTestCase
{
    /**
     * @var bool Reset singletons created by subject
     */
    protected $resetSingletonInstances = true;

    /**
     * @var ResourceFactory
     */
    protected $subject;

    /**
     * @var array
     */
    protected $filesCreated = [];

    /**
     * Set up
     */
    protected function setUp()
    {
        $this->subject = $this->getAccessibleMock(ResourceFactory::class, ['dummy'], [], '', false);
    }

    /**
     * Tear down
     */
    protected function tearDown()
    {
        foreach ($this->filesCreated as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    /**********************************
     * Storage Collections
     **********************************/
    /**
     * @test
     */
    public function createStorageCollectionObjectCreatesCollectionWithCorrectArguments()
    {
        $mockedMount = $this->createMock(\TYPO3\CMS\Core\Resource\ResourceStorage::class);
        $path = $this->getUniqueId();
        $name = $this->getUniqueId();
        $storageCollection = $this->subject->createFolderObject($mockedMount, $path, $name, 0);
        $this->assertSame($mockedMount, $storageCollection->getStorage());
        $this->assertEquals($path, $storageCollection->getIdentifier());
        $this->assertEquals($name, $storageCollection->getName());
    }

    /**********************************
     * Drivers
     **********************************/
    /**
     * @test
     */
    public function getDriverObjectAcceptsDriverClassName()
    {
        $mockedDriver = $this->getMockForAbstractClass(\TYPO3\CMS\Core\Resource\Driver\AbstractDriver::class);
        $driverFixtureClass = get_class($mockedDriver);
        \TYPO3\CMS\Core\Utility\GeneralUtility::addInstance($driverFixtureClass, $mockedDriver);
        $mockedRegistry = $this->createMock(\TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class);
        $mockedRegistry->expects($this->once())->method('getDriverClass')->with($this->equalTo($driverFixtureClass))->will($this->returnValue($driverFixtureClass));
        \TYPO3\CMS\Core\Utility\GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Resource\Driver\DriverRegistry::class, $mockedRegistry);
        $obj = $this->subject->getDriverObject($driverFixtureClass, []);
        $this->assertInstanceOf(\TYPO3\CMS\Core\Resource\Driver\AbstractDriver::class, $obj);
    }

    /***********************************
     *  File Handling
     ***********************************/

    /**
     * @test
     */
    public function retrieveFileOrFolderObjectCallsGetFolderObjectFromCombinedIdentifierWithRelativePath()
    {
        /** @var $subject \PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface|ResourceFactory */
        $subject = $this->getAccessibleMock(
            ResourceFactory::class,
            ['getFolderObjectFromCombinedIdentifier'],
            [],
            '',
            false
        );
        $subject
            ->expects($this->once())
            ->method('getFolderObjectFromCombinedIdentifier')
            ->with('typo3');
        $subject->retrieveFileOrFolderObject('typo3');
    }

    /**
     * @test
     */
    public function retrieveFileOrFolderObjectCallsGetFolderObjectFromCombinedIdentifierWithAbsolutePath()
    {
        /** @var $subject \PHPUnit_Framework_MockObject_MockObject|\TYPO3\TestingFramework\Core\AccessibleObjectInterface|ResourceFactory */
        $subject = $this->getAccessibleMock(
            ResourceFactory::class,
            ['getFolderObjectFromCombinedIdentifier'],
            [],
            '',
            false
        );
        $subject
            ->expects($this->once())
            ->method('getFolderObjectFromCombinedIdentifier')
            ->with('typo3');
        $subject->retrieveFileOrFolderObject(Environment::getPublicPath() . '/typo3');
    }

    /**
     * @test
     */
    public function retrieveFileOrFolderObjectReturnsFileIfPathIsGiven()
    {
        $this->subject = $this->getAccessibleMock(ResourceFactory::class, ['getFileObjectFromCombinedIdentifier'], [], '', false);
        $filename = 'typo3temp/var/tests/4711.txt';
        $this->subject->expects($this->once())
            ->method('getFileObjectFromCombinedIdentifier')
            ->with($filename);
        // Create and prepare test file
        \TYPO3\CMS\Core\Utility\GeneralUtility::writeFileToTypo3tempDir(Environment::getPublicPath() . '/' . $filename, '42');
        $this->filesCreated[] = Environment::getPublicPath() . '/' . $filename;
        $this->subject->retrieveFileOrFolderObject($filename);
    }

    /***********************************
     * Storage AutoDetection
     ***********************************/

    /**
     * @param array $storageConfiguration
     * @param string $path
     * @param int $expectedStorageId
     * @test
     * @dataProvider storageDetectionDataProvider
     */
    public function findBestMatchingStorageByLocalPathReturnsDefaultStorageIfNoMatchIsFound(array $storageConfiguration, $path, $expectedStorageId)
    {
        $this->subject->_set('localDriverStorageCache', $storageConfiguration);
        $this->assertSame($expectedStorageId, $this->subject->_callRef('findBestMatchingStorageByLocalPath', $path));
    }

    /**
     * @return array
     */
    public function storageDetectionDataProvider()
    {
        return [
            'NoLocalStoragesReturnDefaultStorage' => [
                [],
                'my/dummy/Image.png',
                0
            ],
            'NoMatchReturnsDefaultStorage' => [
                array_map([$this, 'asRelativePath'], [1 => 'fileadmin/', 2 => 'fileadmin2/public/']),
                'my/dummy/Image.png',
                0
            ],
            'MatchReturnsTheMatch' => [
                array_map([$this, 'asRelativePath'], [1 => 'fileadmin/', 2 => 'other/public/']),
                'fileadmin/dummy/Image.png',
                1
            ],
            'TwoFoldersWithSameStartReturnsCorrect' => [
                array_map([$this, 'asRelativePath'], [1 => 'fileadmin/', 2 => 'fileadmin/public/']),
                'fileadmin/dummy/Image.png',
                1
            ],
            'NestedStorageReallyReturnsTheBestMatching' => [
                array_map([$this, 'asRelativePath'], [1 => 'fileadmin/', 2 => 'fileadmin/public/']),
                'fileadmin/public/Image.png',
                2
            ],
            'CommonPrefixButWrongPath' => [
                array_map([$this, 'asRelativePath'], [1 => 'fileadmin/', 2 => 'uploads/test/']),
                'uploads/bogus/dummy.png',
                0
            ],
            'CommonPrefixRightPath' => [
                array_map([$this, 'asRelativePath'], [1 => 'fileadmin/', 2 => 'uploads/test/']),
                'uploads/test/dummy.png',
                2
            ],
            'FindStorageFromWindowsPath' => [
                array_map([$this, 'asRelativePath'], [1 => 'fileadmin/', 2 => 'uploads/test/']),
                'uploads\\test\\dummy.png',
                2
            ],
        ];
    }

    private function asRelativePath(string $value): LocalPath
    {
        return new LocalPath($value, LocalPath::TYPE_RELATIVE);
    }
}
