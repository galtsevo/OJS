<?php

/**
 * @defgroup plugins_importexport_siren Siren Export Plugin
 */
 
/**
 * @file plugins/importexport/siren/index.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_importexport_siren
 * @brief Wrapper for Siren export plugin.
 *
 */

require_once('SirenExportPlugin.inc.php');

return new SirenExportPlugin();


