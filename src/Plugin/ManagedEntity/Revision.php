<?php
namespace Drupal\dyniva_core\Plugin\ManagedEntity;

use Drupal\dyniva_core\Plugin\ManagedEntityPluginBase;
use Drupal\dyniva_core\Entity\ManagedEntity;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;


/**
 * ManagedEntity Plugin.
 *
 * @ManagedEntityPlugin(
 *  id = "revision",
 *  label = @Translation("Revisions"),
 *  weight = 2
 * )
 *
 */
class Revision extends ManagedEntityPluginBase{
  use StringTranslationTrait;
  use LinkGeneratorTrait;
  /**
   * @inheritdoc
   */
  public function buildPage(ManagedEntity $managedEntity, EntityInterface $entity){
    return \Drupal::service('dyniva_core.revision_list_builder')->getList($entity);
  }
  /**
   * @inheritdoc
   */
  public function getPageTitle(ManagedEntity $managedEntity, EntityInterface $entity){
    return $this->pluginDefinition['label'];
  }
  /**
   * @inheritdoc
   */
  public function isMenuTask(ManagedEntity $managedEntity){
    return TRUE;
  }
  /**
   * @inheritdoc
   */
  public function isMenuAction(ManagedEntity $managedEntity){
    return FALSE;
  }
}
