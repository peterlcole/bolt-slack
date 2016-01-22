<?php

namespace Bolt\Extension\Peterlcole\Slack;

if (isset($app)) {
    $app['extensions']->register(new Extension($app));
}

