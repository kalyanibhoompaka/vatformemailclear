<?php

namespace Drupal\Tests\patchinfo\Traits;

use Drupal\Component\Render\FormattableMarkup;

/**
 * Provides common methods for functional tests of PatchInfo module.
 */
trait PatchInfoTestTrait {

  /**
   * Passes if an element matching the specified CSS selector is found.
   *
   * An optional element index may be passed.
   *
   * @param string $selector
   *   CSS selector of element.
   * @param int $index
   *   Element position counting from zero.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   */
  protected function assertElementByCssSelector($selector, $index = 0, $message = '') {
    $elements = $this->cssSelect($selector);
    $message = $message ? $message : new FormattableMarkup('Element with CSS selector %selector found.', [
      '%selector' => $selector,
    ]);
    $this->assertTrue(isset($elements[$index]), $message);
  }

  /**
   * Passes if an element matching the specified CSS selector is not found.
   *
   * An optional element index may be passed.
   *
   * @param string $selector
   *   CSS selector of element.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   */
  protected function assertNoElementByCssSelector($selector, $message = '') {
    $elements = $this->cssSelect($selector);
    $message = $message ? $message : new FormattableMarkup('Element with CSS selector %selector not found.', [
      '%selector' => $selector,
    ]);
    $this->assertTrue(empty($elements), $message);
  }

  /**
   * Passes if a a link whose href attribute ends with a string is found.
   *
   * @todo Remove once https://www.drupal.org/node/2031223 has been fixed.
   *
   * An optional element index may be passed.
   *
   * @param string $href
   *   What the href attribute of the link should end with.
   * @param int $index
   *   Link position counting from zero.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   */
  protected function assertLinkByEndOfHref($href, $index = 0, $message = '') {
    // This is an XPath 1.0 implementation of the ends-with() function.
    $links = $this->xpath('//a[:href = substring(@href, string-length(@href) - ' . (strlen($href) + 1) . ')]', [
      ':href' => $href,
    ]);
    $message = $message ? $message : new FormattableMarkup('Link with href %href found.', [
      '%href' => $href,
    ]);
    $this->assertTrue(isset($links[$index]), $message);
  }

  /**
   * Passes if a a link whose href attribute ends with a string is not found.
   *
   * @todo Remove once https://www.drupal.org/node/2031223 has been fixed.
   *
   * An optional element index may be passed.
   *
   * @param string $href
   *   What the href attribute of the link should end with.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   */
  protected function assertNoLinkByEndOfHref($href, $message = '') {
    // This is an XPath 1.0 implementation of the ends-with() function.
    $links = $this->xpath('//a[:href = substring(@href, string-length(@href) - ' . (strlen($href) + 1) . ')]', [
      ':href' => $href,
    ]);
    $message = $message ? $message : new FormattableMarkup('No link with href %href found.', [
      '%href' => $href,
    ]);
    $this->assertTrue(empty($links), $message);
  }

  /**
   * Installs modules and rebuilds all data structures.
   *
   * @param array $modules
   *   Modules to install.
   */
  protected function installModules(array $modules) {
    $module_installer = $this->container->get('module_installer');
    $is_module_installed = $module_installer->install($modules, TRUE);
    $this->assertTrue($is_module_installed, new FormattableMarkup('Enabled modules: %modules', [
      '%modules' => 'taxonomy_access_fix',
    ]));
    $this->rebuildAll();
  }

  /**
   * Asserts that a watchdog message exists.
   *
   * @param string $type
   *   Message type.
   * @param int $severity
   *   Message severity.
   * @param string $watchdog_message
   *   Message to assert. Do not translate messages: use strtr() to embed
   *   variables in the message text, not t().
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   *
   * @see \Drupal\Core\Logger\RfcLogLevel
   */
  protected function assertWatchdogMessage($type, $severity, $watchdog_message, $message = '') {
    $message = $message ? $message : new FormattableMarkup('Message count for message "@message" of type "@type" with severity "@severity" is greater than 0.', [
      '@message' => $watchdog_message,
      '@type' => $type,
      '@severity' => $severity,
    ]);
    $watchdog_message_count = $this->getWatchdogMessageCount($type, $severity, $watchdog_message);
    $this->assertTrue($watchdog_message_count > 0, $message);
  }

  /**
   * Asserts that a watchdog message does not exist.
   *
   * @param string $type
   *   Message type.
   * @param int $severity
   *   Message severity.
   * @param string $watchdog_message
   *   Message to assert. Do not translate messages: use strtr() to embed
   *   variables in the message text, not t().
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use strtr() to embed variables in the message text, not
   *   t(). If left blank, a default message will be displayed.
   *
   * @see \Drupal\Core\Logger\RfcLogLevel
   */
  protected function assertNoWatchdogMessage($type, $severity, $watchdog_message, $message = NULL) {
    $message = $message ? $message : new FormattableMarkup('Message count for message "@message" of type "@type" with severity "@severity" is 0.', [
      '@message' => $watchdog_message,
      '@type' => $type,
      '@severity' => $severity,
    ]);
    $watchdog_message_count = $this->getWatchdogMessageCount($type, $severity, $watchdog_message);
    $this->assertTrue($watchdog_message_count === 0, $message);
  }

  /**
   * Gets number of log entries in watchdog table with a particular message.
   *
   * @param string $type
   *   Message type.
   * @param int $severity
   *   Message severity.
   * @param string $watchdog_message
   *   Message to get count for. Do not translate messages: use strtr() to embed
   *   variables in the message text, not t().
   *
   * @return int
   *   Number of log entries in watchdog table with a particular message.
   *
   * @see \Drupal\Core\Logger\RfcLogLevel
   */
  protected function getWatchdogMessageCount($type, $severity, $watchdog_message) {
    return (int) $this->container->get('database')
      ->select('watchdog', 'w')
      ->condition('w.message', $watchdog_message)
      ->condition('w.type', $type)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
