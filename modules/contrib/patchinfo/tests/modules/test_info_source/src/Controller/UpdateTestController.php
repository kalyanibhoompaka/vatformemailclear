<?php

namespace Drupal\test_info_source\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Provides a route to fetch test update status from.
 *
 * @see \Drupal\update_test\Controller\UpdateTestController
 */
class UpdateTestController extends ControllerBase {

  /**
   * Page callback: Prints mock XML for the Update Manager module.
   *
   * @param string $project_name
   *   The project short name the update manager is trying to fetch data for
   *   (the fetch URLs are of the form:
   *   [base_url]/[project_name]/[core_version]).
   * @param string $version
   *   The version of Drupal core.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   A BinaryFileResponse object containing the content of the XML release
   *   file for the specified project.
   */
  public function updateTest(string $project_name, string $version) {
    $headers = ['Content-Type' => 'text/xml; charset=utf-8'];
    if ($project_name === 'drupal') {
      $file = __DIR__ . "/../../drupal.1_1.xml";
      return new BinaryFileResponse($file, 200, $headers);
    }
    else {
      $file = __DIR__ . "/../../test_info_source.1_1.xml";
    }
    return new BinaryFileResponse($file, 200, $headers);
  }

}
