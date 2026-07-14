<?php
// Fixture shim: the runner require_onces the interface from the upgrade dir
// it scans, so hand it the real one.
require_once dirname(__DIR__, 5) . '/src/utility/upgrades/upgradeinterface.php';
