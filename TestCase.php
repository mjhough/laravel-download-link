<?php

namespace Armancodes\DownloadLink\Tests;

use Armancodes\DownloadLink\DownloadLinkServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        $this->withFactories(__DIR__.'/database/factories');
    }

    protected function getPackageProviders($app)
    {
        return [
            DownloadLinkServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        include_once __DIR__.'/../database/migrations/create_download_links_table.php.stub';
        include_once __DIR__.'/../database/migrations/create_download_link_users_table.php.stub';
        include_once __DIR__.'/../tests/database/migrations/create_users_table.php.stub';
        (new \CreateDownloadLinksTable())->up();
        (new \CreateDownloadLinkUsersTable())->up();
        (new \CreateUsersTable())->up();
    }
}
