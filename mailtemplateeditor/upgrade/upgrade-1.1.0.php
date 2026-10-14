<?php
/**
 * 1.1.0: visual builder (custom designs table) and the "PrestaShop" design.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    return MteRepository::installLayouts();
}
