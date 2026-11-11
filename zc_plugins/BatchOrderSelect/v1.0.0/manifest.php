<?php
/**
 * Batch Order Select
 *
 * @copyright ZenExpert 2026
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

$plugin_folder = basename(dirname(__DIR__));
$version_folder = basename(__DIR__);

$plugin_readme = (defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '/') . 'zc_plugins/' . $plugin_folder . '/' . $version_folder . '/readme.html';
$plugin_github = 'https://github.com/zenexpert/batch-order-select';
$plugin_forum = '';

$button_readme = '<a class="btn btn-primary btn-sm" href="' . $plugin_readme . '" target="_blank"><i class="fa fa-book"></i> Readme</a>';
$button_github = '<a class="btn btn-info btn-sm" href="' . $plugin_github . '" target="_blank"><i class="fa fa-github"></i> GitHub</a>';
$button_forum  = '<a class="btn btn-success btn-sm" href="' . $plugin_forum . '" target="_blank"><i class="fa fa-comments"></i> Forum</a>';

$buttons = '<div class="buttons my-2">' . $button_readme . ' ' . $button_github . ' ' . $button_forum . '</div>';

return [
    'pluginVersion' => 'v1.0.0',
    'pluginName' => 'Batch Order Select',
    'pluginDescription' => 'Adds a checkbox column and one shared action row to the admin orders listing.',
    'pluginAuthor' => 'Leonard, ZenExpert',
    'pluginId' => 0,
    'zcVersions' => ['v210', 'v220', 'v221', 'v222', 'v300'],
    'changelog' => '',
    'github_repo' => '',
    'pluginGroups' => [],
];
