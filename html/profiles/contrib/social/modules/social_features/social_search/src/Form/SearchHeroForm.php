<?php

namespace Drupal\social_search\Form;

use Drupal\Component\Utility\Xss;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class SearchHeroForm.
 *
 * @package Drupal\social_search\Form
 */
class SearchHeroForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * SearchHeroForm constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(RouteMatchInterface $routeMatch, RequestStack $requestStack) {
    $this->routeMatch = $routeMatch;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_hero_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['search_input'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#title_display' => 'invisible',
    ];

    // Pre-fill search input on the search group page.
    $form['search_input']['#default_value'] = $this->routeMatch
      ->getParameter('keys');

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];
    $form['#cache']['contexts'][] = 'url';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $current_route = $this->routeMatch->getRouteName();
    $route_parts = explode('.', ($current_route ?? ''));

    $query = UrlHelper::filterQueryParameters($this->requestStack->getCurrentRequest()->query->all());

    // Unset the page parameter. When someone starts a new search query they
    // should always start again at the first page.
    unset($query['page']);

    $options = ['query' => $query];
    $parameters = [];

    if (empty($form_state->getValue('search_input'))) {
      // Redirect to the search page with empty search values.
      $new_route = "view.{$route_parts[1]}.page_no_value";
    }
    else {
      // Redirect to the search page with filters in the GET parameters.
      $search_input = Xss::filter($form_state->getValue('search_input'));
      $search_input = preg_replace('/[\/]+/', ' ', $search_input);
      $search_input = str_replace('&amp;', '&', $search_input);
      $parameters['keys'] = $search_input;

      $new_route = "view.{$route_parts[1]}.page";
    }

    $redirect = Url::fromRoute($new_route, $parameters, $options);

    $form_state->setRedirectUrl($redirect);
  }

}
