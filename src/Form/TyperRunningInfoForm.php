<?php

namespace Drupal\tyres_running_info\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the tyres_running_info entity edit forms.
 */
class TyperRunningInfoForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $entity = $this->getEntity();

    $message_arguments = ['%label' => $entity->toLink()->toString()];
    $logger_arguments = [
      '%label' => $entity->label(),
      'link' => $entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New tyres_running_info %label has been created.', $message_arguments));
        $this->logger('tyres_running_info')->notice('Created new tyres_running_info %label', $logger_arguments);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The tyres_running_info %label has been updated.', $message_arguments));
        $this->logger('tyres_running_info')->notice('Updated tyres_running_info %label.', $logger_arguments);
        break;
    }

    $form_state->setRedirect('entity.typer_running_info.canonical', ['typer_running_info' => $entity->id()]);

    return $result;
  }

}
