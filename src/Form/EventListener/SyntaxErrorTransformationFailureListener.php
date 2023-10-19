<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Form\EventListener;

use Solido\QueryLanguage\Exception\SyntaxError;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

use function gettype;
use function is_scalar;
use function strtr;

class SyntaxErrorTransformationFailureListener implements EventSubscriberInterface
{
    public function __construct(private readonly TranslatorInterface|null $translator = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SUBMIT => ['convertTransformationFailureToFormError', 100],
        ];
    }

    /**
     * Converts an AQL syntax error into a form error, or process a transformation failure exception normally.
     */
    public function convertTransformationFailureToFormError(FormEvent $event): void
    {
        $form = $event->getForm();
        $failure = $form->getTransformationFailure();

        if ($failure === null || ! $form->isValid()) {
            return;
        }

        foreach ($form as $child) {
            if (! $child->isSynchronized()) {
                return;
            }
        }

        $clientDataAsString = is_scalar($form->getViewData()) ? (string) $form->getViewData() : gettype($form->getViewData());
        $previous = $failure;

        // phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedWhile
        while (($previous = $previous->getPrevious()) !== null && ! $previous instanceof SyntaxError) {
            // Intentionally empty. Just cycle all previous exceptions until a SyntaxError is found.
        }

        // phpcs:enable

        if ($previous === null) {
            return;
        }

        $messageTemplate = $previous->getMessage();

        if ($this->translator !== null) {
            $message = $this->translator->trans($messageTemplate, ['{{ value }}' => $clientDataAsString]);
        } else {
            $message = strtr($messageTemplate, ['{{ value }}' => $clientDataAsString]);
        }

        $form->addError(new FormError($message, $messageTemplate, ['{{ value }}' => $clientDataAsString], null, $form->getTransformationFailure()));
    }
}
