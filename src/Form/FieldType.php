<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Form;

use Solido\QueryLanguage\Form\EventListener\SyntaxErrorTransformationFailureListener;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FieldType extends AbstractType
{
    public function __construct(private TranslatorInterface|null $translator = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->addEventSubscriber(new SyntaxErrorTransformationFailureListener($this->translator))
            ->addViewTransformer(new DataTransformer\StringToExpresionTransformer());
    }

    public function getParent(): string|null
    {
        return TextType::class;
    }
}
