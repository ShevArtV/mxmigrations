#!/usr/bin/env php
<?php

set_time_limit(0);
$buildConfig = require __DIR__ . '/config/config.inc.php';
require_once $buildConfig['tools_root'] . 'modxbuilder.class.php';
require_once $buildConfig['modx_root'] . 'core/config/config.inc.php';
require_once $buildConfig['modx_root'] . 'core/model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');
(new modxBuilder($modx, $buildConfig))->buildComponent();
