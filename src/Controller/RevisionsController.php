<?php

namespace Drupal\dyniva_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class RevisionsController extends ControllerBase  implements ContainerInjectionInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a RevisionsController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(DateFormatterInterface $date_formatter, RendererInterface $renderer) {
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer')
    );
  }

  /**
   * Prints the revision tree of the current entity.
   *
   * @param \Drupal\node\NodeInterface $node
   *    A Node object.
   *
   * @return array
   *    Array of page elements to render.
   */
  public function revisions(NodeInterface $node) {

    // 【Move this funciton to Drupal\dyniva_core\Plugin\ManagedEntity\Revision::buildPage】
    $rows = [];
    $node_storage = $this->entityManager()->getStorage('node');
    $block_content_storage = $this->entityManager()->getStorage('block_content');
    $revisions = $node_storage->revisionIds($node);
    foreach ($revisions as $index => $vid) {
      $revision = $node_storage->loadRevision($vid);
      $log = ['#markup' => $revision->revision_log->value];
      if (isset($revisions[$index - 1])) {
        $log_rows = [];
        $prevision = $node_storage->loadRevision($revisions[$index - 1]);
        if (isset($revision->panelizer[0]->panels_display['blocks'])){
          foreach ($revision->panelizer[0]->panels_display['blocks'] as $block_uuid => $block) {
            if (isset($prevision->panelizer[0]->panels_display['blocks'][$block_uuid])
              && $pblock = $prevision->panelizer[0]->panels_display['blocks'][$block_uuid]) {
              if ($block['region'] != $pblock['region']) {
                $log_rows[] = $this->t('%block region %from to %to', ['%block' => $block['label'],
                                '%from' => $pblock['region'],
                                '%to' => $block['region']]);
              }
              if (isset($pblock['vid']) && isset($block['vid']) && $block['vid'] != $pblock['vid']) {
                $block_content_revision = $block_content_storage->loadRevision($block['vid']);
                $log_rows[] = $this->t('%block updated %revision_log', [
                                '%block' => $block['label'],
                                '%revision_log' => $block_content_revision->revision_log->value]);
              }
            } else {
              if ($block['provider'] == 'block_content') {
                $block_content_revision = $block_content_storage->loadRevision($block['vid']);
                $log_rows[] = $this->t('%block added %revision_log', [
                                '%block' => $block['label'],
                                '%revision_log' => $block_content_revision->revision_log->value]);
              } else {
                $log_rows[] = $this->t('%block added', ['%block' => $block['label']]);
              }
            }
          }
        }
        if (isset($prevision->panelizer[0]->panels_display['blocks'])) {
          foreach ($prevision->panelizer[0]->panels_display['blocks'] as $block_uuid => $pblock) {
            if (!$block = $revision->panelizer[0]->panels_display['blocks'][$block_uuid]) {
              $log_rows[] = $this->t('%block deleted', ['%block' => $pblock['label']]);
            }
          }
        }

        $log = [
          '#theme' => 'item_list',
          '#items' => $log_rows,
          '#list_type' => 'ul',
        ];
      }

      $date = $this->dateFormatter->format($revision->revision_timestamp->value, 'short');
      $view = $this->t('view');
      if ($vid != $node->getRevisionId()) {
        $link = $this->l($view, new Url('entity.node.revision', ['node' => $node->id(), 'node_revision' => $vid]));
      }
      else {
        $link = $node->link($view);
      }
      $username = [
        '#theme' => 'username',
        '#account' => $revision->getRevisionUser(),
      ];
      $column = [
        'data' => [
          '#type' => 'inline_template',
          '#template' => '{% trans %}{{ date }}, {{ username }}, {{view}} {% endtrans %}{% if log %}<p class="revision-log">{{ log }}</p>{% endif %}',
          '#context' => [
            'date' => $date,
            'username' => $this->renderer->renderPlain($username),
            'view' => $link,
            'log' => $log,
          ],
        ],
      ];
      $rows[] = $column;
    }

    $output = [
      '#theme' => 'dyniva_core_revisions',
      '#attributes' => array('class' => array('dyniva-core-revisions')),
      '#items' => $rows,
      '#list_type' => 'ul',
    ];

    $output .= [
      '#theme' => 'item_list',
      '#attributes' => array('class' => array('dyniva-core-revisions')),
      '#items' => $rows,
      '#list_type' => 'ul',
    ];

    return $output;
  }

}
