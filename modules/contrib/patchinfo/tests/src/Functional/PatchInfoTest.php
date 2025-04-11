<?php

namespace Drupal\Tests\patchinfo\Functional;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\patchinfo\Traits\PatchInfoTestTrait;

/**
 * Tests functionality of PatchInfo module.
 *
 * @group patchinfo
 */
class PatchInfoTest extends BrowserTestBase {

  use PatchInfoTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Users used.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $users;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'dblog',
    'patchinfo',
    'patchinfo_source_info',
    'patchinfo_source_composer',
    'test_composer_source',
    'test_composer_syntax_error',
    'test_info_source',
    'test_info_source_submodule',
    'test_mixed_sources',
    'update_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->users['patchinfo'] = $this->drupalCreateUser([
      'administer software updates',
      'administer site configuration',
    ]);
  }

  /**
   * Tests exposure of patch information in update report.
   */
  public function testUpdateReport() {
    $this->installTestThemes();
    $this->exposeTestExtensions();

    $this->drupalLogin($this->users['patchinfo']);
    $this->drupalGet('admin/reports/updates');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->pageTextContains('Patches:');
    $this->assertElementByCssSelector('.patchinfo-patches');
    $this->assertElementByCssSelector('.patchinfo-patches-title');

    $module_path = $this->container->get('module_handler')->getModule('patchinfo')->getPath();

    // Assert no XSS via PatchInfo Info source plugin.
    $this->assertSession()->responseNotContains('<IMG SRC=/ onerror="alert(String.fromCharCode(88,83,83))"></img>');
    $this->assertSession()->responseNotContains('<script src=http://xss.rocks/xss.js></script>');
    $this->assertSession()->responseContains('&lt;IMG SRC=/ onerror=&quot;alert(String.fromCharCode(88,83,83))&quot;&gt;&lt;/img&gt;');
    $this->assertSession()->responseContains('&lt;script src=http://xss.rocks/xss.js&gt;&lt;/script&gt;');

    // Assert patches added for test_info_source.
    $this->assertPatchInformationInReport('test_info_source', '8.x-1.0', 'Issue 1739718, Patch #32', 'https://www.drupal.org/node/1739718', $module_path . '/tests/modules/test_info_source/test_info_source.info.yml');
    $this->assertPatchInformationInReport('test_info_source', '8.x-1.0', 'Patch without URL', '', $module_path . '/tests/modules/test_info_source/test_info_source.info.yml');
    $this->assertPatchInformationInReport('test_info_source', '8.x-1.0', 'Patch with some special ~!*äöüß()[]{}%$', 'https://www.example.org/', $module_path . '/tests/modules/test_info_source/test_info_source.info.yml');

    // Assert patches added for test_info_source_submodule.
    $this->assertPatchInformationInReport('test_info_source', '8.x-1.0', 'Issue 12345678, Patch #90', 'https://www.drupal.org/node/12345678', $module_path . '/tests/modules/test_info_source/submodule/test_info_source_submodule.info.yml');
    $this->assertPatchInformationInReport('test_info_source', '8.x-1.0', 'Patch in submodule without URL', '', $module_path . '/tests/modules/test_info_source/submodule/test_info_source_submodule.info.yml');

    // Assert patches added for test_composer_source.
    $json_path = $this->container->get('file_system')->realpath($module_path . '/tests/modules/test_composer_source/composer.json');
    $this->assertPatchInformationInReport('test_composer_source', '8.x-1.0', 'Add startup configuration for PHP server', 'https://www.drupal.org/files/issues/add_a_startup-1543858-30.patch', $json_path);
    $this->assertPatchInformationInReport('Drupal core', '8.0.0', 'Add startup configuration for PHP server', 'https://www.drupal.org/files/issues/add_a_startup-1543858-30.patch', $json_path);

    // Assert no XSS via PatchInfo Composer source plugin.
    $this->assertSession()->responseNotContains('<IMG SRC=/ onerror=\'alert(String.fromCharCode(88,83,83))\'></img>');
    $this->assertSession()->responseContains('&lt;IMG SRC=/ onerror=&#039;alert(String.fromCharCode(88,83,83))&#039;&gt;&lt;/img&gt;');

    // Assert patches added for test_mixed_sources.
    $json_path = $this->container->get('file_system')->realpath($module_path . '/tests/modules/test_mixed_sources/composer.json');
    $this->assertPatchInformationInReport('test_mixed_sources', '8.x-1.0', 'Issue 2739718, Patch #42', 'https://www.drupal.org/node/2739718', $module_path . '/tests/modules/test_mixed_sources/test_mixed_sources.info.yml');
    $this->assertPatchInformationInReport('test_mixed_sources', '8.x-1.0', 'Patch with-out URLs', '', $module_path . '/tests/modules/test_mixed_sources/test_mixed_sources.info.yml');
    $this->assertPatchInformationInReport('test_mixed_sources', '8.x-1.0', 'Patch with some special $()äöüß[]~!*{}%', 'https://www.example.net/', $module_path . '/tests/modules/test_mixed_sources/test_mixed_sources.info.yml');
    $this->assertPatchInformationInReport('test_mixed_sources', '8.x-1.0', 'Issue #3013512 by brtamas: Php 7.2 each() deprecated', 'https://git.drupalcode.org/project/draggableviews/commit/b973bfaf33b37ca5e473edddf20e25da1f9df163.diff', $json_path);
    $this->assertPatchInformationInReport('Drupal core', '8.0.0', 'Issue 2556069, Patch #46 (caption element throws TypeError: Cannot read property tagName of null in Drupal.behaviors.filterFilterHtmlUpdating)', 'https://www.drupal.org/files/issues/2018-08-31/2556069-46.patch', $json_path);

    // Assert warning logged for syntax errors in composer.json.
    $json_path = $this->container->get('file_system')->realpath($module_path . '/tests/modules/test_composer_syntax_error/composer.json');
    $this->assertWatchdogMessage('patchinfo_source_composer', RfcLogLevel::WARNING, strtr('Unable to parse @json_path. Check your JSON syntax.', [
      '@json_path' => $json_path,
    ]));

    // Assert patches added for test_theme_info_source.
    $this->assertPatchInformationInReport('test_theme_info_source', '8.x-1.0', 'Issue 2239723, Patch #14', 'https://www.drupal.org/node/2239723', $module_path . '/tests/themes/test_theme_info_source/test_theme_info_source.info.yml');
    $this->assertPatchInformationInReport('test_theme_info_source', '8.x-1.0', 'Patch in theme without URL', '', $module_path . '/tests/themes/test_theme_info_source/test_theme_info_source.info.yml');
    $this->assertPatchInformationInReport('test_theme_info_source', '8.x-1.0', 'Theme patch with some special ~!*äöüß()[]{}%$', 'https://www.example.net/', $module_path . '/tests/themes/test_theme_info_source/test_theme_info_source.info.yml');

    // Assert patches added for test_subtheme_info_source.
    $this->assertPatchInformationInReport('test_theme_info_source', '8.x-1.0', 'Patch in sub-theme without URL', '', $module_path . '/tests/themes/test_subtheme_info_source/test_subtheme_info_source.info.yml');

    // Assert patches added for test_theme_composer_source.
    $json_path = $this->container->get('file_system')->realpath($module_path . '/tests/themes/test_theme_composer_source/composer.json');
    $this->assertPatchInformationInReport('test_theme_composer_source', '8.x-1.0', 'Add patch to test theme to facilitate testing', 'https://www.drupal.org/files/issues/add_a_patch-1643831-40.patch', $json_path);
    $this->assertPatchInformationInReport('Drupal core', '8.0.0', 'Add patch to core to facilitate testing', 'https://www.drupal.org/files/issues/add_a_patch-1643899-62.patch', $json_path);

    // Assert patches added for test_theme_mixed_sources.
    $json_path = $this->container->get('file_system')->realpath($module_path . '/tests/themes/test_theme_mixed_sources/composer.json');
    $this->assertPatchInformationInReport('test_theme_mixed_sources', '8.x-1.0', 'Issue #3013512 by brtamas: Php 7.2 each() deprecated', 'https://git.drupalcode.org/project/draggableviews/commit/b973bfaf33b37ca5e473edddf20e25da1f9df163.diff', $json_path);
    $this->assertPatchInformationInReport('Drupal core', '8.0.0', 'Issue 2556069, Patch #46 (caption element throws TypeError: Cannot read property tagName of null in Drupal.behaviors.filterFilterHtmlUpdating)', 'https://www.drupal.org/files/issues/2018-08-31/2556069-46.patch', $json_path);
  }

  /**
   * Tests exclusion of modules from update check.
   */
  public function testModuleExclusion() {
    $this->installTestThemes();
    $this->exposeTestExtensions();

    // Assert all modules listed and not excluded.
    $this->drupalLogin($this->users['patchinfo']);
    $this->drupalGet('admin/reports/updates');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('test_composer_source');
    $this->assertSession()->pageTextContains('test_info_source');
    $this->assertSession()->pageTextContains('test_mixed_sources');
    $this->assertSession()->pageTextContains('test_theme_composer_source');
    $this->assertSession()->pageTextContains('test_theme_info_source');
    $this->assertSession()->pageTextContains('test_theme_mixed_sources');
    $this->assertModuleNotExcluded('test_composer_source');
    $this->assertModuleNotExcluded('test_info_source');
    $this->assertModuleNotExcluded('test_mixed_sources');
    $this->assertModuleNotExcluded('test_theme_composer_source');
    $this->assertModuleNotExcluded('test_theme_info_source');
    $this->assertModuleNotExcluded('test_theme_mixed_sources');
    $this->assertModuleListed('test_composer_source', '8.x-1.0');
    $this->assertModuleListed('test_info_source', '8.x-1.0');
    $this->assertModuleListed('test_mixed_sources', '8.x-1.0');
    $this->assertModuleListed('test_theme_composer_source', '8.x-1.0');
    $this->assertModuleListed('test_theme_info_source', '8.x-1.0');
    $this->assertModuleListed('test_theme_mixed_sources', '8.x-1.0');

    // Assert form field to exclude modules is available.
    $this->drupalGet('/admin/reports/updates/settings');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Exclude modules from update check');
    $this->assertSession()->pageTextContains('Modules, which should be excluded from the update check, can be listed here. Each entry should use the machine readable name of the module and go on a separate line.');

    // Exclude modules.
    $edit['patchinfo_exclude_from_update_check'] = 'test_info_source' . "\r\n" . 'test_theme_mixed_sources' . "\r\n" . 'test_composer_source';
    $this->drupalGet('admin/reports/updates/settings');
    $this->submitForm($edit, t('Save configuration'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('test_info_source');
    $this->assertSession()->pageTextContains('test_composer_source');
    $this->assertSession()->pageTextContains('test_theme_mixed_sources');

    // Assert excluded modules listed as excluded.
    $this->drupalGet('admin/reports/updates');
    $this->assertSession()->statusCodeEquals(200);
    $this->clickLink('Check manually');
    $this->checkForMetaRefresh();
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('test_composer_source');
    $this->assertSession()->pageTextContains('test_info_source');
    $this->assertSession()->pageTextContains('test_mixed_sources');
    $this->assertSession()->pageTextContains('test_theme_composer_source');
    $this->assertSession()->pageTextContains('test_theme_info_source');
    $this->assertSession()->pageTextContains('test_theme_mixed_sources');
    $this->assertModuleExcluded('test_composer_source');
    $this->assertModuleExcluded('test_info_source');
    $this->assertModuleExcluded('test_theme_mixed_sources');
    $this->assertModuleNotExcluded('test_mixed_sources');
    $this->assertModuleNotExcluded('test_theme_composer_source');
    $this->assertModuleNotExcluded('test_theme_info_source');
    $this->assertModuleNotListed('test_composer_source', '8.x-1.0');
    $this->assertModuleNotListed('test_info_source', '8.x-1.0');
    $this->assertModuleNotListed('test_theme_mixed_sources', '8.x-1.0');
    $this->assertModuleListed('test_mixed_sources', '8.x-1.0');
    $this->assertModuleListed('test_theme_info_source', '8.x-1.0');
    $this->assertModuleListed('test_theme_composer_source', '8.x-1.0');

    // Exclude no modules.
    $edit['patchinfo_exclude_from_update_check'] = NULL;
    $this->drupalGet('admin/reports/updates/settings');
    $this->submitForm($edit, t('Save configuration'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('test_info_source');
    $this->assertSession()->pageTextNotContains('test_composer_source');
    $this->assertSession()->pageTextNotContains('test_theme_mixed_sources');

    // Assert all modules listed and not excluded.
    $this->drupalGet('admin/reports/updates');
    $this->assertSession()->statusCodeEquals(200);
    $this->clickLink('Check manually');
    $this->checkForMetaRefresh();
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('test_composer_source');
    $this->assertSession()->pageTextContains('test_info_source');
    $this->assertSession()->pageTextContains('test_mixed_sources');
    $this->assertSession()->pageTextContains('test_theme_composer_source');
    $this->assertSession()->pageTextContains('test_theme_info_source');
    $this->assertSession()->pageTextContains('test_theme_mixed_sources');
    $this->assertModuleNotExcluded('test_composer_source');
    $this->assertModuleNotExcluded('test_info_source');
    $this->assertModuleNotExcluded('test_mixed_sources');
    $this->assertModuleNotExcluded('test_theme_composer_source');
    $this->assertModuleNotExcluded('test_theme_info_source');
    $this->assertModuleNotExcluded('test_theme_mixed_sources');
    $this->assertModuleListed('test_composer_source', '8.x-1.0');
    $this->assertModuleListed('test_info_source', '8.x-1.0');
    $this->assertModuleListed('test_mixed_sources', '8.x-1.0');
    $this->assertModuleListed('test_theme_composer_source', '8.x-1.0');
    $this->assertModuleListed('test_theme_info_source', '8.x-1.0');
    $this->assertModuleListed('test_theme_mixed_sources', '8.x-1.0');
  }

  /**
   * Tests exposure of patch information in update manager form.
   */
  public function testUpdateForm() {
    $this->exposeTestExtensions(TRUE);

    // Update XML fetch url for update manager to our own test XML source.
    $url = Url::fromUri('base:patchinfo-update-test', [
      'absolute' => TRUE,
    ])->toString();
    $this->config('update.settings')->set('fetch.url', $url)->save();

    // Refresh update information.
    $this->drupalLogin($this->users['patchinfo']);
    $this->drupalGet('admin/reports/updates');
    $this->checkForMetaRefresh();
    $this->assertSession()->statusCodeEquals(200);

    // Get update form.
    $this->drupalGet('admin/reports/updates/update');
    $this->assertSession()->statusCodeEquals(200);

    // Assert no XSS via PatchInfo Info source plugin.
    $this->assertSession()->responseNotContains('<IMG SRC=/ onerror="alert(String.fromCharCode(88,83,83))"></img>');
    $this->assertSession()->responseNotContains('<script src=http://xss.rocks/xss.js></script>');
    $this->assertSession()->responseContains('&lt;IMG SRC=/ onerror=&quot;alert(String.fromCharCode(88,83,83))&quot;&gt;&lt;/img&gt;');
    $this->assertSession()->responseContains('&lt;script src=http://xss.rocks/xss.js&gt;&lt;/script&gt;');

    $module_path = $this->container->get('module_handler')->getModule('patchinfo')->getPath();

    // Assert patches added for test_info_source.
    $this->assertPatchInformationInForm('Info source test', 'Issue 1739718, Patch #32', 'https://www.drupal.org/node/1739718', $module_path . '/tests/modules/test_info_source/test_info_source.info.yml');
    $this->assertPatchInformationInForm('Info source test', 'Patch without URL', '', $module_path . '/tests/modules/test_info_source/test_info_source.info.yml');
    $this->assertPatchInformationInForm('Info source test', 'Patch with some special ~!*äöüß()[]{}%$', 'https://www.example.org/', $module_path . '/tests/modules/test_info_source/test_info_source.info.yml');

    // Assert patches added for test_info_source_submodule.
    $this->assertPatchInformationInForm('Info source test', 'Issue 12345678, Patch #90', 'https://www.drupal.org/node/12345678', $module_path . '/tests/modules/test_info_source/submodule/test_info_source_submodule.info.yml');
    $this->assertPatchInformationInForm('Info source test', 'Patch in submodule without URL', '', $module_path . '/tests/modules/test_info_source/submodule/test_info_source_submodule.info.yml');

    // Assert patches added for test_composer_source.
    $json_path = $this->container->get('file_system')->realpath($module_path . '/tests/modules/test_composer_source/composer.json');
    $this->assertPatchInformationInForm('Drupal', 'Add startup configuration for PHP server', 'https://www.drupal.org/files/issues/add_a_startup-1543858-30.patch', $json_path);

    // Assert patches added for test_mixed_sources.
    $json_path = $this->container->get('file_system')->realpath($module_path . '/tests/modules/test_mixed_sources/composer.json');
    $this->assertPatchInformationInForm('Drupal', 'Issue 2556069, Patch #46 (caption element throws TypeError: Cannot read property tagName of null in Drupal.behaviors.filterFilterHtmlUpdating)', 'https://www.drupal.org/files/issues/2018-08-31/2556069-46.patch', $json_path);

    // Assert patches added for test_theme_composer_source.
    $json_path = $this->container->get('file_system')->realpath($module_path . '/tests/themes/test_theme_composer_source/composer.json');
    $this->assertPatchInformationInForm('Drupal', 'Add patch to core to facilitate testing', 'https://www.drupal.org/files/issues/add_a_patch-1643899-62.patch', $json_path);

    // Assert patches added for test_theme_mixed_sources.
    $json_path = $this->container->get('file_system')->realpath($module_path . '/tests/themes/test_theme_mixed_sources/composer.json');
    $this->assertPatchInformationInForm('Drupal', 'Issue 2556069, Patch #46 (caption element throws TypeError: Cannot read property tagName of null in Drupal.behaviors.filterFilterHtmlUpdating)', 'https://www.drupal.org/files/issues/2018-08-31/2556069-46.patch', $json_path);
  }

  /**
   * Installs test themes.
   */
  protected function installTestThemes() {
    $this->container->get('theme_installer')->install([
      'test_theme_composer_source',
      'test_theme_info_source',
      'test_subtheme_info_source',
      'test_theme_mixed_sources',
    ]);
  }

  /**
   * Exposes test extensions to update manager.
   *
   * @param bool $update_form_test
   *   Whether to test the update form. In that case only a limited number of
   *   test extensions will be exposed.
   */
  protected function exposeTestExtensions($update_form_test = FALSE) {
    $system_info = [
      '#all' => [
        'version' => '8.0.0',
      ],
      'test_info_source' => [
        'project' => 'test_info_source',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
      'test_info_source_submodule' => [
        'project' => 'test_info_source',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
      'test_composer_source' => [
        'project' => 'test_composer_source',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
      'test_mixed_sources' => [
        'project' => 'test_mixed_sources',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
      'test_theme_composer_source' => [
        'project' => 'test_theme_composer_source',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
      'test_theme_info_source' => [
        'project' => 'test_theme_info_source',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
      'test_subtheme_info_source' => [
        'project' => 'test_theme_info_source',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
      'test_theme_mixed_sources' => [
        'project' => 'test_theme_mixed_sources',
        'version' => '8.x-1.0',
        'hidden' => FALSE,
      ],
    ];
    if ($update_form_test) {
      $system_info = [
        '#all' => [
          'version' => '8.0.0',
        ],
        'test_info_source' => [
          'project' => 'test_info_source',
          'version' => '8.x-1.0',
          'hidden' => FALSE,
        ],
        'test_info_source_submodule' => [
          'project' => 'test_info_source',
          'version' => '8.x-1.0',
          'hidden' => FALSE,
        ],
      ];
    }
    $this->config('update_test.settings')->set('system_info', $system_info)->save();
  }

  /**
   * Assert that patch information is listed in update report.
   *
   * @param string $module_label
   *   Module label as listed in update report.
   * @param string $module_version
   *   Module version as listed in update report.
   * @param string $label
   *   Label of patch information.
   * @param string $url
   *   Url of patch information.
   * @param string $source
   *   Source of patch information.
   * @param int $index
   *   Link position counting from zero.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   */
  protected function assertPatchInformationInReport($module_label, $module_version, $label, $url = '', $source = '', $index = 0, $message = '') {
    // Get any elements.
    $xpath = '//';
    $arguments = [];

    // That are parents of abbreviations with a label matching '(src)' and title
    // attribute matching the source parameter.
    if (!empty($source)) {
      $xpath .= 'abbr[normalize-space(text())="(src)"][normalize-space(@title)=:source]/parent::';
      $arguments[':source'] = $source;
    }

    // That are links.
    if (!empty($url)) {
      $xpath .= 'a';
    }
    // That are list items.
    else {
      $xpath .= 'li';
    }

    // That have a label matching the label parameter.
    $xpath .= '[normalize-space(text())=:label]';
    $arguments[':label'] = $label;

    // That have an href attribute ending with the url parameter.
    if (!empty($url)) {
      // @note: This is an XPath 1.0 implementation of the ends-with() function.
      $xpath .= '[:href = substring(@href, string-length(@href) - ' . (strlen($url) + 1) . ')]';
      $arguments[':href'] = $url;
    }

    // That is nested inside a list item.
    if (!empty($url)) {
      $xpath .= '/parent::li';
    }

    // That is nested inside an unordered list.
    $xpath .= '/parent::ul';

    // That has a parent div with a class attribute matching exactly
    // 'patchinfo-patches'.
    $xpath .= '/parent::div[@class="patchinfo-patches"]';

    // That has a parent div with a class attribute matching exactly
    // 'project-updates__details'.
    $xpath .= '/parent::div[@class="project-updates__details"]';

    // That has a preceding div on same label with a class attribute matching
    // exactly 'project-update__title'.
    $xpath .= '/preceding-sibling::div[@class="project-update__title"]';

    // That has either a label whose space normalized version matches the
    // module_label parameter and module_version parameter concatenated with
    // a string or a child link with a label matching the module_label paramter.
    $xpath_link = $xpath . '/a[normalize-space(text())=:module_label]';
    $xpath .= '[normalize-space(text())=:update_title]|' . $xpath_link;
    $arguments[':module_label'] = $module_label;
    $arguments[':update_title'] = $module_label . ' ' . $module_version;

    $patch_information = $this->xpath($xpath, $arguments);

    $message = $message ? $message : strtr('Patch information for %module_label (%module_version) with label %label, url %url and source %source found.', [
      '%label' => $label,
      '%module_label' => $module_label,
      '%module_version' => $module_version,
      '%source' => $source,
      '%url' => $url,
    ]);
    $this->assertTrue(isset($patch_information[$index]), $message);
  }

  /**
   * Assert that patch information is listed in update form.
   *
   * @param string $module_label
   *   Module label as listed in update report.
   * @param string $label
   *   Label of patch information.
   * @param string $url
   *   Url of patch information.
   * @param string $source
   *   Source of patch information.
   * @param int $index
   *   Link position counting from zero.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   */
  protected function assertPatchInformationInForm($module_label, $label, $url = '', $source = '', $index = 0, $message = '') {
    // Get any elements.
    $xpath = '//';
    $arguments = [];

    // That are parents of abbreviations with a label matching '(src)' and title
    // attribute matching the source parameter.
    if (!empty($source)) {
      $xpath .= 'abbr[normalize-space(text())="(src)"][normalize-space(@title)=:source]/parent::';
      $arguments[':source'] = $source;
    }

    // That are links.
    if (!empty($url)) {
      $xpath .= 'a';
    }
    // That are list items.
    else {
      $xpath .= 'li';
    }

    // That have a label matching the label parameter.
    $xpath .= '[normalize-space(text())=:label]';
    $arguments[':label'] = $label;

    // That have an href attribute ending with the url parameter.
    if (!empty($url)) {
      // @note: This is an XPath 1.0 implementation of the ends-with() function.
      $xpath .= '[:href = substring(@href, string-length(@href) - ' . (strlen($url) + 1) . ')]';
      $arguments[':href'] = $url;
    }

    // That is nested inside a list item.
    if (!empty($url)) {
      $xpath .= '/parent::li';
    }

    // That is nested inside an unordered list.
    $xpath .= '/parent::ul';

    // That has a parent div with a class attribute matching exactly
    // 'patchinfo-patches'.
    $xpath .= '/parent::div[@class="patchinfo-patches"]';

    // That has a parent td.
    $xpath .= '/parent::td';

    // That are links with a label whose space normalized version matches the
    // module_label parameter.
    $xpath .= '/a[normalize-space(text())=:module_label]';
    $arguments[':module_label'] = $module_label;

    $patch_information = $this->xpath($xpath, $arguments);

    $message = $message ? $message : strtr('Patch information for %module_label with label %label, url %url and source %source found.', [
      '%label' => $label,
      '%module_label' => $module_label,
      '%source' => $source,
      '%url' => $url,
    ]);
    $this->assertTrue(isset($patch_information[$index]), $message);
  }

  /**
   * Assert that a module is listed in update report.
   *
   * @param string $module_label
   *   Module label as listed in update report.
   * @param string $module_version
   *   Module version as listed in update report.
   * @param int $index
   *   Link position counting from zero.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   */
  protected function assertModuleListed($module_label, $module_version, $index = 0, $message = '') {
    // Get any div elements with class 'project-update-title'.
    $xpath = '//div[@class="project-update__title"]';

    // That have an anchor tag containing the module label.
    $xpath_link = $xpath . '/a[normalize-space(text())=:module_label]';

    // Or that contain the module label and module version separated by a space.
    $xpath .= '[normalize-space(text())=:update_title]|' . $xpath_link;

    $listed_module = $this->xpath($xpath, [
      ':module_label' => $module_label,
      ':update_title' => $module_label . ' ' . $module_version,
    ]);
    $this->assertTrue(isset($listed_module[$index]), $message);
  }

  /**
   * Assert that a module is listed as excluded above the update report.
   *
   * @param string $module_label
   *   Module label as listed in update report.
   * @param int $index
   *   Link position counting from zero.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   */
  protected function assertModuleExcluded($module_label, $index = 0, $message = '') {
    // Get any div elements with class 'patchinfo-excluded-modules'.
    $xpath = '//div[@class="patchinfo-excluded-modules"]';

    // That contain an unordered list.
    $xpath .= '/ul';

    // With a list item containing the module label.
    $xpath .= '/li[normalize-space(text())=:module_label]';

    $excluded_module = $this->xpath($xpath, [
      ':module_label' => $module_label,
    ]);

    $this->assertTrue(isset($excluded_module[$index]), $message);
  }

  /**
   * Assert that a module is not listed as excluded above the update report.
   *
   * @param string $module_label
   *   Module label as listed in update report.
   * @param int $index
   *   Link position counting from zero.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   */
  protected function assertModuleNotExcluded($module_label, $index = 0, $message = '') {
    // Get any div elements with class 'patchinfo-excluded-modules'.
    $xpath = '//div[@class="patchinfo-excluded-modules"]';

    // That contain an unordered list.
    $xpath .= '/ul';

    // With a list item containing the module label.
    $xpath .= '/li[normalize-space(text())=:module_label]';

    $excluded_module = $this->xpath($xpath, [
      ':module_label' => $module_label,
    ]);

    $this->assertFalse(isset($excluded_module[$index]), $message);
  }

  /**
   * Assert that a module is not listed in update report.
   *
   * @param string $module_label
   *   Module label as listed in update report.
   * @param string $module_version
   *   Module version as listed in update report.
   * @param int $index
   *   Link position counting from zero.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   */
  protected function assertModuleNotListed($module_label, $module_version, $index = 0, $message = '') {
    // Get any div elements with class 'project-update-title'.
    $xpath = '//div[@class="project-update__title"]';

    // That have an anchor tag containing the module label.
    $xpath_link = $xpath . '/a[normalize-space(text())=:module_label]';

    // Or that contain the module label and module version separated by a space.
    $xpath .= '[normalize-space(text())=:update_title]|' . $xpath_link;

    $listed_module = $this->xpath($xpath, [
      ':module_label' => $module_label,
      ':update_title' => $module_label . ' ' . $module_version,
    ]);
    $this->assertFalse(isset($listed_module[$index]), $message);
  }

}
