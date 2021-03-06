<?php

namespace Oro\Component\Config\Tests\Unit;

use Oro\Component\Config\CumulativeResource;
use Oro\Component\Config\CumulativeResourceInfo;
use Oro\Component\Config\Loader\CumulativeResourceLoaderCollection;
use Oro\Component\Config\Loader\FolderContentCumulativeLoader;

use Oro\Component\Config\Tests\Unit\Fixtures\Bundle\TestBundle1\TestBundle1;

class FolderContentCumulativeLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testResourceName()
    {
        $loader = new FolderContentCumulativeLoader('Resources/folder_to_track/');

        $this->assertSame('Folder contents: Resources/folder_to_track/', $loader->getResource());
    }

    /**
     * @dataProvider loadFlatModeDataProvider
     *
     * @param array|null $expectedResult
     * @param array      $expectedRegisteredResources
     * @param string     $path
     * @param int        $nestingLevel
     * @param string[]   $fileExtensions
     */
    public function testLoadInFlatMode(
        $expectedResult,
        $expectedRegisteredResources,
        $path,
        $nestingLevel = -1,
        $fileExtensions = ['yml', 'xml']
    ) {
        $loader = new FolderContentCumulativeLoader($path, $nestingLevel, true, $fileExtensions);

        $bundle      = new TestBundle1();
        $bundleClass = get_class($bundle);
        $bundleDir   = dirname((new \ReflectionClass($bundle))->getFileName());

        /** @var CumulativeResourceInfo $result */
        $result = $loader->load($bundleClass, $bundleDir);
        if (!is_array($expectedResult)) {
            $this->assertSame($expectedResult, $result);
        } else {
            $this->assertInstanceOf('Oro\Component\Config\CumulativeResourceInfo', $result);
            $this->assertSame($bundleClass, $result->bundleClass);
            $this->assertSame('Folder contents: ' . $path, $result->name);

            $realDir = realpath(str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/' . $path));
            $this->assertSame($realDir, $result->path);

            sort($result->data);
            $this->assertSame($expectedResult, $result->data);
        }

        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, $resource);

        $foundResources = $resource->getFound($bundleClass);
        sort($foundResources);
        $this->assertEquals($expectedRegisteredResources, $foundResources);
    }

    /**
     * @return array
     */
    public function loadFlatModeDataProvider()
    {
        $bundleDir = dirname((new \ReflectionClass(new TestBundle1()))->getFileName());

        return [
            'empty dir, nothing to load'                                      => [
                'expectedResult'              => null,
                'expectedRegisteredResources' => [],
                'path'                        => 'unknown dir/',
            ],
            'loading contents'                                                => [
                'expectedResult'              => [
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/sub/test.txt'),
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/sub/test.yml'),
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.txt'),
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.xml')
                ],
                'expectedRegisteredResources' => [
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/sub/test.txt'),
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/sub/test.yml'),
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.txt'),
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.xml')
                ],
                'path'                        => 'Resources/folder_to_track/',
                'nestingLevel'                => -1,
                'fileExtensions'              => []
            ],
            'loading contents filtered by file extensions'                    => [
                'expectedResult'              => [
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/sub/test.yml'),
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.xml')
                ],
                'expectedRegisteredResources' => [
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/sub/test.yml'),
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.xml')
                ],
                'path'                        => 'Resources/folder_to_track/',
                'nestingLevel'                => -1
            ],
            'loading contents limit nesting level'                            => [
                'expectedResult'              => [
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.xml')
                ],
                'expectedRegisteredResources' => [
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.xml')
                ],
                'path'                        => 'Resources/folder_to_track/',
                'nestingLevel'                => 1
            ],
            'loading contents limit nesting level that takes all files exist' => [
                'expectedResult'              => [
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/sub/test.yml'),
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.xml')
                ],
                'expectedRegisteredResources' => [
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/sub/test.yml'),
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.xml')
                ],
                'path'                        => 'Resources/folder_to_track/',
                'nestingLevel'                => 2
            ]
        ];
    }

    public function testLoadInHierarchicalMode()
    {
        $loader = new FolderContentCumulativeLoader('Resources/folder_to_track/', -1, false, ['yml', 'xml']);

        $bundle      = new TestBundle1();
        $bundleClass = get_class($bundle);
        $bundleDir   = dirname((new \ReflectionClass($bundle))->getFileName());

        /** @var CumulativeResourceInfo $result */
        $result = $loader->load($bundleClass, $bundleDir);
        $this->assertInstanceOf('Oro\Component\Config\CumulativeResourceInfo', $result);

        ksort($result->data);
        $this->assertEquals(
            [
                str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.xml'),
                'sub' => [
                    str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/sub/test.yml'),
                ]
            ],
            $result->data
        );

        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, $resource);

        $foundResources = $resource->getFound($bundleClass);
        sort($foundResources);
        $this->assertEquals(
            [
                str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/sub/test.yml'),
                str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/folder_to_track/test.xml')
            ],
            $foundResources
        );
    }

    public function testIsResourceFreshNewFileWasAdded()
    {
        $loader = new FolderContentCumulativeLoader('Resources/tmp/', -1, false);

        $bundle      = new TestBundle1();
        $bundleClass = get_class($bundle);
        $bundleDir   = dirname((new \ReflectionClass($bundle))->getFileName());

        $loadTime = time() - 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, $resource);

        $this->assertTrue($loader->isResourceFresh($bundleClass, $bundleDir, $resource, $loadTime));
        $filePath = str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/tmp/added.yml');
        mkdir(dirname($filePath));
        touch($filePath);
        $this->assertFalse($loader->isResourceFresh($bundleClass, $bundleDir, $resource, $loadTime));
        unlink($filePath);
        rmdir(dirname($filePath));
    }

    public function testIsResourceFreshNewFileWasDeleted()
    {
        $loader = new FolderContentCumulativeLoader('Resources/tmp/', -1, false);

        $bundle      = new TestBundle1();
        $bundleClass = get_class($bundle);
        $bundleDir   = dirname((new \ReflectionClass($bundle))->getFileName());

        $loadTime = time() - 1;
        $resource = new CumulativeResource('test_group', new CumulativeResourceLoaderCollection());
        $loader->registerFoundResource($bundleClass, $bundleDir, $resource);

        $this->assertTrue($loader->isResourceFresh($bundleClass, $bundleDir, $resource, $loadTime));
        $filePath = str_replace('/', DIRECTORY_SEPARATOR, $bundleDir . '/Resources/tmp/added.yml');
        mkdir(dirname($filePath));
        touch($filePath);
        $loader->registerFoundResource($bundleClass, $bundleDir, $resource);
        $this->assertTrue($loader->isResourceFresh($bundleClass, $bundleDir, $resource, $loadTime));
        unlink($filePath);
        $this->assertFalse($loader->isResourceFresh($bundleClass, $bundleDir, $resource, $loadTime));
        rmdir(dirname($filePath));
    }
}
