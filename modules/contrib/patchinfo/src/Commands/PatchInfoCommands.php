<?php

namespace Drupal\patchinfo\Commands;

use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\update\UpdateManagerInterface;

/**
 * A Drush commandfile for the patchinfo:list command.
 */
class PatchInfoCommands extends DrushCommands {

  /**
   * Show a report of patches applied to Drupal core and contrib projects.
   *
   * @param array $options
   *   Array of output options.
   *
   * @command patchinfo:list
   * @option projects
   *   Comma-separated list of projects to list patches for.
   * @field-labels
   *   name: Name
   *   label: Title
   *   delta: Delta
   *   info: Info
   *   url: URL
   *   source: Source
   * @default-string-field name
   * @usage patchinfo:list --projects=drupal
   *   List patches for Drupal Core.
   * @usage patchinfo:list --projects=drupal,pathauto
   *   List patches for Drupal Core and Pathauto.
   * @usage patchinfo:list --format=yaml
   *   List patches in YAML format.
   * @usage patchinfo:list --format=csv
   *   List patches iN CSV format.
   * @usage patchinfo:list --fields=project,info
   *   List the project and info fields for all patches.
   * @usage patchinfo:list --fields=project,delta
   *   List the project and delta fields for all patches.
   * @aliases pil,patchinfo-list,pi-list
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Patch information as rows of fields.
   */
  public function list(array $options = [
    'projects' => NULL,
    'format' => 'table',
    'fields' => 'name,label,delta,info',
  ]) {

    $fields = explode(',', $options['fields']);
    $hasUrlColumn = in_array('url', $fields);

    $table = $this->getTableData($options['projects'], $hasUrlColumn);
    $data = new RowsOfFields($table);

    $data->addRendererFunction(
      function ($key, $cellData, FormatterOptions $options, $rowData) {
        if ($key === 'name') {
          return "<comment>$cellData</comment>";
        }
        return $cellData;
      }
    );

    return $data;
  }

  /**
   * Update manager service.
   *
   * @var \Drupal\update\UpdateManagerInterface
   */
  protected $updateManager;

  /**
   * Constructs update status data.
   *
   * @param \Drupal\update\UpdateManagerInterface $update_manager
   *   Update Manager Service.
   */
  public function __construct(UpdateManagerInterface $update_manager) {
    $this->updateManager = $update_manager;
  }

  /**
   * Returns table data for all patches in projects.
   *
   * @param string|null $projects
   *   List of projects to include.
   * @param bool $hasUrlColumn
   *   Whether the table includes a URL column.
   *
   * @return array
   *   Table data of all patches
   */
  protected function getTableData(string $projects = NULL, bool $hasUrlColumn = TRUE) {
    $table = [];

    $limit_projects = !empty($projects) ? explode(',', $projects) : [];

    $patch_info = _patchinfo_get_info(TRUE);
    if (count($patch_info) === 0) {
      return $table;
    }
    // Get project information from update manager service.
    $projects = $this->updateManager->getProjects();

    $has_limit_projects = (count($limit_projects) > 0);
    foreach ($projects as $project) {
      if ($has_limit_projects && !in_array($project['name'], $limit_projects, TRUE)) {
        continue;
      }
      $patches = _patchinfo_get_patches($patch_info, $project);
      if (count($patches) > 0) {
        $label = $project['info']['name'] . ' (' . $project['name'] . ')';
        if ($project['name'] === 'drupal') {
          $label = 'Drupal (drupal)';
        }
        foreach ($patches as $delta => $patch) {
          $patchinfo_list_row = [
            'name' => $project['name'],
            'label' => $label,
            'delta' => $delta,
            'info' => $hasUrlColumn ? $patch['info'] : ($patch['info'] . ($patch['url'] ? "\n" . $patch['url'] : '')),
            'url' => $patch['url'],
            'source' => $patch['source'],
          ];
          $table[$project['name'] . '-' . $delta] = $patchinfo_list_row;
        }
      }
    }
    return $table;
  }

}
