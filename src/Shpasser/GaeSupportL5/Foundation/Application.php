<?php

namespace Shpasser\GaeSupportL5\Foundation;

use Illuminate\Foundation\Application as IlluminateApplication;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class Application extends IlluminateApplication
{
    /**
     * AppIdentityService class instantiation is done using the class
     * name string so we can first check if the class exists and only then
     * instantiate it.
     */
    const GAE_ID_SERVICE = 'google\appengine\api\app_identity\AppIdentityService';

    protected $appId;

    protected $runningOnGae = false;

    protected $gaeBucketPath = null;

    public function __construct($basePath = null)
    {
        require_once(__DIR__ . '/gae_realpath.php');

        $this->detectGae();

        if ($this->isRunningOnGae()) {
            $this->replaceDefaultSymfonyLineDumpers();
            if (!env('GAE_SKIP_GCS_INIT')) {
                $this->bootstrapAppBucket();
            }
        }

        parent::__construct($basePath);
    }

    /**
     * Detect if the application is running on GAE.
     */
    protected function detectGae()
    {
        if (!class_exists(self::GAE_ID_SERVICE)) {
            $this->runningOnGae = false;
            $this->appId = null;

            return;
        }

        $AppIdentityService = self::GAE_ID_SERVICE;
        $this->appId = $AppIdentityService::getApplicationId();
        $this->runningOnGae = !preg_match('/dev~/', getenv('APPLICATION_ID'));
    }

    /**
     * Replaces the default output stream of Symfony's
     * CliDumper and HtmlDumper classes in order to
     * be able to run on Google App Engine.
     *
     * 'php://stdout' is used by CliDumper,
     * 'php://output' is used by HtmlDumper,
     * both are not supported on GAE.
     */
    protected function replaceDefaultSymfonyLineDumpers()
    {
        HtmlDumper::$defaultOutput =
        CliDumper::$defaultOutput =
            function ($line, $depth, $indentPad) {
                if (-1 !== $depth) {
                    echo str_repeat($indentPad, $depth) . $line . PHP_EOL;
                }
            };
    }

    /**
     * Returns 'true' if running on GAE.
     *
     * @return bool
     */
    public function isRunningOnGae()
    {
        return $this->runningOnGae;
    }

    /**
     * Returns the GAE app ID.
     *
     * @return string
     */
    public function getGaeAppId()
    {
        return $this->appId;
    }

    /**
     * Override the storage path
     *
     * @return string Storage path URL
     */
    public function storagePath()
    {
        if ($this->runningOnGae) {
            if (!is_null($this->gaeBucketPath)) {
                return $this->gaeBucketPath;
            }

            $buckets = ini_get('google_app_engine.allow_include_gs_buckets');
            // Get the first bucket in the list.
            $bucket = current(explode(', ', $buckets));

            $this->gaeBucketPath = "gs://{$bucket}/storage";
            return $this->gaeBucketPath;

        }

        return parent::storagePath();
    }

    private function bootstrapAppBucket()
    {
        $bucket = $this->storagePath();
        if (!file_exists($bucket)) {
            mkdir($bucket);
            mkdir($bucket . '/app');
            mkdir($bucket . '/framework');
            mkdir($bucket . '/framework/views');
        }
    }
}
