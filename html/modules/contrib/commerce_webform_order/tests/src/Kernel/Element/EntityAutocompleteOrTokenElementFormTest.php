<?php

namespace Drupal\Tests\commerce_webform_order\Kernel\Element;

use Drupal\commerce_webform_order\Element\EntityAutocompleteOrToken;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests the EntityAutocompleteOrToken Form API element.
 *
 * @group commerce_webform_order
 */
class EntityAutocompleteOrTokenElementFormTest extends EntityKernelTestBase implements FormInterface {

  use StringTranslationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['commerce_webform_order'];

  /**
   * User for testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * User for autocreate testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testAutocreateUser;

  /**
   * An array of entities to be referenced in this test.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $referencedEntities;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_string_id');

    $this->testUser = User::create([
      'name' => 'foobar1',
      'mail' => 'foobar1@example.com',
    ]);
    $this->testUser->save();
    \Drupal::service('current_user')->setAccount($this->testUser);

    $this->testAutocreateUser = User::create([
      'name' => 'foobar2',
      'mail' => 'foobar2@example.com',
    ]);
    $this->testAutocreateUser->save();

    for ($i = 1; $i < 3; $i++) {
      $entity = EntityTest::create([
        'name' => $this->randomMachineName(),
      ]);
      $entity->save();
      $this->referencedEntities[] = $entity;
    }

    // Use special characters in the ID of some of the test entities so we can
    // test if these are handled correctly.
    for ($i = 0; $i < 2; $i++) {
      $entity = EntityTestStringId::create([
        'name' => $this->randomMachineName(),
        'id' => $this->randomMachineName() . '&</\\:?',
      ]);
      $entity->save();
      $this->referencedEntities[] = $entity;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'test_entity_autocomplete_token';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['single'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
    ];
    $form['single_autocreate'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#autocreate' => [
        'bundle' => 'entity_test',
      ],
    ];
    $form['single_autocreate_specific_uid'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#autocreate' => [
        'bundle' => 'entity_test',
        'uid' => $this->testAutocreateUser->id(),
      ],
    ];

    $form['tags'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#tags' => TRUE,
    ];
    $form['tags_autocreate'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#tags' => TRUE,
      '#autocreate' => [
        'bundle' => 'entity_test',
      ],
    ];
    $form['tags_autocreate_specific_uid'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#tags' => TRUE,
      '#autocreate' => [
        'bundle' => 'entity_test',
        'uid' => $this->testAutocreateUser->id(),
      ],
    ];

    $form['single_no_validate'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#validate_reference' => FALSE,
    ];
    $form['single_autocreate_no_validate'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#validate_reference' => FALSE,
      '#autocreate' => [
        'bundle' => 'entity_test',
      ],
    ];

    $form['single_access'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#default_value' => $this->referencedEntities[0],
    ];
    $form['tags_access'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#tags' => TRUE,
      '#default_value' => [
        $this->referencedEntities[0],
        $this->referencedEntities[1],
      ],
    ];

    $form['single_string_id'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test_string_id',
    ];
    $form['tags_string_id'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test_string_id',
      '#tags' => TRUE,
    ];

    $form['token'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
    ];
    $form['token_tags'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#tags' => TRUE,
    ];
    $form['token_default_value'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#default_value' => '[current-user:uid]',
    ];
    $form['token_tags_default_value'] = [
      '#type' => 'commerce_webform_order_entity_autocomplete_token',
      '#target_type' => 'entity_test',
      '#tags' => TRUE,
      '#default_value' => [
        '[current-user:uid]',
        '[current-user:mail]',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Tests valid entries in the EntityAutocompleteOrToken Form API element.
   */
  public function testValidEntityAutocompleteOrTokenElement() {
    $form_state = (new FormState())
      ->setValues([
        'single' => $this->getAutocompleteInput($this->referencedEntities[0]),
        'single_autocreate' => 'single - autocreated entity label',
        'single_autocreate_specific_uid' => 'single - autocreated entity label with specific uid',
        'tags' => $this->getAutocompleteInput($this->referencedEntities[0]) . ', ' . $this->getAutocompleteInput($this->referencedEntities[1]),
        'tags_autocreate' => $this->getAutocompleteInput($this->referencedEntities[0]) . ', tags - autocreated entity label, ' . $this->getAutocompleteInput($this->referencedEntities[1]),
        'tags_autocreate_specific_uid' => $this->getAutocompleteInput($this->referencedEntities[0]) . ', tags - autocreated entity label with specific uid, ' . $this->getAutocompleteInput($this->referencedEntities[1]),
        'single_string_id' => $this->getAutocompleteInput($this->referencedEntities[2]),
        'tags_string_id' => $this->getAutocompleteInput($this->referencedEntities[2]) . ', ' . $this->getAutocompleteInput($this->referencedEntities[3]),
        'token' => '[current-user:uid]',
        'token_tags' => '[current-user:uid], [current-user:mail]',
      ]);
    $form_builder = $this->container->get('form_builder');
    $form_builder->submitForm($this, $form_state);

    // Valid form state.
    $this->assertCount(0, $form_state->getErrors());

    // Test the 'single' element.
    $this->assertEquals($this->referencedEntities[0]->id(), $form_state->getValue('single'));

    // Test the 'single_autocreate' element.
    $value = $form_state->getValue('single_autocreate');
    $this->assertEquals('single - autocreated entity label', $value['entity']->label());
    $this->assertEquals('entity_test', $value['entity']->bundle());
    $this->assertEquals($this->testUser->id(), $value['entity']->getOwnerId());

    // Test the 'single_autocreate_specific_uid' element.
    $value = $form_state->getValue('single_autocreate_specific_uid');
    $this->assertEquals('single - autocreated entity label with specific uid', $value['entity']->label());
    $this->assertEquals('entity_test', $value['entity']->bundle());
    $this->assertEquals($this->testAutocreateUser->id(), $value['entity']->getOwnerId());

    // Test the 'tags' element.
    $expected = [
      ['target_id' => $this->referencedEntities[0]->id()],
      ['target_id' => $this->referencedEntities[1]->id()],
    ];
    $this->assertEquals($expected, $form_state->getValue('tags'));

    // Test the 'single_autocreate' element.
    $value = $form_state->getValue('tags_autocreate');
    // First value is an existing entity.
    $this->assertEquals($this->referencedEntities[0]->id(), $value[0]['target_id']);
    // Second value is an autocreated entity.
    $this->assertTrue(!isset($value[1]['target_id']));
    $this->assertEquals('tags - autocreated entity label', $value[1]['entity']->label());
    $this->assertEquals($this->testUser->id(), $value[1]['entity']->getOwnerId());
    // Third value is an existing entity.
    $this->assertEquals($this->referencedEntities[1]->id(), $value[2]['target_id']);

    // Test the 'tags_autocreate_specific_uid' element.
    $value = $form_state->getValue('tags_autocreate_specific_uid');
    // First value is an existing entity.
    $this->assertEquals($this->referencedEntities[0]->id(), $value[0]['target_id']);
    // Second value is an autocreated entity.
    $this->assertTrue(!isset($value[1]['target_id']));
    $this->assertEquals('tags - autocreated entity label with specific uid', $value[1]['entity']->label());
    $this->assertEquals($this->testAutocreateUser->id(), $value[1]['entity']->getOwnerId());
    // Third value is an existing entity.
    $this->assertEquals($this->referencedEntities[1]->id(), $value[2]['target_id']);

    // Test the 'single_string_id' element.
    $this->assertEquals($this->referencedEntities[2]->id(), $form_state->getValue('single_string_id'));

    // Test the 'tags_string_id' element.
    $expected = [
      ['target_id' => $this->referencedEntities[2]->id()],
      ['target_id' => $this->referencedEntities[3]->id()],
    ];
    $this->assertEquals($expected, $form_state->getValue('tags_string_id'));

    // Test the 'token' element.
    $this->assertEquals('[current-user:uid]', $form_state->getValue('token'));

    // Test the 'token_tags' element.
    $expected = [
      ['target_id' => '[current-user:uid]'],
      ['target_id' => '[current-user:mail]'],
    ];
    $this->assertEquals($expected, $form_state->getValue('token_tags'));

    // Test the 'token_default_value' element.
    $this->assertEquals('[current-user:uid]', $form_state->getValue('token_default_value'));

    // Test the 'token_tags_default_value' element.
    $expected = [
      ['target_id' => '[current-user:uid]'],
      ['target_id' => '[current-user:mail]'],
    ];
    $this->assertEquals($expected, $form_state->getValue('token_tags_default_value'));
  }

  /**
   * Tests invalid entries in the EntityAutocompleteOrToken Form API element.
   */
  public function testInvalidEntityAutocompleteOrTokenElement() {
    $form_builder = $this->container->get('form_builder');

    // Test 'single' with a entity label that doesn't exist.
    $form_state = (new FormState())
      ->setValues([
        'single' => 'single - non-existent label',
      ]);
    $form_builder->submitForm($this, $form_state);
    $this->assertCount(1, $form_state->getErrors());
    $t_args = ['%value' => 'single - non-existent label'];
    $this->assertEquals($this->t('There are no test entity entities matching "%value".', $t_args), $form_state->getErrors()['single']);

    // Test 'single' with a entity ID that doesn't exist.
    $form_state = (new FormState())
      ->setValues([
        'single' => 'single - non-existent label (42)',
      ]);
    $form_builder->submitForm($this, $form_state);
    $this->assertCount(1, $form_state->getErrors());
    $t_args = [
      '%type' => 'entity_test',
      '%id' => 42,
    ];
    $this->assertEquals($this->t('The referenced entity (%type: %id) does not exist.', $t_args), $form_state->getErrors()['single']);

    // Do the same tests as above but on an element with '#validate_reference'
    // set to FALSE.
    $form_state = (new FormState())
      ->setValues([
        'single_no_validate' => 'single - non-existent label',
        'single_autocreate_no_validate' => 'single - autocreate non-existent label',
      ]);
    $form_builder->submitForm($this, $form_state);

    // The element without 'autocreate' support still has to emit a warning when
    // the input doesn't end with an entity ID enclosed in parentheses.
    $this->assertCount(1, $form_state->getErrors());
    $t_args = ['%value' => 'single - non-existent label'];
    $this->assertEquals($this->t('There are no test entity entities matching "%value".', $t_args), $form_state->getErrors()['single_no_validate']);

    $form_state = (new FormState())
      ->setValues([
        'single_no_validate' => 'single - non-existent label (42)',
        'single_autocreate_no_validate' => 'single - autocreate non-existent label (43)',
      ]);
    $form_builder->submitForm($this, $form_state);

    // The input is complete (i.e. contains an entity ID at the end), no errors
    // are triggered.
    $this->assertCount(0, $form_state->getErrors());
  }

  /**
   * Tests that access is checked by the EntityAutocompleteOrToken element.
   */
  public function testEntityAutocompleteOrTokenAccess() {
    $form_builder = $this->container->get('form_builder');
    $form = $form_builder->getForm($this);

    // Check that the current user has proper access to view entity labels.
    $expected = $this->referencedEntities[0]->label() . ' (' . $this->referencedEntities[0]->id() . ')';
    $this->assertEquals($expected, $form['single_access']['#value']);

    $expected .= ', ' . $this->referencedEntities[1]->label() . ' (' . $this->referencedEntities[1]->id() . ')';
    $this->assertEquals($expected, $form['tags_access']['#value']);

    // Set up a non-admin user that is *not* allowed to view test entities.
    \Drupal::currentUser()->setAccount($this->createUser());

    // Rebuild the form.
    $form = $form_builder->getForm($this);

    $expected = $this->t('- Restricted access -') . ' (' . $this->referencedEntities[0]->id() . ')';
    $this->assertEquals($expected, $form['single_access']['#value']);

    $expected .= ', ' . $this->t('- Restricted access -') . ' (' . $this->referencedEntities[1]->id() . ')';
    $this->assertEquals($expected, $form['tags_access']['#value']);
  }

  /**
   * Tests ID input is handled correctly.
   */
  public function testEntityAutocompleteOrTokenIdInput() {
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form_state = (new FormState())
      ->setMethod('GET')
      ->setValues([
        'single' => [['target_id' => $this->referencedEntities[0]->id()]],
        'single_no_validate' => [['target_id' => $this->referencedEntities[0]->id()]],
        'token' => [['target_id' => '[current-user:uid]']],
        'token_tags' => [
          ['target_id' => '[current-user:uid]'],
          ['target_id' => '[current-user:mail]'],
        ],
      ]);

    $form_builder->submitForm($this, $form_state);

    $form = $form_state->getCompleteForm();

    $expected_label = $this->getAutocompleteInput($this->referencedEntities[0]);
    $this->assertSame($expected_label, $form['single']['#value']);
    $this->assertSame($expected_label, $form['single_no_validate']['#value']);
    $this->assertSame('[current-user:uid]', $form['token']['#value']);
    $this->assertSame('[current-user:uid], [current-user:mail]', $form['token_tags']['#value']);
  }

  /**
   * Returns a label in the format needed by the EntityAutocompleteOrToken.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A Drupal entity.
   *
   * @return string
   *   A string that can be used as a value for EntityAutocompleteOrToken
   *   elements.
   */
  protected function getAutocompleteInput(EntityInterface $entity) {
    return EntityAutocompleteOrToken::getEntityLabels([$entity]);
  }

}
