<?php 
namespace Drupal\dyniva_core;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\node\Entity\Node;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\Entity;
use Drupal\content_moderation\ModerationInformation;

class RevisionListBuilder {
  use StringTranslationTrait;
  use LinkGeneratorTrait;
  /**
   * 
   * @param EntityInterface $entity
   */
  public function getList(EntityInterface $entity){
    
    $items = $this->getItems($entity);
    
    $output = [
      '#theme' => 'dyniva_core_revisions',
      '#attributes' => array('class' => array('dyniva-core-revisions')),
      '#items' => $items,
      '#cache' => [
        'tags' => ["{$entity->getEntityTypeId()}:{$entity->id()}"],
        'max-age' => 0,
      ]
    ];
    return $output;
  }
  /**
   * 
   * @param EntityInterface $entity
   */
  public function getItems(EntityInterface $entity){
    $rows = [];
    /**
     *
     * @var DateFormatter $dateformatter
     */
    $dateformatter = \Drupal::service('date.formatter');
    
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    
    /**
     * @var ModerationInformation $moderation_info
     */
    $moderation_info = \Drupal::service('content_moderation.moderation_information');
    
    /**
     *
     * @var WorkflowInterface $workflow
     */
    $workflow = $moderation_info->getWorkflowForEntity($entity);
    
    if($entity->getEntityTypeId() == 'node'){
    
      $langcode = $entity->language()->getId();
      $block_content_storage = \Drupal::entityTypeManager()->getStorage('block_content');
      
      $revisions = workspace_ccms_entity_revisionIds($entity);
      $last_vid = end($revisions);
      $managed_entity = ccms_core_get_entity_managed_entity($entity);
      foreach ($revisions as $index => $vid) {
        /**
         * @var Node $revision
         */
        $revision = $entity_storage->loadRevision($vid);
        $revision = \Drupal::entityManager()->getTranslationFromContext($revision, $langcode, ['operation' => 'entity_upcast']);
        if($workflow){
          $state = $workflow->getTypePlugin()->getState($revision->moderation_state->value);
        }
        $log_rows = [];
        if (isset($revisions[$index - 1])) {
          /**
           * @var Node $revision
           */
          $prevision = $entity_storage->loadRevision($revisions[$index - 1]);
          $prevision = \Drupal::entityManager()->getTranslationFromContext($prevision, $langcode, ['operation' => 'entity_upcast']);
          if (isset($revision->panelizer[0]->panels_display['blocks'])){
            foreach ($revision->panelizer[0]->panels_display['blocks'] as $block_uuid => $block) {
              if (isset($prevision->panelizer[0]->panels_display['blocks'][$block_uuid])
                  && $pblock = $prevision->panelizer[0]->panels_display['blocks'][$block_uuid]) {
                    if ($block['region'] != $pblock['region']) {
                      $log_rows[] = $this->t('Moved %block region %from to %to.', ['%block' => $block['label'],
                        '%from' => $pblock['region'],
                        '%to' => $block['region']]);
                    }
                    if (isset($pblock['vid']) && isset($block['vid']) && $block['vid'] != $pblock['vid']) {
                      $block_content_revision = $block_content_storage->loadRevision($block['vid']);
                      $log_rows[] = $this->t('Updated %block%revision_log.', [
                        '%block' => $block['label'],
                        '%revision_log' => $block_content_revision->revision_log->value?', ' . $block_content_revision->revision_log->value:'']);
                    }
                  } else {
                    if ($block['provider'] == 'block_content') {
                      $block_content_revision = $block_content_storage->loadRevision($block['vid']);
                      $log_rows[] = $this->t('Added %block%revision_log.', [
                        '%block' => $block['label'],
                        '%revision_log' => $block_content_revision->revision_log->value?', ' . $block_content_revision->revision_log->value:'']);
                    } else {
                      $log_rows[] = $this->t('Added %block.', ['%block' => $block['label']]);
                    }
                  }
            }
          }
          if (isset($prevision->panelizer[0]->panels_display['blocks'])) {
            foreach ($prevision->panelizer[0]->panels_display['blocks'] as $block_uuid => $pblock) {
              if (!isset($revision->panelizer[0]->panels_display['blocks'][$block_uuid])) {
                $log_rows[] = $this->t('Deleted %block', ['%block' => $pblock['label']]);
              }
            }
          }
        }
    
        if(empty($log_rows) || !empty($revision->revision_log->value)){
          $log_rows[] = ['#markup' => !empty($revision->revision_log->value)?$revision->revision_log->value:'Updated.'];
        }
        $current = TRUE;
        if (!$revision->isDefaultRevision()) {
          $current = FALSE;
          if ($vid == $last_vid) {
            $link = $this->l($this->t('View'), new Url('entity.node.latest_version', ['node' => $entity->id()]));
          }else{
            
            $link = $this->l($this->t('View'), new Url('entity.node.revision', ['node' => $entity->id(), 'node_revision' => $vid]));
          }
        }else {
          $link = $entity->link('view');
        }
        $revert = FALSE;
        if ($vid != $last_vid && \Drupal::currentUser()->hasPermission("revert ccms {$managed_entity->id()} revision")) {
          $revert = $this->l($this->t('Revert'), new Url("ccms_core.managed_entity.{$managed_entity->id()}.revision_revert",
          ['managed_entity_id' => $entity->id(), 'node_revision' => $vid],
          ['query' => ['destination' => \Drupal::request()->getRequestUri()]]));
        }
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];
        
        $row = [
          'vid' => $revision->getRevisionId(),
          'date' => $revision->getChangedTime(),
          'user' => $username,
          'view' => $link,
          'revert' => $revert,
          'comments' => $log_rows,
          'current' => $current,
          'state' => $workflow?$state->label():false,
        ];
        $rows[$revision->getChangedTime()+$revision->getRevisionId()] = $row;
      }
    
    }else{
      $revisions = workspace_ccms_entity_revisionIds($entity);
      foreach ($revisions as $index => $vid) {
        /**
         * @var Entity $revision
         */
        $revision = $entity_storage->loadRevision($vid);
        if($workflow){
          $state = $workflow->getTypePlugin()->getState($revision->moderation_state->value);
        }
        
        $log_rows = [['#markup' => $revision->getRevisionLogMessage()?:'Updated.']];
        $link = "";
        $current = TRUE;
        if ($vid != $entity->getRevisionId()) {
          //           $link = $this->l($this->t('View'), new Url('entity.' . $entity->getEntityTypeId() . '.revision', [$entity->getEntityTypeId() => $entity->id(), $entity->getEntityTypeId() . '_revision' => $vid]));
          $current = FALSE;
        }
        else {
          $link = $entity->link('view');
        }
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];
        $row = [
          'vid' => $revision->getRevisionId(),
          'date' => $revision->getChangedTime(),
          'user' => $username,
          'view' => $link,
          'comments' => $log_rows,
          'current' => $current,
          'state' => $workflow?$state->label():false,
        ];
        $rows[$revision->getChangedTime()+$revision->getRevisionId()] = $row;
      }
    
    }
    krsort($rows);
    $items = array();
    $index = count($rows);
    foreach ($rows as $timestamp => $row){
      $day = $dateformatter->format($row['date'], 'custom','F j, Y');
      $row['date'] = $dateformatter->formatInterval(time() - $row['date']) . ' ' . t('ago');
      $row['index'] = $index--;
      $items[$day][] = $row;
    }
    
    return $items;
  }
}
