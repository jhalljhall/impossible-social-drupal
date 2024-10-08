<?php

namespace Drupal\rules\Commands;

// cspell:ignore rlst renb rdis rdel rexp rrev

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\rules\Core\RulesEventManager;
use Drush\Commands\DrushCommands;

/**
 * Drush 9+ commands for the Rules module.
 */
class RulesCommands extends DrushCommands {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config storage service.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  protected $configStorage;

  /**
   * The rules event manager.
   *
   * @var \Drupal\rules\Core\RulesEventManager
   */
  protected $rulesEventManager;

  /**
   * RulesCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\CachedStorage $config_storage
   *   The config storage service.
   * @param \Drupal\rules\Core\RulesEventManager $rules_event_manager
   *   The rules event manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CachedStorage $config_storage, RulesEventManager $rules_event_manager) {
    parent::__construct();
    $this->configFactory = $config_factory;
    $this->configStorage = $config_storage;
    $this->rulesEventManager = $rules_event_manager;
  }

  /**
   * Lists all the active and inactive rules for your site.
   *
   * @param string $type
   *   (optional) Either 'rule' or 'component'. Any other value (or no value)
   *   will list both Reaction Rules and Rules Components.
   * @param array $options
   *   (optional) The options.
   *
   * @command rules:list
   * @aliases rlst,rules-list
   *
   * @usage drush rules:list
   *   Lists both Reaction Rules and Rules Components.
   * @usage drush rules:list component
   *   Lists only Rules Components.
   * @usage drush rules:list --fields=machine-name
   *   Lists just the machine names.
   * @usage drush rules:list --fields=machine-name --pipe
   *   Outputs machine names in a format suitable for piping.
   *
   * @table-style default
   * @field-labels
   *   machine-name: Rule
   *   label: Label
   *   event: Event
   *   active: Active
   * @default-fields machine-name,label,event,active
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The data.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function listAll($type = '', array $options = ['format' => 'table', 'fields' => '']) {
    // Type is 'rule', or 'component'. Any other value (or no value) will
    // list both Reaction Rules and Rules Components.
    switch ($type) {
      case 'rule':
        $types = ['reaction'];
        break;

      case 'component':
        $types = ['component'];
        break;

      default:
        $types = ['reaction', 'component'];
        break;
    }

    // Loop over type parameter.
    $rows = [];
    foreach ($types as $item) {
      $rules = $this->configFactory->listAll('rules.' . $item);
      // Loop over configuration entities for this $item.
      foreach ($rules as $config) {
        $rule = $this->configFactory->get($config);
        if (!empty($rule->get('id')) && !empty($rule->get('label'))) {
          $events = [];
          $active = '';
          // Components don't have events and can't be enabled/disabled.
          if ($item == 'reaction') {
            foreach ($rule->get('events') as $event) {
              $plugin = $this->rulesEventManager->getDefinition($event['event_name']);
              $events[] = (string) $plugin['label'];
            }
            $active = $rule->get('status') ? dt('Enabled') : dt('Disabled');
          }
          $rows[(string) $rule->get('id')] = [
            'machine-name' => (string) $rule->get('id'),
            'label' => (string) $rule->get('label'),
            'event' => implode(', ', $events),
            'active' => (string) $active,
          ];
        }
      }
    }

    return new RowsOfFields($rows);
  }

  /**
   * Enables a Reaction Rule on your site.
   *
   * @param string $rule
   *   Reaction rule name (machine name) to enable.
   *
   * @command rules:enable
   * @interact-disabled-rules
   * @aliases renb,rules-enable
   *
   * @usage drush rules:enable
   *   Displays all disabled rules and allows you to select one to enable.
   * @usage drush rules:enable test_rule
   *   Enables the rule with machine name 'test_rule'.
   *
   * @throws \Exception
   */
  public function enable($rule) {
    // The $rule argument must be a Reaction Rule.
    if ($this->configStorage->exists('rules.reaction.' . $rule)) {
      $config = $this->configFactory->getEditable('rules.reaction.' . $rule);
    }
    // The @interact-disabled-rules hook returns fully-qualified names.
    elseif ($this->configStorage->exists($rule)) {
      $config = $this->configFactory->getEditable($rule);
    }
    else {
      throw new \Exception(dt('Could not find a Reaction Rule named @name', ['@name' => $rule]));
    }

    if (!$config->get('status')) {
      $config->set('status', TRUE);
      $config->save();
      $this->logger->success(dt('The rule @name has been enabled.', ['@name' => $rule]));
    }
    else {
      $this->logger->warning(dt('The rule @name is already enabled', ['@name' => $rule]));
    }
  }

  /**
   * Disables a Reaction Rule on your site.
   *
   * @param string $rule
   *   Reaction rule name (machine name) to disable.
   *
   * @command rules:disable
   * @interact-enabled-rules
   * @aliases rdis,rules-disable
   *
   * @usage drush rules:disable
   *   Displays all enabled rules and allows you to select one to disable.
   * @usage drush rules:disable test_rule
   *   Disables the rule with machine name 'test_rule'.
   *
   * @throws \Exception
   */
  public function disable($rule) {
    // The $rule argument must be a Reaction Rule.
    if ($this->configStorage->exists('rules.reaction.' . $rule)) {
      $config = $this->configFactory->getEditable('rules.reaction.' . $rule);
    }
    // The @interact-enabled-rules hook returns fully-qualified names.
    elseif ($this->configStorage->exists($rule)) {
      $config = $this->configFactory->getEditable($rule);
    }
    else {
      throw new \Exception(dt('Could not find a Reaction Rule named @name', ['@name' => $rule]));
    }

    if ($config->get('status')) {
      $config->set('status', FALSE);
      $config->save();
      $this->logger->success(dt('The rule @name has been disabled.', ['@name' => $rule]));
    }
    else {
      $this->logger->warning(dt('The rule @name is already disabled', ['@name' => $rule]));
    }
  }

  /**
   * Deletes a rule on your site.
   *
   * @param string $rule
   *   Rule name (machine id) to delete.
   *
   * @command rules:delete
   * @interact-rule-names
   * @aliases rdel,rules-delete
   *
   * @usage drush rules:delete
   *   Displays all rules and allows you to select one to delete.
   * @usage drush rules:delete test_rule
   *   Permanently deletes the rule with machine name 'test_rule'.
   *
   * @throws \Exception
   */
  public function delete($rule) {
    // The $rule argument could refer to a Reaction Rule or a Rules Component.
    if ($this->configStorage->exists('rules.reaction.' . $rule)) {
      $config = $this->configFactory->getEditable('rules.reaction.' . $rule);
    }
    elseif ($this->configStorage->exists('rules.component.' . $rule)) {
      $config = $this->configFactory->getEditable('rules.component.' . $rule);
    }
    // The @interact-rule-names hook returns fully-qualified names.
    elseif ($this->configStorage->exists($rule)) {
      $config = $this->configFactory->getEditable($rule);
    }
    else {
      throw new \Exception(dt('Could not find a Reaction Rule or a Rules Component named @name', ['@name' => $rule]));
    }

    if ($this->confirm(dt('Are you sure you want to delete the rule named "@name"? This action cannot be undone.', ['@name' => $rule]))) {
      $config->delete();
      $this->logger->success(dt('The rule @name has been deleted.', ['@name' => $rule]));
    }

  }

  /**
   * Exports a single rule configuration, in YAML format.
   *
   * @param string $rule
   *   Rule name (machine id) to export.
   *
   * @command rules:export
   * @interact-rule-names
   * @aliases rexp,rules-export
   *
   * @codingStandardsIgnoreStart
   * @usage drush rules:export
   *   Displays all rules and allows you to select one to export.
   * @usage drush rules:export test_rule > rules.reaction.test_rule.yml
   *   Exports the Rule with machine name 'test_rule' and saves it in a .yml file.
   * @usage drush rules:list rule --fields=machine-name --pipe | xargs -I{}  sh -c "drush rules:export '{}' > 'rules.reaction.{}.yml'"
   *   Exports all Reaction Rules into individual YAML files.
   * @codingStandardsIgnoreEnd
   *
   * @throws \Exception
   */
  public function export($rule) {
    // The $rule argument could refer to a Reaction Rule or a Rules Component.
    $config = $this->configStorage->read('rules.reaction.' . $rule);
    if (empty($config)) {
      $config = $this->configStorage->read('rules.component.' . $rule);
      if (empty($config)) {
        // The @interact-rule-names hook returns fully-qualified names.
        $config = $this->configStorage->read($rule);
        if (empty($config)) {
          throw new \Exception(dt('Could not find a Reaction Rule or a Rules Component named @name', ['@name' => $rule]));
        }
      }
    }

    $this->output->write(Yaml::encode($config), FALSE);
    $this->logger->success(dt('The rule @name has been exported.', ['@name' => $rule]));
  }

  /**
   * Reverts a rule to its original state on your site.
   *
   * @param string $rule
   *   Rule name (machine id) to revert.
   *
   * @command rules:revert
   * @interact-rule-names
   * @aliases rrev,rules-revert
   *
   * @usage drush rules:revert test_rule
   *   Restores the module-provided Rule with machine id 'test_rule' to its
   *   original state. If the Rule hasn't been customized on the site, this has
   *   no effect.
   *
   * @throws \Exception
   */
  public function revert($rule) {
    // @todo Implement this function.

    // The $rule argument could refer to a Reaction Rule or a Rules Component.
    $config = $this->configStorage->read('rules.reaction.' . $rule);
    if (empty($config)) {
      $config = $this->configStorage->read('rules.component.' . $rule);
      if (empty($config)) {
        // The @interact-rule-names hook returns fully-qualified names.
        $config = $this->configStorage->read($rule);
        if (empty($config)) {
          throw new \Exception(dt('Could not find a Reaction Rule or a Rules Component named @name', ['@name' => $rule]));
        }
      }
    }

    if (($rule->status & ENTITY_OVERRIDDEN) == ENTITY_OVERRIDDEN) {
      if ($this->confirm(dt('Are you sure you want to revert the rule named "@name"? This action cannot be undone.', ['@name' => $rule]))) {
        // $config->delete();
        $this->logger->success(dt('The rule @name has been reverted to its default state.', ['@name' => $rule]));
      }
    }
    else {
      $this->logger->warning(dt('The rule "@name" has not been overridden and can\'t be reverted.', ['@name' => $rule]));
    }
  }

  /**
   * Show a list of Rules events.
   *
   * @command rules:events
   * @aliases rules-events
   */
  public function listEvents() {
    $this->formatOutput('plugin.manager.rules_event', 'Available Rules Events:');
  }

  /**
   * Show a list of Rules conditions.
   *
   * @command rules:conditions
   * @aliases rules-conditions
   */
  public function listConditions() {
    $this->formatOutput('plugin.manager.condition', 'Available Rules Conditions:');
  }

  /**
   * Show a list of Rules actions.
   *
   * @command rules:actions
   * @aliases rules-actions
   */
  public function listActions() {
    $this->formatOutput('plugin.manager.rules_action', 'Available Rules Actions:');
  }

  /**
   * Show a list of Rules expressions.
   *
   * @command rules:expressions
   * @aliases rules-expressions
   */
  public function listExpressions() {
    $this->formatOutput('plugin.manager.rules_expression', 'Available Rules Expressions:');
  }

  /**
   * Helper function to format command output.
   */
  protected function formatOutput($plugin_manager_service, $title, $categories = TRUE, $short = FALSE) {
    // Dependency injection deliberately not used because we don't know
    // a priori what service will be needed. So ignore the phpcs message.
    // @phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $definitions = \Drupal::service($plugin_manager_service)->getDefinitions();
    $plugins = [];
    foreach ($definitions as $plugin) {
      if ($categories) {
        if ($short) {
          $plugins[(string) $plugin['category']][] = $plugin['id'];
        }
        else {
          $plugins[(string) $plugin['category']][] = $plugin['label'] . '   (' . $plugin['id'] . ')';
        }
      }
      else {
        if ($short) {
          $plugins[] = $plugin['id'];
        }
        else {
          $plugins[] = $plugin['label'] . '   (' . $plugin['id'] . ')';
        }
      }
    }

    $this->output()->writeln(dt($title));
    if ($categories) {
      ksort($plugins);
      foreach ($plugins as $category => $plugin_list) {
        $this->output()->writeln('  ' . $category);
        sort($plugin_list);
        $this->output()->writeln('    ' . implode(PHP_EOL . '    ', $plugin_list));
        $this->output()->writeln('');
      }
    }
    else {
      $unique = array_unique($plugins);
      sort($unique);
      $this->output()->writeln('  ' . implode(PHP_EOL . '  ', $unique) . PHP_EOL);
    }
  }

  /**
   * @hook interact @interact-enabled-rules
   */
  public function interactEnabledRules($input, $output) {
    if (empty($input->getArgument('rule'))) {
      $rules = $this->configFactory->listAll('rules.reaction');
      // Loop over configuration entities for this $item.
      foreach ($rules as $index => $config) {
        $rule = $this->configFactory->get($config);
        if ($rule->get('status') === FALSE) {
          unset($rules[$index]);
        }
      }
      $choice = $this->io()->choice('Choose a Reaction Rule', drush_map_assoc($rules));
      $input->setArgument('rule', $choice);
    }
  }

  /**
   * @hook interact @interact-disabled-rules
   */
  public function interactDisabledRules($input, $output) {
    if (empty($input->getArgument('rule'))) {
      $rules = $this->configFactory->listAll('rules.reaction');
      // Loop over configuration entities for this $item.
      foreach ($rules as $index => $config) {
        $rule = $this->configFactory->get($config);
        if ($rule->get('status') === TRUE) {
          unset($rules[$index]);
        }
      }
      $choice = $this->io()->choice('Choose a Reaction Rule', drush_map_assoc($rules));
      $input->setArgument('rule', $choice);
    }
  }

  /**
   * @hook interact @interact-rule-names
   */
  public function interactRuleNames($input, $output) {
    if (empty($input->getArgument('rule'))) {
      $rule_names = $this->configFactory->listAll('rules.reaction');
      $component_names = $this->configFactory->listAll('rules.component');
      $config_names = array_merge($rule_names, $component_names);
      $choice = $this->io()->choice('Choose a Rule', drush_map_assoc($config_names));
      $input->setArgument('rule', $choice);
    }
  }

}
