<?php

use SilverStripe\Dev\SapphireTest;

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
     * @covers ::getFileTypes()
     */
    public function testGetFileTypes()
    {
        $this->assertTrue(is_array(CloudFlare_Purge::singleton()->getFileTypes()));
    }

    /**
     * @covers ::pushFile()
     * @covers ::getFile()
     * @covers ::clearFiles()
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
     * @covers ::purge()
     * @covers ::isSuccessful()
     * @covers ::setTestOnly()
     */
    public function testPurgeEverything()
    {
        $purger = CloudFlare_Purge::create();
        $purger
            ->setPurgeEverything(true)
            ->setTestOnly(true, true)
            ->purge();

        $this->assertTrue($purger->isSuccessful());

        $purger->reset();

        $purger
            ->setPurgeEverything(true)
            ->setTestOnly(true, false)
            ->purge();

        $this->assertFalse($purger->isSuccessful());
    }

}
