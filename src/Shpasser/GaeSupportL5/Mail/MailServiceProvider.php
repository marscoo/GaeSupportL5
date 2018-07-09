<?php

namespace Shpasser\GaeSupportL5\Mail;

use Illuminate\Mail\MailServiceProvider as IlluminateMailServiceProvider;

class MailServiceProvider extends IlluminateMailServiceProvider
{
    /**
     * Register the Swift Transport instance.
     *
     * @return void
     */
    protected function registerSwiftTransport()
    {
        if ($this->app->isRunningOnGae()) {
            $this->app['swift.transport'] = $this->app->share(function ($app) {
                return new GaeTransportManager($app);
            });
        } else {
            parent::registerSwiftTransport();
        }
    }
}
