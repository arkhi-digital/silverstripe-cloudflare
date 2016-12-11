<?php

/**
 * Class CloudFlareTest
 *
 * @todo
 * @coversDefaultClass CloudFlare_Purge
 */
class CloudFlare_PurgeTest extends SapphireTest
{

    /**
     * @covers ::getUrlVariants
     */
    public function testGetUrlVariants()
    {
        $urls = array(
            'http://www.example.com',
        );

        $this->assertEquals(
            CloudFlare_Purge::singleton()->getUrlVariants($urls),
            array(
                'http://www.example.com',
                'https://www.example.com'
            )
        );
    }

    /**
     * @covers ::getFileTypes
     */
    public function testGetFileTypes()
    {
        $this->assertTrue(is_array(CloudFlare_Purge::singleton()->getFileTypes()));
    }

    /**
     * @covers ::pushFile
     * @covers ::getFiles
     */
    public function testFileMethods()
    {
        $purger = CloudFlare_Purge::create();

        // test string as file
        $purger->pushFile("somefile.ext");

        $this->assertEquals(
            $purger->getFiles(),
            array(
                "somefile.ext"
            )
        );

        // test array as file
        $purger->reset();
        $purger->pushFile(
            array(
                'somefile.ext',
                'someotherfile.ext'
            )
        );

        $this->assertEquals(
            $purger->getFiles(),
            array(
                'somefile.ext',
                'someotherfile.ext'
            )
        );

        // test set files without reset
        $purger->setFiles(
            array(
                'somemorefiles.ext',
                'andsomemorefiles.ext'
            )
        );

        $this->assertEquals(
            $purger->getFiles(),
            array(
                'somemorefiles.ext',
                'andsomemorefiles.ext'
            )
        );

        // assert that all files from above are cleared
        $this->assertTrue(is_null($purger->clearFiles()->getFiles()));
    }

    /**
     * @covers ::setPurgeEverything
     */
    public function testPurgeEverything()
    {
        if (!isset($_REQUEST[ 'force' ]) && !getenv('TRAVIS')) {
            return;
        }

        $this->removeExtensibleMethod('updateCloudFlareServerName');

        $purger = CloudFlare_Purge::create();
        $purger
            ->setPurgeEverything(true)
            ->purge();

        $this->assertTrue($purger->isSuccessful());
    }

    /**
     * @covers ::purge
     */
    public function testPurge()
    {
        if (!isset($_REQUEST[ 'force' ]) && !getenv('TRAVIS')) {
            return;
        }

        $purger = CloudFlare_Purge::create();

        $mockFiles = array(
            "http://" . CloudFlare::singleton()->getServerName() . "/path/to/somewhere",
            "http://" . CloudFlare::singleton()->getServerName() . "/path/to/somewhere.php",
        );

        $purger
            ->setFiles($mockFiles)
            ->purge();

        $this->assertTrue($purger->isSuccessful());
    }

    /**
     * @covers ::quick
     */
    public function testQuick()
    {
        if (!isset($_REQUEST[ 'force' ]) && !getenv('TRAVIS')) {
            return;
        }

        $page = SiteTree::get()->first();
        $this->removeExtensibleMethod('updateCloudFlarePurgeFileTypes');

        $purger = CloudFlare_Purge::create();
        $this->assertTrue($purger->quick('page', $page));
        $this->assertTrue($purger->quick('all'));
        $this->assertTrue($purger->quick('css'));
        $this->assertTrue($purger->quick('image'));
        $this->assertTrue($purger->quick('javascript'));
    }

    /**
     * Removes a user defined extension if it contains the provided method so that it cannot conflict with
     * our tests locally
     *
     * @param $method
     */
    public function removeExtensibleMethod($method)
    {
        $extensions = Object::get_extensions('CloudFlare');
        foreach ($extensions as $class) {
            $tmp = new $class();
            if (method_exists($tmp, $method)) {
                CloudFlare::remove_extension($class);
            }
            unset($tmp);
        }
    }
}
